<?php

namespace App\Services;

use App\Jobs\ProcessCampaignWhatsappRecipientJob;
use App\Models\Campaign;
use App\Models\CampaignWhatsappMessage;
use App\Models\CampaignWhatsappRecipient;
use App\Models\Client;
use App\Models\WhatsAppSendAttempt;
use Illuminate\Support\Carbon;

class WhatsAppBatchService
{
    public const DEFAULT_MESSAGES_PER_SECOND = 20;

    public function enforcedMessagesPerSecond(): int
    {
        return self::DEFAULT_MESSAGES_PER_SECOND;
    }

    public function dispatchQueuedRecipients(CampaignWhatsappMessage $message): int
    {
        $recipientIds = $message->recipients()
            ->whereRaw('LOWER(status) = ?', ['queued'])
            ->pluck('id');

        foreach ($recipientIds as $recipientId) {
            ProcessCampaignWhatsappRecipientJob::dispatch((int) $recipientId)->onQueue('whatsapp');
        }

        return $recipientIds->count();
    }

    public function queueAllRecipients(CampaignWhatsappMessage $message): int
    {
        $now = now();

        $message->update([
            'status' => 'Queued',
            'queued_at' => $message->queued_at ?: $now,
            'processing_started_at' => null,
            'completed_at' => null,
            'paused_at' => null,
            'pause_reason' => null,
            'messages_per_second' => $message->messages_per_second ?: $this->enforcedMessagesPerSecond(),
        ]);

        $message->recipients()
            ->whereNotIn('status', ['Delivered', 'Delivered (Ecosystem Warning)', 'Suppressed', 'No Lawful Basis', 'No Phone'])
            ->update([
                'status' => 'Queued',
                'queued_at' => $now,
                'processing_started_at' => null,
                'updated_at' => $now,
            ]);

        $this->syncMessageProgress($message);

        return $this->dispatchQueuedRecipients($message->fresh());
    }

    public function pauseMessage(CampaignWhatsappMessage $message, string $reason, bool $markQueuedRecipients = true): CampaignWhatsappMessage
    {
        $now = now();

        $message->update([
            'status' => 'Paused',
            'paused_at' => $now,
            'pause_reason' => $reason,
            'last_processed_at' => $now,
        ]);

        if ($markQueuedRecipients) {
            $message->recipients()
                ->whereIn('status', ['Queued', 'Processing'])
                ->update([
                    'status' => 'Paused',
                    'error_message' => $reason,
                    'updated_at' => $now,
                ]);
        }

        return $this->syncMessageProgress($message->fresh());
    }

    public function resumeMessage(CampaignWhatsappMessage $message): array
    {
        $now = now();

        $message->recipients()
            ->where('status', 'Paused')
            ->update([
                'status' => 'Queued',
                'queued_at' => $now,
                'processing_started_at' => null,
                'updated_at' => $now,
            ]);

        $message->update([
            'status' => 'Queued',
            'queued_at' => $now,
            'paused_at' => null,
            'pause_reason' => null,
            'completed_at' => null,
            'messages_per_second' => $message->messages_per_second ?: $this->enforcedMessagesPerSecond(),
        ]);

        $queuedCount = $this->dispatchQueuedRecipients($message->fresh());

        return [
            'message' => $this->syncMessageProgress($message->fresh()),
            'queued_count' => $queuedCount,
        ];
    }

    public function retryFailedRecipients(CampaignWhatsappMessage $message): array
    {
        $now = now();

        $message->recipients()
            ->whereRaw('LOWER(status) = ?', ['failed'])
            ->update([
                'status' => 'Queued',
                'queued_at' => $now,
                'processing_started_at' => null,
                'error_code' => null,
                'error_message' => null,
                'updated_at' => $now,
            ]);

        $message->update([
            'status' => 'Queued',
            'queued_at' => $now,
            'paused_at' => null,
            'pause_reason' => null,
            'completed_at' => null,
            'messages_per_second' => $message->messages_per_second ?: $this->enforcedMessagesPerSecond(),
        ]);

        $queuedCount = $this->dispatchQueuedRecipients($message->fresh());

        return [
            'message' => $this->syncMessageProgress($message->fresh()),
            'queued_count' => $queuedCount,
        ];
    }

