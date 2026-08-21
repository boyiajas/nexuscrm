<?php

namespace App\Jobs;

use App\Contracts\WhatsAppServiceInterface;
use App\Models\CampaignClient;
use App\Models\CampaignWhatsappRecipient;
use App\Services\WhatsAppBatchService;
use App\Services\WhatsAppDailyLimitService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessCampaignWhatsappRecipientJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $recipientId)
    {
    }

    public function middleware(): array
    {
        return [
            new RateLimited('meta-whatsapp-outbound'),
        ];
    }

    public function backoff(): array
    {
        return [1, 5, 15];
    }

    public function handle(
        WhatsAppServiceInterface $whatsApp,
        WhatsAppDailyLimitService $dailyLimitService,
        WhatsAppBatchService $batchService
    ): void {
        $recipient = CampaignWhatsappRecipient::with(['message.campaign.bank', 'message.createdBy', 'client'])
            ->find($this->recipientId);

        if (!$recipient || !$recipient->message || !$recipient->message->campaign) {
            return;
        }

        $message = $recipient->message;
        $campaign = $message->campaign;
        $client = $recipient->client;

        if (in_array($message->status, ['Paused', 'Completed', 'Completed With Failures', 'Failed'], true)) {
            if ($recipient->status === 'Queued' || $recipient->status === 'Processing') {
                $recipient->update(['status' => 'Paused']);
                $batchService->syncMessageProgress($message);
            }
            return;
        }

        if (in_array($recipient->status, ['Delivered', 'Delivered (Ecosystem Warning)', 'Suppressed', 'No Lawful Basis', 'No Phone'], true)) {
            $batchService->syncMessageProgress($message);
            return;
        }

        $createdBy = $message->createdBy;
        if ($createdBy) {
            $limitCheck = $dailyLimitService->validateSendAllowance($createdBy, 1);
            if (!$limitCheck['allowed']) {
                $recipient->update([
                    'status' => 'Paused',
                    'error_message' => $limitCheck['message'],
                ]);
                $batchService->pauseMessage($message, $limitCheck['message']);
                return;
            }
        }

        $phone = $recipient->phone ?: $batchService->resolveClientPhone($client);
        if (!$phone) {
            $recipient->update([
                'status' => 'No Phone',
                'error_message' => 'Recipient has no valid phone number.',
            ]);
            $batchService->syncMessageProgress($message);
            return;
        }

        if ($client && !$batchService->canSendWhatsappToClient($client)) {
            $recipient->update([
                'status' => $batchService->whatsappComplianceBlockedStatus($client),
            ]);
            $batchService->syncMessageProgress($message);
            return;
        }

        $senderContext = method_exists($whatsApp, 'resolveSenderContext')
            ? $whatsApp->resolveSenderContext($message->provider_display_phone_number ?: $campaign->whatsapp_from)
            : [
                'phone_number_id' => $message->provider_phone_number_id,
                'display_phone_number' => $message->provider_display_phone_number ?: $campaign->whatsapp_from,
            ];

        $now = now();

        $message->update([
            'status' => 'Processing',
            'queued_at' => $message->queued_at ?: $now,
            'processing_started_at' => $message->processing_started_at ?: $now,
            'paused_at' => null,
            'pause_reason' => null,
        ]);

        $recipient->update([
            'status' => 'Processing',
            'queued_at' => $recipient->queued_at ?: $now,
            'processing_started_at' => $now,
            'attempts_count' => (int) $recipient->attempts_count + 1,
            'last_attempted_at' => $now,
            'phone' => $phone,
        ]);

        $attempt = $batchService->startAttempt($recipient->fresh(['message']));

        try {
            $subject = $client?->name ?? '';
            $bodyVar = $message->mode === 'flow'
                ? ($message->flow_definition[0]['message'] ?? '')
                : '';
            $resolvedTemplateVariables = $batchService->resolveTemplateVariableValues(
                $message->template_variables ?? [],
                $client,
                $campaign
            );

            $response = $whatsApp->sendTemplateFromSubjectMessage(
                $phone,
                $message->template_sid,
                $subject,
                $bodyVar,
                $resolvedTemplateVariables,
                $campaign->whatsapp_from
            );

            $status = $this->mapProviderStatus($response['status'] ?? 'queued');
            $deliveredAt = $status === 'Delivered' ? now() : null;
            $providerMessageId = $response['message_id'] ?? ($response['sid'] ?? null);

            $recipient->update([
                'message_sid' => $response['sid'] ?? $recipient->message_sid,
                'provider_message_id' => $providerMessageId ?: $recipient->provider_message_id,
                'provider_phone_number_id' => $response['phone_number_id'] ?? $senderContext['phone_number_id'],
                'provider_display_phone_number' => $response['display_phone_number'] ?? $senderContext['display_phone_number'],
                'status' => $status,
                'delivered_at' => $deliveredAt ?: $recipient->delivered_at,
                'error_code' => null,
                'error_message' => null,
            ]);

            if ($client) {
                CampaignClient::where('campaign_id', $campaign->id)
                    ->where('client_id', $client->id)
                    ->update([
                        'whatsapp_status' => $status,
                        'whatsapp_sent_at' => $deliveredAt ?: now(),
                        'updated_at' => now(),
                    ]);
            }

            $batchService->completeAttempt($attempt, $recipient->fresh(), $status, $providerMessageId);
            $batchService->syncMessageProgress($message->fresh());
        } catch (\Throwable $e) {
            $errorMsg = $e->getMessage();
            $isEcosystemWarning = str_contains(strtolower($errorMsg), 'maintain healthy ecosystem engagement')
                || str_contains($errorMsg, '131049')
                || str_contains($errorMsg, '131026');

            if ($isEcosystemWarning) {
                Log::info('WhatsApp send generated ecosystem engagement advisory (message delivered)', [
                    'campaign_id' => $campaign->id,
                    'message_id' => $message->id,
                    'recipient_id' => $recipient->id,
                    'client_id' => $client?->id,
                    'advisory' => $errorMsg,
                ]);

                $recipient->update([
                    'status' => 'Delivered',
                    'delivered_at' => $recipient->delivered_at ?: now(),
                    'error_code' => '131049',
                    'error_message' => $errorMsg,
                ]);

                if ($client) {
                    CampaignClient::where('campaign_id', $campaign->id)
                        ->where('client_id', $client->id)
                        ->update([
                            'whatsapp_status' => 'Delivered',
                            'whatsapp_sent_at' => $recipient->delivered_at ?: now(),
                            'updated_at' => now(),
                        ]);
                }

                $batchService->completeAttempt($attempt, $recipient->fresh(), 'Delivered', null, '131049', $errorMsg);
                $batchService->syncMessageProgress($message->fresh());
                return;
            }

            Log::error('Queued WhatsApp recipient send failed', [
                'campaign_id' => $campaign->id,
                'message_id' => $message->id,
                'recipient_id' => $recipient->id,
                'client_id' => $client?->id,
                'error' => $errorMsg,
            ]);

            $recipient->update([
                'status' => 'Failed',
                'error_message' => $errorMsg,
            ]);

            if ($client) {
                CampaignClient::where('campaign_id', $campaign->id)
                    ->where('client_id', $client->id)
                    ->update([
                        'whatsapp_status' => 'Failed',
                        'updated_at' => now(),
                    ]);
            }

            $batchService->completeAttempt($attempt, $recipient->fresh(), 'Failed', null, null, $errorMsg);
            $batchService->syncMessageProgress($message->fresh());

            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        $recipient = CampaignWhatsappRecipient::with('message')->find($this->recipientId);
        if (!$recipient || !$recipient->message) {
            return;
        }

        $recipient->update([
            'status' => 'Failed',
            'error_message' => $e->getMessage(),
        ]);

        app(WhatsAppBatchService::class)->syncMessageProgress($recipient->message->fresh());
    }

    protected function mapProviderStatus(?string $status): string
    {
        return match (strtolower((string) $status)) {
            'delivered', 'read'             => 'Delivered',
            'sent', 'accepted', 'queued'    => 'Sent',
            'failed', 'undelivered', 'error' => 'Failed',
            default                         => 'Pending',
        };
    }
}
