<?php

namespace App\Http\Controllers\Api;

use App\Contracts\WhatsAppServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\CampaignClient;
use App\Models\CampaignWhatsappMessage;
use App\Models\CampaignWhatsappRecipient;
use App\Models\ChatSession;
use App\Models\Client;
use App\Services\MetaWhatsAppService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    public function __construct(private WhatsAppServiceInterface $whatsApp)
    {
    }

    public function whatsappSenders(): JsonResponse
    {
        return response()->json($this->whatsApp->listWhatsappSenders());
    }

    public function verify(Request $request)
    {
        $verifyToken = method_exists($this->whatsApp, 'verifyToken') ? $this->whatsApp->verifyToken() : null;

        if (
            $request->query('hub.mode') === 'subscribe' &&
            hash_equals((string) $verifyToken, (string) $request->query('hub.verify_token'))
        ) {
            return response($request->query('hub.challenge'), 200);
        }

        abort(403, 'Invalid webhook verification token.');
    }

    public function webhook(Request $request): JsonResponse
    {
        $payload = $request->all();

        foreach (($payload['entry'] ?? []) as $entry) {
            foreach (($entry['changes'] ?? []) as $change) {
                $value = $change['value'] ?? [];

                foreach (($value['statuses'] ?? []) as $status) {
                    $this->handleStatusUpdate($status, $value);
                }

                foreach (($value['messages'] ?? []) as $message) {
                    $this->handleInboundMessage($message, $value);
                }
            }
        }

        return response()->json(['ok' => true]);
    }

    protected function handleStatusUpdate(array $status, array $payload = []): void
    {
        $messageId = $status['id'] ?? null;
        $statusName = strtolower((string) ($status['status'] ?? ''));
        $recipientPhone = MetaWhatsAppService::normalizePhoneNumber((string) ($status['recipient_id'] ?? ''));

        $recipient = null;
        if ($messageId) {
            $recipient = CampaignWhatsappRecipient::where('provider_message_id', $messageId)
                ->orWhere('message_sid', $messageId)
                ->first();
        }

        if (!$recipient && $recipientPhone) {
            $recipient = CampaignWhatsappRecipient::where('phone', $recipientPhone)->latest('id')->first();
        }

        if (!$recipient) {
            Log::warning('No recipient matched for Meta status', [
                'message_id' => $messageId,
                'recipient_id' => $status['recipient_id'] ?? null,
            ]);
            return;
        }

        $mappedStatus = $this->mapStatus($statusName);

        $recipient->status = $mappedStatus;
        $recipient->message_sid = $messageId ?: $recipient->message_sid;
        $recipient->provider_message_id = $messageId ?: $recipient->provider_message_id;
        $recipient->status_payload = $payload;
        $recipient->provider_status_payload = $payload;

        if ($mappedStatus === 'Delivered') {
            $recipient->delivered_at = Carbon::now();
        } elseif ($mappedStatus === 'Failed') {
            $recipient->error_code = $status['errors'][0]['code'] ?? $recipient->error_code;
            $recipient->error_message = $status['errors'][0]['title'] ?? $recipient->error_message;
        }

        $recipient->save();

        $campaignId = $recipient->message?->campaign_id;
        if ($recipient->client_id && $campaignId) {
            CampaignClient::where('campaign_id', $campaignId)
                ->where('client_id', $recipient->client_id)
                ->update([
                    'whatsapp_status' => $recipient->status,
                    'whatsapp_sent_at' => $recipient->delivered_at ?? now(),
                    'updated_at' => now(),
                ]);
        }

        $this->refreshWhatsappMessageCounts($recipient->message);
    }

    protected function handleInboundMessage(array $message, array $payload = []): void
    {
        $from = MetaWhatsAppService::normalizePhoneNumber((string) ($message['from'] ?? ''));
        $body = trim((string) ($message['text']['body'] ?? ''));
        $messageId = $message['id'] ?? null;

        if (!$from || $body === '') {
            return;
        }

        $normalizedReply = $this->normalizeReply($body);
        $recipient = $this->findRecipientByPhone($from);
        $client = $this->findClientByPhone($from);

        if (!$recipient && $client) {
            $recipient = CampaignWhatsappRecipient::where('client_id', $client->id)->latest('id')->first();
        }

        if ($recipient) {
            $recipient->last_response = $normalizedReply ?? $body;
            $recipient->last_response_at = Carbon::now();
            $recipient->provider_message_id = $recipient->provider_message_id ?: $messageId;
            $recipient->message_sid = $recipient->message_sid ?: $messageId;
            $recipient->status_payload = $payload;
            $recipient->provider_status_payload = $payload;
            if ($client && !$recipient->client_id) {
                $recipient->client_id = $client->id;
            }
            $recipient->save();
            $this->refreshWhatsappMessageCounts($recipient->message);
        }

        if ($client) {
            $session = ChatSession::firstOrCreate(
                ['client_id' => $client->id, 'platform' => 'whatsapp'],
                [
                    'client_name' => $client->name,
                    'phone' => $client->phone ?? $from,
                    'status' => 'active',
                    'unread_count' => 0,
                ]
            );
        } else {
            $session = ChatSession::firstOrCreate(
                ['client_name' => $from, 'platform' => 'whatsapp'],
                [
                    'phone' => $from,
                    'status' => 'active',
                    'unread_count' => 0,
                ]
            );
        }

        $session->messages()->create([
            'sender' => 'client',
            'content' => $body,
            'sent_at' => Carbon::now(),
        ]);

        $session->increment('unread_count');
        $session->update([
            'last_message' => $body,
            'updated_at' => now(),
            'client_id' => $session->client_id ?: ($client?->id ?? null),
            'client_name' => $client?->name ?? $session->client_name ?? $from,
            'phone' => $session->phone ?: ($client?->phone ?? $from),
        ]);
    }

    protected function mapStatus(string $status): string
    {
        return match ($status) {
            'delivered', 'read', 'sent' => 'Delivered',
            'failed' => 'Failed',
            default => 'Pending',
        };
    }

    protected function refreshWhatsappMessageCounts(?CampaignWhatsappMessage $message): void
    {
        if (!$message) {
            return;
        }

        $delivered = $message->recipients()->whereRaw('LOWER(status) = ?', ['delivered'])->count();
        $failed = $message->recipients()->whereRaw('LOWER(status) = ?', ['failed'])->count();
        $pending = $message->recipients()->whereNotIn('status', ['Delivered', 'Failed'])->count();

        $message->update([
            'delivered' => $delivered,
            'failed' => $failed,
            'pending' => $pending,
            'track_responses' => $message->track_responses,
            'enable_live_chat' => $message->enable_live_chat,
        ]);
    }

    protected function findClientByPhone(string $phone): ?Client
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if (!$digits) {
            return null;
        }

        return Client::query()
            ->where('phone', $phone)
            ->orWhereRaw("REPLACE(REPLACE(REPLACE(`phone`, '+', ''), ' ', ''), '-', '') = ?", [$digits])
            ->orWhereRaw("RIGHT(REPLACE(REPLACE(REPLACE(`phone`, '+', ''), ' ', ''), '-', ''), 9) = ?", [substr($digits, -9)])
            ->first();
    }

    protected function findRecipientByPhone(string $phone): ?CampaignWhatsappRecipient
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if (!$digits) {
            return null;
        }

        return CampaignWhatsappRecipient::query()
            ->where('phone', $phone)
            ->orWhereRaw("REPLACE(REPLACE(REPLACE(`phone`, '+', ''), ' ', ''), '-', '') = ?", [$digits])
            ->orWhereRaw("RIGHT(REPLACE(REPLACE(REPLACE(`phone`, '+', ''), ' ', ''), '-', ''), 9) = ?", [substr($digits, -9)])
            ->latest('id')
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
            '2', 'no', 'n' => 'no',
            default => $trimmed,
        };
    }
}
