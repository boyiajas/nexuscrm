<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CampaignClient;
use App\Models\CampaignWhatsappMessage;
use App\Models\CampaignWhatsappRecipient;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Client;
use App\Services\TwilioWhatsAppService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class TwilioController extends Controller
{
    public function whatsappSenders(TwilioWhatsAppService $service): JsonResponse
    {
        $senders = $service->listWhatsappSenders();

        return response()->json($senders);
    }

    /**
     * Public webhook for Twilio WhatsApp status + inbound messages.
     * Configure this URL as the Status Callback and inbound webhook in Twilio.
     */
    public function webhookWhatsapp(Request $request): JsonResponse
    {
        $payload = $request->all();
        $messageSid = $request->input('MessageSid') ?? $request->input('SmsSid');
        $status     = strtolower((string) ($request->input('MessageStatus') ?? $request->input('SmsStatus') ?? ''));
        $body       = trim((string) $request->input('Body', ''));
        $from       = $this->normalizePhone($request->input('From'));
        $to         = $this->normalizePhone($request->input('To'));

        Log::info('Twilio WhatsApp webhook hit', [
            'MessageSid' => $messageSid,
            'status'     => $status,
            'from'       => $from,
            'to'         => $to,
        ]);

        // If we have a received status or body, treat as inbound client message
        if ($status === 'received' || ($body && !$status)) {
            $this->handleInboundMessage($from, $body, $messageSid, $payload);
            return response()->json(['ok' => true]);
        }

        // Otherwise treat as delivery status update
        if ($messageSid || $from) {
            $this->handleStatusUpdate($messageSid, $status, $from, $payload);
        }

        return response()->json(['ok' => true]);
    }

    protected function handleStatusUpdate(?string $messageSid, string $status, ?string $toPhone, array $payload = []): void
    {
        $recipient = null;

        if ($messageSid) {
            $recipient = CampaignWhatsappRecipient::where('message_sid', $messageSid)->first();
        }

        if (!$recipient && $toPhone) {
            $recipient = CampaignWhatsappRecipient::where('phone', $toPhone)
                ->latest('id')
                ->first();
        }

        if (!$recipient) {
            Log::warning('No recipient matched for Twilio status', ['message_sid' => $messageSid, 'to' => $toPhone]);
            return;
        }

        $mappedStatus = $this->mapTwilioStatus($status);

        $recipient->status = $mappedStatus;
        $recipient->status_payload = $payload;

        if ($mappedStatus === 'Delivered') {
            $recipient->delivered_at = Carbon::now();
        } elseif ($mappedStatus === 'Failed') {
            $recipient->error_code = $payload['ErrorCode'] ?? $recipient->error_code;
            $recipient->error_message = $payload['ErrorMessage'] ?? ($payload['Message'] ?? null);
        }

        $recipient->save();

        // Mirror status to campaign pivot (if exists)
        $campaignId = $recipient->message?->campaign_id;

        if ($recipient->client_id && $recipient->whatsapp_message_id && $campaignId) {
            CampaignClient::where('campaign_id', $campaignId)
                ->where('client_id', $recipient->client_id)
                ->update([
                    'whatsapp_status'  => $recipient->status,
                    'whatsapp_sent_at' => $recipient->delivered_at ?? now(),
                    'updated_at'       => now(),
                ]);
        }

        $this->refreshWhatsappMessageCounts($recipient->message);
    }

    protected function handleInboundMessage(?string $from, string $body, ?string $messageSid, array $payload = []): void
    {
        if (!$from) {
            return;
        }

        $normalizedReply = $this->normalizeReply($body);

        $recipient = CampaignWhatsappRecipient::where('phone', $from)
            ->latest('id')
            ->first();

        if ($recipient) {
            $recipient->last_response = $normalizedReply ?? $body;
            $recipient->last_response_at = Carbon::now();
            if (!$recipient->message_sid && $messageSid) {
                $recipient->message_sid = $messageSid;
            }
            $recipient->save();
            $this->refreshWhatsappMessageCounts($recipient->message);
        }

        $client = $this->findClientByPhone($from);

        // Create chat session + message for live chat view
        $session = null;
        if ($client) {
            $session = ChatSession::firstOrCreate(
                [
                    'client_id' => $client->id,
                    'platform'  => 'whatsapp',
                ],
                [
                    'client_name' => $client->name,
                    'phone'       => $client->phone ?? $from,
                    'status'      => 'active',
                    'unread_count'=> 0,
                ]
            );
        } else {
            $session = ChatSession::firstOrCreate(
                [
                    'client_name' => $from,
                    'platform'    => 'whatsapp',
                ],
                [
                    'phone'        => $from,
                    'status'       => 'active',
                    'unread_count' => 0,
                ]
            );
        }

        $session->messages()->create([
            'sender'  => 'client',
            'content' => $body,
            'sent_at' => Carbon::now(),
        ]);

        $session->increment('unread_count');
        $session->update(['last_message' => $body]);
    }

    protected function mapTwilioStatus(string $status): string
    {
        return match (strtolower($status)) {
            'delivered', 'read' => 'Delivered',
            'failed', 'undelivered' => 'Failed',
            default => 'Pending',
        };
    }

    protected function refreshWhatsappMessageCounts(?CampaignWhatsappMessage $message): void
    {
        if (!$message) return;

        $delivered = $message->recipients()->whereRaw('LOWER(status) = ?', ['delivered'])->count();
        $failed    = $message->recipients()->whereRaw('LOWER(status) = ?', ['failed'])->count();
        $pending   = $message->recipients()->whereNotIn('status', ['Delivered', 'Failed'])->count();
        $replies   = $message->recipients()->whereNotNull('last_response')->count();

        $message->update([
            'delivered' => $delivered,
            'failed'    => $failed,
            'pending'   => $pending,
            'track_responses' => $message->track_responses,
            'enable_live_chat' => $message->enable_live_chat,
        ]);
    }

    protected function normalizePhone(?string $raw): ?string
    {
        if (!$raw) return null;
        $trimmed = str_ireplace('whatsapp:', '', $raw);
        $normalized = TwilioWhatsAppService::normalizeZA($trimmed) ?? $trimmed;
        return $normalized;
    }

    protected function findClientByPhone(string $phone): ?Client
    {
        $normalized = $this->normalizePhone($phone) ?? $phone;
        $digits = preg_replace('/\D+/', '', $normalized);

        if (!$digits) {
            return null;
        }

        // Fix: Use backticks for column name and single quotes for string literals
        return Client::query()
            ->where('phone', $normalized)
            ->orWhere('phone', $phone)
            ->orWhereRaw(
                "REPLACE(REPLACE(REPLACE(`phone`, '+', ''), ' ', ''), '-', '') = ?",
                [$digits]
            )
            ->first();
    }

    protected function normalizeReply(string $body): ?string
    {
        $trimmed = strtolower(trim($body));
        if ($trimmed === '') {
            return null;
        }

        return match ($trimmed) {
            '1', 'yes', 'y' => 'yes',
            '2', 'no', 'n'  => 'no',
            default         => $trimmed,
        };
    }
}
