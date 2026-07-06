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
use Illuminate\Support\Facades\Cache;
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
        $mode = $request->query('hub_mode') ?? $request->query('hub.mode');
        $token = $request->query('hub_verify_token') ?? $request->query('hub.verify_token');
        $challenge = $request->query('hub_challenge') ?? $request->query('hub.challenge');

        if (
            $mode === 'subscribe' &&
            hash_equals((string) $verifyToken, (string) $token)
        ) {
            return response($challenge, 200);
        }

        abort(403, 'Invalid webhook verification token.');
    }

    public function webhook(Request $request): JsonResponse
    {
        $this->validateWebhookSignature($request);

        $payload = $request->all();

        foreach (($payload['entry'] ?? []) as $entry) {
            foreach (($entry['changes'] ?? []) as $change) {
                $value = $change['value'] ?? [];

                foreach (($value['statuses'] ?? []) as $status) {
                    if ($this->isDuplicateStatusEvent($status)) {
                        continue;
                    }
                    $this->handleStatusUpdate($status, $value);
                }

                foreach (($value['messages'] ?? []) as $message) {
                    if ($this->isDuplicateInboundMessage($message)) {
                        continue;
                    }
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

        $phoneNumberId = $payload['metadata']['phone_number_id'] ?? null;
        $recipient = null;
        if ($messageId) {
            $recipient = CampaignWhatsappRecipient::where('provider_message_id', $messageId)
                ->orWhere('message_sid', $messageId)
                ->first();
        }

        if (!$recipient && $recipientPhone) {
            $recipient = $this->findRecipientByPhone($recipientPhone, $phoneNumberId);
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
        $recipient = $this->findRecipientByPhone($from, $phoneNumberId);
        $client = $this->findClientByPhone($from, $phoneNumberId);

        if (!$recipient && $client) {
            $recipient = CampaignWhatsappRecipient::where('client_id', $client->id)->latest('id')->first();
        }

        if (!$client && $recipient?->client) {
            $client = $recipient->client;
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

        if ($client && $this->isOptOutMessage($body)) {
            $client->markWhatsappOptOut($this->normalizeReply($body) ?? strtolower(trim($body)));
        }

        if ($client) {
            $session = ChatSession::firstOrCreate(
                ['client_id' => $client->id, 'platform' => 'whatsapp'],
                [
                    'client_name' => $client->name,
                    'phone' => $client->phone ?? $from,
                    'bank_id' => $client->bank_id,
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
            'bank_id' => $session->bank_id ?: ($client?->bank_id ?? null),
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
        $pending = $message->recipients()->whereNotIn('status', ['Delivered', 'Failed', 'Suppressed'])->count();

        $message->update([
            'delivered' => $delivered,
            'failed' => $failed,
            'pending' => $pending,
            'track_responses' => $message->track_responses,
            'enable_live_chat' => $message->enable_live_chat,
        ]);
    }

    protected function findClientByPhone(string $phone, ?string $phoneNumberId = null): ?Client
    {
        $clients = $this->candidateClientsForPhone($phone);
        if ($clients->isEmpty()) {
            return null;
        }

        if ($clients->count() === 1) {
            return $clients->first();
        }

        $recipient = $this->findRecipientByPhone($phone, $phoneNumberId);
        if ($recipient?->client) {
            return $recipient->client;
        }

        Log::warning('Ambiguous inbound client match by phone; client was not auto-linked.', [
            'phone' => $phone,
            'phone_number_id' => $phoneNumberId,
            'candidate_client_ids' => $clients->pluck('id')->all(),
            'candidate_bank_ids' => $clients->pluck('bank_id')->unique()->values()->all(),
        ]);

        return null;
    }

    protected function findRecipientByPhone(string $phone, ?string $phoneNumberId = null): ?CampaignWhatsappRecipient
    {
        $recipients = $this->candidateRecipientsForPhone($phone);
        if ($recipients->isEmpty()) {
            return null;
        }

        if ($recipients->count() === 1) {
            return $recipients->first();
        }

        if ($phoneNumberId) {
            $scopedByPhoneNumberId = $recipients
                ->filter(fn ($recipient) => (string) $recipient->provider_phone_number_id === (string) $phoneNumberId)
                ->values();

            if ($scopedByPhoneNumberId->count() === 1) {
                return $scopedByPhoneNumberId->first();
            }

            if ($scopedByPhoneNumberId->isNotEmpty()) {
                $recipients = $scopedByPhoneNumberId;
            }
        }

        $clientIds = $recipients->pluck('client_id')->filter()->unique()->values();
        if ($clientIds->count() === 1) {
            return $recipients->sortByDesc('id')->first();
        }

        $recentSingle = $recipients
            ->filter(fn ($recipient) => optional($recipient->created_at)->gte(now()->subDays(30)))
            ->pluck('client_id')
            ->filter()
            ->unique()
            ->values();

        if ($recentSingle->count() === 1) {
            return $recipients
                ->where('client_id', $recentSingle->first())
                ->sortByDesc('id')
                ->first();
        }

        Log::warning('Ambiguous inbound WhatsApp recipient match by phone; recipient was not auto-linked.', [
            'phone' => $phone,
            'phone_number_id' => $phoneNumberId,
            'candidate_recipient_ids' => $recipients->pluck('id')->all(),
            'candidate_client_ids' => $clientIds->all(),
        ]);

        return null;
    }

    protected function candidateClientsForPhone(string $phone)
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if (!$digits) {
            return collect();
        }

        return Client::query()
            ->where(function ($query) use ($phone, $digits) {
                $query->where('phone', $phone)
                    ->orWhereRaw("REPLACE(REPLACE(REPLACE(`phone`, '+', ''), ' ', ''), '-', '') = ?", [$digits])
                    ->orWhereRaw("RIGHT(REPLACE(REPLACE(REPLACE(`phone`, '+', ''), ' ', ''), '-', ''), 9) = ?", [substr($digits, -9)]);
            })
            ->orderByDesc('id')
            ->get();
    }

    protected function candidateRecipientsForPhone(string $phone)
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if (!$digits) {
            return collect();
        }

        return CampaignWhatsappRecipient::query()
            ->with('client')
            ->where(function ($query) use ($phone, $digits) {
                $query->where('phone', $phone)
                    ->orWhereRaw("REPLACE(REPLACE(REPLACE(`phone`, '+', ''), ' ', ''), '-', '') = ?", [$digits])
                    ->orWhereRaw("RIGHT(REPLACE(REPLACE(REPLACE(`phone`, '+', ''), ' ', ''), '-', ''), 9) = ?", [substr($digits, -9)]);
            })
            ->orderByDesc('id')
            ->get();
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

    protected function isOptOutMessage(string $body): bool
    {
        return in_array(strtolower(trim($body)), [
            'stop',
            'unsubscribe',
            'opt out',
            'optout',
            'cancel',
            'end',
            'quit',
        ], true);
    }

    protected function validateWebhookSignature(Request $request): void
    {
        $secret = method_exists($this->whatsApp, 'appSecret') ? $this->whatsApp->appSecret() : null;
        if (!$secret) {
            Log::warning('Meta webhook signature validation skipped because no app secret is configured.');
            return;
        }

        $signature = (string) $request->header('X-Hub-Signature-256', '');
        if (!str_starts_with($signature, 'sha256=')) {
            abort(403, 'Missing Meta webhook signature.');
        }

        $computed = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);
        if (!hash_equals($computed, $signature)) {
            Log::warning('Rejected Meta webhook with invalid signature.');
            abort(403, 'Invalid Meta webhook signature.');
        }
    }

    protected function isDuplicateInboundMessage(array $message): bool
    {
        $messageId = $message['id'] ?? null;
        if (!$messageId) {
            return false;
        }

        $key = 'meta_webhook_message:' . $messageId;
        if (Cache::has($key)) {
            return true;
        }

        Cache::put($key, true, now()->addDay());

        return false;
    }

    protected function isDuplicateStatusEvent(array $status): bool
    {
        $messageId = $status['id'] ?? 'unknown';
        $statusName = $status['status'] ?? 'unknown';
        $timestamp = $status['timestamp'] ?? 'unknown';
        $key = 'meta_webhook_status:' . sha1($messageId . '|' . $statusName . '|' . $timestamp);

        if (Cache::has($key)) {
            return true;
        }

        Cache::put($key, true, now()->addDay());

        return false;
    }
}