    public function syncMessageProgress(CampaignWhatsappMessage $message): CampaignWhatsappMessage
    {
        $counts = $this->recipientCounts($message);
        $status = $this->resolveMessageStatus($message, $counts);
        $completedAt = in_array($status, ['Completed', 'Completed With Failures', 'Failed'], true)
            ? ($message->completed_at ?: now())
            : null;

        $message->update([
            'total' => $counts['total'],
            'delivered' => $counts['delivered'],
            'failed' => $counts['failed'],
            'pending' => $counts['pending'],
            'status' => $status,
            'completed_at' => $completedAt,
            'last_processed_at' => now(),
            'messages_per_second' => $message->messages_per_second ?: $this->enforcedMessagesPerSecond(),
        ]);

        return $message->fresh();
    }

    public function recipientCounts(CampaignWhatsappMessage $message): array
    {
        $query = $message->recipients();

        $total           = (clone $query)->count();
        $delivered       = (clone $query)->where(function($q) {
            $q->whereIn('status', ['Delivered', 'Delivered (Ecosystem Warning)'])
              ->orWhereRaw('LOWER(status) = ?', ['delivered']);
        })->count();
        $sent            = (clone $query)->whereRaw('LOWER(status) = ?', ['sent'])->count();
        $failed          = (clone $query)->whereRaw('LOWER(status) = ?', ['failed'])->count();
        $queued          = (clone $query)->whereRaw('LOWER(status) = ?', ['queued'])->count();
        $processing      = (clone $query)->whereRaw('LOWER(status) = ?', ['processing'])->count();
        $paused          = (clone $query)->whereRaw('LOWER(status) = ?', ['paused'])->count();
        $providerPending = (clone $query)->whereRaw('LOWER(status) = ?', ['pending'])->count();
        $suppressed      = (clone $query)->whereIn('status', ['Suppressed', 'No Lawful Basis', 'No Phone'])->count();

        return [
            'total'            => $total,
            'delivered'        => $delivered,
            'sent'             => $sent,     // Accepted by Meta, awaiting delivery webhook
            'failed'           => $failed,
            'queued'           => $queued,
            'processing'       => $processing,
            'paused'           => $paused,
            'provider_pending' => $providerPending,
            'suppressed'       => $suppressed,
            // Sent is included in pending so batch stays active until webhooks arrive
            'pending'          => $queued + $processing + $providerPending + $paused + $sent,
        ];
    }

    public function resolveMessageStatus(CampaignWhatsappMessage $message, array $counts): string
    {
        if (!$message->sent_at && empty($message->queued_at)) {
            return 'Draft';
        }

        if (($message->status === 'Paused' || $message->paused_at) && $counts['pending'] > 0) {
            return 'Paused';
        }

        if ($counts['processing'] > 0) {
            return 'Processing';
        }

        // Recipients accepted by Meta but awaiting delivery webhooks — keep batch active
        if ($counts['queued'] > 0 || $counts['provider_pending'] > 0 || $counts['sent'] > 0) {
            return 'Queued';
        }

        if ($counts['delivered'] === 0 && $counts['failed'] > 0) {
            return 'Failed';
        }

        if ($counts['delivered'] > 0 && $counts['failed'] > 0) {
            return 'Completed With Failures';
        }

        if ($counts['delivered'] > 0) {
            return 'Completed';
        }

        return $message->status ?: 'Draft';
    }

    public function normalizePhone(?string $raw): ?string
    {
        if (!$raw) {
            return null;
        }

        $normalized = MetaWhatsAppService::normalizePhoneNumber($raw);
        if ($normalized) {
            return $normalized;
        }

        return str_starts_with($raw, '+') ? $raw : $raw;
    }

    public function resolveClientPhone($client): ?string
    {
        foreach (['cell_phone', 'phone', 'home_phone', 'work_phone'] as $field) {
            $raw = data_get($client, $field);
            if (!is_string($raw) || trim($raw) === '') {
                continue;
            }

            $normalized = $this->normalizePhone($raw);
            if ($normalized) {
                return $normalized;
            }
        }

        return null;
    }

    public function isWhatsappSuppressedClient($client): bool
    {
        if (!$client) {
            return false;
        }

        if ($client instanceof Client) {
            return $client->isWhatsappSuppressed();
        }

        return !empty($client->whatsapp_opted_out_at);
    }

