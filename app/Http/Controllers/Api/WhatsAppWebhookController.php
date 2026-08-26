<?php

namespace App\Http\Controllers\Api;

use App\Contracts\WhatsAppServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\CampaignClient;
use App\Models\CampaignWhatsappMessage;
use App\Models\CampaignWhatsappRecipient;
use App\Models\ChatSession;
use App\Models\Client;
use App\Mail\WhatsAppInboundReplyNotification;
use App\Services\MetaWhatsAppService;
use App\Services\WhatsAppBatchService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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
        Log::info('Raw WhatsApp Webhook Payload:', $payload);

        foreach (($payload['entry'] ?? []) as $entry) {
            foreach (($entry['changes'] ?? []) as $change) {
                $value = $change['value'] ?? [];
                $field = $change['field'] ?? '';

                if (in_array($field, ['account_update', 'phone_number_quality_update', 'message_template_quality_update'])) {
                    $this->handleAccountAlert($field, $value, (string) ($entry['id'] ?? 'unknown'));
                    continue;
                }

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
        $errorCode = $status['errors'][0]['code'] ?? null;
        $errorTitle = $status['errors'][0]['title'] ?? $status['errors'][0]['message'] ?? '';

        $isEcosystemWarning = in_array((string)$errorCode, ['131049', '131026'], true)
            || str_contains(strtolower((string)$errorTitle), 'maintain healthy ecosystem engagement');

        if ($isEcosystemWarning) {
            $mappedStatus = 'Delivered';
        }

        $recipient->status = $mappedStatus;
        $recipient->message_sid = $messageId ?: $recipient->message_sid;
        $recipient->provider_message_id = $messageId ?: $recipient->provider_message_id;
        $recipient->status_payload = $payload;
        $recipient->provider_status_payload = $payload;

        if ($mappedStatus === 'Delivered') {
            $recipient->delivered_at = $recipient->delivered_at ?: Carbon::now();
        }

        if ($errorCode || $errorTitle) {
            $recipient->error_code = $errorCode ?: $recipient->error_code;
            $recipient->error_message = $errorTitle ?: $recipient->error_message;
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
        $phoneNumberId = $payload['metadata']['phone_number_id'] ?? null;
        $reply = $this->extractInboundReply($message);
        $body = $reply['display_text'];
        $messageId = $message['id'] ?? null;

        if (!$from || $body === '') {
            return;
        }

        $normalizedReply = $this->normalizeReply($body);
        foreach ($reply['keywords'] as $candidate) {
            $candidateReply = $this->normalizeReply((string) $candidate);
            if (in_array($candidateReply, ['yes', 'no'], true)) {
                $normalizedReply = $candidateReply;
                break;
            }
        }
        $recipient = $this->findRecipientByPhone($from, $phoneNumberId);
        $client = $this->findClientByPhone($from, $phoneNumberId);

        if (!$recipient && $client) {
            $recipient = CampaignWhatsappRecipient::where('client_id', $client->id)->latest('id')->first();
        }

        if (!$client && $recipient?->client) {
            $client = $recipient->client;
        }

        $messageBatch = $recipient?->message;
        $shouldTrackResponse = (bool) ($messageBatch?->track_responses ?? false);
        $shouldOpenLiveChat = !$messageBatch || (bool) ($messageBatch->enable_live_chat ?? false);
        $isOptOut = $this->isOptOutMessage($body, $reply['keywords']);

        if ($recipient) {
            $recipient->provider_message_id = $recipient->provider_message_id ?: $messageId;
            $recipient->message_sid = $recipient->message_sid ?: $messageId;
            $recipient->status_payload = $payload;
            $recipient->provider_status_payload = $payload;
            if ($client && !$recipient->client_id) {
                $recipient->client_id = $client->id;
            }

            if ($shouldTrackResponse || $isOptOut) {
                $recipient->last_response = $normalizedReply ?? strtolower(trim($body));
                $recipient->last_response_at = Carbon::now();
            }

            $recipient->save();
            $this->refreshWhatsappMessageCounts($recipient->message);

            if ($messageBatch && $messageBatch->mode === 'flow' && $recipient->last_response) {
                $flowDef = $messageBatch->flow_definition ?? [];
                $currentStepId = $recipient->current_flow_step_id;
                
                $currentStep = collect($flowDef)->firstWhere('id', $currentStepId);
                if (!$currentStep && !empty($flowDef)) {
                    $currentStep = $flowDef[0];
                }
                
                if ($currentStep) {
                    $nextStepId = null;
                    if (!empty($currentStep['decision'])) {
                        if ($normalizedReply === 'yes') {
                            $nextStepId = $currentStep['yesNextId'] ?? null;
                        } elseif ($normalizedReply === 'no') {
                            $nextStepId = $currentStep['noNextId'] ?? null;
                        }
                    } else {
                        // Linear progression
                        $currentIndex = collect($flowDef)->search(fn($s) => $s['id'] === $currentStep['id']);
                        if ($currentIndex !== false && isset($flowDef[$currentIndex + 1])) {
                            $nextStepId = $flowDef[$currentIndex + 1]['id'];
                        }
                    }
                    
                    if ($nextStepId) {
                        $nextStep = collect($flowDef)->firstWhere('id', $nextStepId);
                        if ($nextStep && !empty($nextStep['message'])) {
                            try {
                                app(MetaWhatsAppService::class)->sendTextMessage(
                                    $from,
                                    $nextStep['message'],
                                    $messageBatch->provider_display_phone_number
                                );
                                
                                $recipient->current_flow_step_id = $nextStepId;
                                $recipient->save();
                                
                                Log::info('Meta WhatsApp flow step advanced.', [
                                    'from' => $from,
                                    'recipient_id' => $recipient->id,
                                    'next_step_id' => $nextStepId,
                                ]);
                            } catch (\Throwable $e) {
                                Log::error('Failed to send flow next step message.', [
                                    'error' => $e->getMessage(),
                                    'phone' => $from,
                                ]);
                            }
                        }
                    }
                }
            } elseif ($messageBatch && $recipient->last_response) {
                $autoReply = $messageBatch->autoReplies()
                    ->where('trigger_keyword', strtolower($recipient->last_response))
                    ->first();

                if ($autoReply) {
                    try {
                        app(MetaWhatsAppService::class)->sendTemplateFromSubjectMessage(
                            $from,
                            $autoReply->template_sid,
                            '',
                            '',
                            $autoReply->template_variables ?? [],
                            $messageBatch->provider_display_phone_number
                        );
                        
                        Log::info('Meta WhatsApp auto-reply template sent.', [
                            'from' => $from,
                            'client_id' => $client?->id,
                            'recipient_id' => $recipient->id,
                            'template_sid' => $autoReply->template_sid,
                        ]);
                    } catch (\Throwable $e) {
                        Log::error('Failed to send auto-reply template.', [
                            'error' => $e->getMessage(),
                            'client_id' => $client?->id,
                            'phone' => $from,
                            'template_sid' => $autoReply->template_sid,
                        ]);
                    }
                }
            }
        }

        if ($client) {
            if ($isOptOut) {
                $client->setOptIn('no', strtolower(trim($body)));
            } else {
                $client->setOptIn('yes', 'inbound_reply');
            }
        }

        $this->sendInboundReplyNotificationEmail($messageBatch, $client, $recipient, $body, $from);

        if (!$shouldOpenLiveChat) {
            Log::info('Meta WhatsApp reply tracked without opening live chat session.', [
                'from' => $from,
                'client_id' => $client?->id,
                'recipient_id' => $recipient?->id,
                'message_id' => $messageId,
                'message_type' => $reply['message_type'],
                'interactive_type' => $reply['interactive_type'],
                'normalized_reply' => $normalizedReply,
            ]);

            return;
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
            'media_url' => $reply['media_url'] ?? null,
            'media_type' => $reply['media_type'] ?? null,
            'sent_at' => Carbon::now(),
        ]);

        $session->increment('unread_count');
        $session->update([
            'last_message' => $body,
            'updated_at' => now(),
            'client_id' => $client?->id ?: $session->client_id,
            'client_name' => $client?->name ?? $session->client_name ?? $from,
            'phone' => $session->phone ?: ($client?->phone ?? $from),
            'bank_id' => $client?->bank_id ?: $session->bank_id,
            'status' => 'active',
        ]);

        Log::info('Meta WhatsApp inbound reply routed to live chat.', [
            'from' => $from,
            'client_id' => $client?->id,
            'recipient_id' => $recipient?->id,
            'message_id' => $messageId,
            'message_type' => $reply['message_type'],
            'interactive_type' => $reply['interactive_type'],
            'normalized_reply' => $normalizedReply,
            'session_id' => $session->id,
        ]);
    }

    protected function mapStatus(string $status): string
    {
        return match ($status) {
            'delivered', 'read' => 'Delivered',
            'sent'              => 'Sent',
            'failed'            => 'Failed',
            default             => 'Pending',
        };
    }

    protected function refreshWhatsappMessageCounts(?CampaignWhatsappMessage $message): void
    {
        if (!$message) {
            return;
        }

        app(WhatsAppBatchService::class)->syncMessageProgress($message);
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

        Log::warning('Ambiguous inbound client match by phone; picking the most recently updated client.', [
            'phone' => $phone,
            'phone_number_id' => $phoneNumberId,
            'candidate_client_ids' => $clients->pluck('id')->all(),
            'candidate_bank_ids' => $clients->pluck('bank_id')->unique()->values()->all(),
        ]);

        return $clients->sortByDesc('updated_at')->first();
    }

    protected function findRecipientByPhone(string $phone, ?string $phoneNumberId = null): ?CampaignWhatsappRecipient
    {
        $recipients = $this->candidateRecipientsForPhone($phone);
        if ($recipients->isEmpty()) {
            return null;
        }

        if ($phoneNumberId) {
            $scopedByPhoneNumberId = $recipients
                ->filter(fn ($recipient) => (string) $recipient->provider_phone_number_id === (string) $phoneNumberId)
                ->values();

            if ($scopedByPhoneNumberId->isNotEmpty()) {
                $recipients = $scopedByPhoneNumberId;
            }
        }

        // Just pick the most recent recipient overall to avoid dropping the link
        return $recipients->sortByDesc('id')->first();
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

    protected function isOptOutMessage(string $body, array $keywords = []): bool
    {
        $phrases = array_filter(array_map(
            fn ($value) => strtolower(trim((string) $value)),
            array_merge([$body], $keywords)
        ));

        $optOutTriggers = [
            'stop',
            'unsubscribe',
            'opt out',
            'optout',
            'opt-out',
            'opt_out',
            'cancel',
            'end',
            'quit',
        ];

        foreach ($phrases as $phrase) {
            if (in_array($phrase, $optOutTriggers, true)) {
                return true;
            }
        }

        return false;
    }

    protected function extractInboundReply(array $message): array
    {
        $messageType = strtolower((string) ($message['type'] ?? 'text'));
        $interactiveType = strtolower((string) ($message['interactive']['type'] ?? ''));

        $textBody = trim((string) ($message['text']['body'] ?? ''));
        $buttonText = trim((string) ($message['button']['text'] ?? ''));
        $buttonPayload = trim((string) ($message['button']['payload'] ?? ''));
        $interactiveButtonTitle = trim((string) ($message['interactive']['button_reply']['title'] ?? ''));
        $interactiveButtonId = trim((string) ($message['interactive']['button_reply']['id'] ?? ''));
        $interactiveListTitle = trim((string) ($message['interactive']['list_reply']['title'] ?? ''));
        $interactiveListId = trim((string) ($message['interactive']['list_reply']['id'] ?? ''));

        $mediaUrl = null;
        $mediaType = null;

        if (in_array($messageType, ['image', 'document', 'audio', 'video', 'sticker'], true)) {
            $mediaData = $message[$messageType] ?? [];
            $mediaId = $mediaData['id'] ?? null;
            $caption = trim((string) ($mediaData['caption'] ?? ''));
            $filename = trim((string) ($mediaData['filename'] ?? ''));
            $mediaType = $messageType;

            if ($mediaId) {
                try {
                    $downloaded = app(MetaWhatsAppService::class)->downloadMedia($mediaId);
                    if ($downloaded && !empty($downloaded['content'])) {
                        $extMap = [
                            'image/jpeg' => 'jpg',
                            'image/png' => 'png',
                            'image/webp' => 'webp',
                            'audio/ogg' => 'ogg',
                            'audio/mpeg' => 'mp3',
                            'video/mp4' => 'mp4',
                            'application/pdf' => 'pdf',
                        ];
                        $ext = $extMap[$downloaded['mime_type']] ?? (pathinfo($filename, PATHINFO_EXTENSION) ?: 'bin');
                        $localPath = 'whatsapp_media/' . uniqid('wa_') . '.' . $ext;
                        \Illuminate\Support\Facades\Storage::disk('public')->put($localPath, $downloaded['content']);
                        $mediaUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($localPath);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Could not download inbound Meta WhatsApp media locally', ['media_id' => $mediaId, 'error' => $e->getMessage()]);
                }
            }

            if ($messageType === 'image') {
                $textBody = $caption !== '' ? "[📷 Image] {$caption}" : '[📷 Image Attachment]';
            } elseif ($messageType === 'document') {
                $docLabel = $filename ?: 'Document';
                $textBody = $caption !== '' ? "[📄 {$docLabel}] {$caption}" : "[📄 {$docLabel}]";
            } elseif ($messageType === 'audio') {
                $textBody = '[🎵 Audio Message]';
            } elseif ($messageType === 'video') {
                $textBody = $caption !== '' ? "[🎥 Video] {$caption}" : '[🎥 Video Attachment]';
            } elseif ($messageType === 'sticker') {
                $textBody = '[🎨 Sticker]';
            }
        }

        $displayText = collect([
            $interactiveButtonTitle,
            $buttonText,
            $interactiveListTitle,
            $textBody,
            $interactiveButtonId,
            $buttonPayload,
            $interactiveListId,
        ])->first(fn ($value) => trim((string) $value) !== '') ?? '';

        return [
            'display_text' => trim((string) $displayText),
            'message_type' => $messageType,
            'interactive_type' => $interactiveType !== '' ? $interactiveType : null,
            'media_url' => $mediaUrl,
            'media_type' => $mediaType,
            'keywords' => array_values(array_filter([
                $interactiveButtonTitle,
                $interactiveButtonId,
                $buttonText,
                $buttonPayload,
                $interactiveListTitle,
                $interactiveListId,
                $textBody,
            ], fn ($value) => trim((string) $value) !== '')),
        ];
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

    protected function sendInboundReplyNotificationEmail(?CampaignWhatsappMessage $messageBatch, ?Client $client, ?CampaignWhatsappRecipient $recipient, string $body, string $from): void
    {
        try {
            if ($messageBatch && isset($messageBatch->enable_email_notification) && !$messageBatch->enable_email_notification) {
                Log::info('WhatsApp inbound reply email notification skipped (disabled for batch).', [
                    'message_batch_id' => $messageBatch->id,
                    'from' => $from,
                ]);
                return;
            }

            $targetUser = $messageBatch?->createdBy
                ?: $messageBatch?->campaign?->user
                ?: $client?->assignedTo
                ?: \App\Models\User::where('role', 'admin')->first()
                ?: \App\Models\User::first();

            if ($targetUser && $targetUser->email) {
                Mail::to($targetUser->email)->send(
                    new WhatsAppInboundReplyNotification(
                        $targetUser,
                        $client,
                        $recipient,
                        $messageBatch,
                        $body,
                        $from
                    )
                );

                Log::info('WhatsApp inbound reply email notification dispatched.', [
                    'target_user_id' => $targetUser->id,
                    'target_email'   => $targetUser->email,
                    'client_id'      => $client?->id,
                    'from'           => $from,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch WhatsApp inbound reply email notification.', [
                'error'     => $e->getMessage(),
                'client_id' => $client?->id,
                'from'      => $from,
            ]);
        }
    }

    protected function handleAccountAlert(string $field, array $value, string $wabaId): void
    {
        Log::critical("Meta WhatsApp Account Alert Received: {$field}", ['waba_id' => $wabaId, 'payload' => $value]);

        $event = $value['event'] ?? 'UNKNOWN_EVENT';
        $reason = $value['reason'] ?? '';
        $phoneOrAccount = $value['display_phone_number'] ?? 'WABA: ' . $wabaId;
        
        $alertType = strtoupper($field) . ' - ' . $event;
        $eventDetails = "Event: {$event}\nReason: {$reason}";

        if (in_array($event, ['RESTRICTED', 'DISABLED', 'FLAGGED', 'DOWNGRADED'])) {
            // Auto-pause active campaigns
            try {
                $activeCampaigns = \App\Models\Campaign::where('type', 'whatsapp')
                    ->whereIn('status', ['running', 'processing'])
                    ->get();

                foreach ($activeCampaigns as $campaign) {
                    $campaign->update(['status' => 'paused']);
                    Log::critical("Auto-paused WhatsApp Campaign #{$campaign->id} due to Meta Account Alert.");
                }
            } catch (\Exception $e) {
                Log::error('Failed to auto-pause campaigns on Meta alert: ' . $e->getMessage());
            }

            // Send Email to Admins
            try {
                $admins = \App\Models\User::where('role', 'admin')->orWhere('role', 'super_admin')->get();
                foreach ($admins as $admin) {
                    Mail::to($admin->email)->send(
                        new \App\Mail\WhatsAppAccountAlertNotification($alertType, $wabaId, $phoneOrAccount, $eventDetails)
                    );
                }
            } catch (\Exception $e) {
                Log::error('Failed to send Meta account alert email: ' . $e->getMessage());
            }
        }
    }
}