    public function clientHasWhatsappLawfulBasis($client): bool
    {
        if (!$client) {
            return false;
        }

        if ($client instanceof Client) {
            return $client->hasWhatsappLawfulBasis();
        }

        return !empty($client->whatsapp_contact_basis) || !empty($client->whatsapp_opted_in_at);
    }

    public function canSendWhatsappToClient($client): bool
    {
        return !$this->isWhatsappSuppressedClient($client) && $this->clientHasWhatsappLawfulBasis($client);
    }

    public function whatsappComplianceBlockedStatus($client): string
    {
        if ($this->isWhatsappSuppressedClient($client)) {
            return 'Suppressed';
        }

        return 'No Lawful Basis';
    }

    public function resolveTemplateVariableValues(array $templateVariables, $client, Campaign $campaign): array
    {
        if (empty($templateVariables)) {
            return [];
        }

        $keys = array_map('strval', array_keys($templateVariables));
        usort($keys, function ($a, $b) {
            $prefixA = preg_replace('/\d+/', '', $a);
            $prefixB = preg_replace('/\d+/', '', $b);
            if ($prefixA === $prefixB) {
                return (int) preg_replace('/\D+/', '', $a) <=> (int) preg_replace('/\D+/', '', $b);
            }
            return strcmp($prefixA, $prefixB);
        });

        $values = [];
        foreach ($keys as $key) {
            $entry = $templateVariables[$key] ?? [];
            $source = is_array($entry) ? ($entry['source'] ?? null) : $entry;
            $customValue = is_array($entry) ? ($entry['custom_value'] ?? null) : null;

            $firstName = $client?->first_name ?: (explode(' ', $client?->name ?? '')[0] ?? '');
            $surname = $client?->surname ?: (implode(' ', array_slice(explode(' ', $client?->name ?? ''), 1)) ?: '');

            $val = match ($source) {
                'client.name' => (string) ($client?->name ?? ''),
                'client.title' => (string) ($client?->title ?? ''),
                'client.first_name' => (string) $firstName,
                'client.surname' => (string) $surname,
                'client.phone' => (string) ($this->resolveClientPhone($client) ?? ''),
                'client.email' => (string) ($client?->email ?? ''),
                'client.id_number' => (string) ($client?->id_number ?? ''),
                'client.account_number' => (string) ($client?->account_number ?? ''),
                'client.easy_pay_number' => (string) ($client?->easy_pay_number ?? ''),
                'client.outstanding_balance' => (string) ($client?->outstanding_balance ?? ''),
                'client.arrears_amount' => (string) ($client?->arrears_amount ?? ''),
                'client.installment_amount' => (string) ($client?->installment_amount ?? ''),
                'client.bank_name' => (string) ($client?->bank_name ?? $campaign->bank?->name ?? ''),
                'client.branch_code' => (string) ($client?->branch_code ?? ''),
                'campaign.name' => (string) ($campaign->name ?? ''),
                'campaign.status' => (string) ($campaign->status ?? ''),
                'custom' => (string) ($customValue ?? ''),
                default => '',
            };

            $values[$key] = trim($val) === '' ? ' ' : $val;
        }

        return $values;
    }

    public function startAttempt(CampaignWhatsappRecipient $recipient): WhatsAppSendAttempt
    {
        return WhatsAppSendAttempt::create([
            'campaign_whatsapp_message_id' => $recipient->whatsapp_message_id,
            'campaign_whatsapp_recipient_id' => $recipient->id,
            'client_id' => $recipient->client_id,
            'user_id' => $recipient->message?->created_by_user_id,
            'attempt_date' => now()->toDateString(),
            'attempted_at' => now(),
            'status' => 'Processing',
        ]);
    }

    public function completeAttempt(WhatsAppSendAttempt $attempt, CampaignWhatsappRecipient $recipient, string $status, ?string $providerMessageId = null, ?string $errorCode = null, ?string $errorMessage = null): void
    {
        $attempt->update([
            'status' => $status,
            'provider_message_id' => $providerMessageId,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
        ]);

        $recipient->update([
            'last_attempted_at' => Carbon::parse($attempt->attempted_at),
        ]);
    }
}
