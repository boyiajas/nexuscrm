<?php

namespace App\Http\Controllers\Api;

use App\Contracts\WhatsAppServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\CampaignClient;
use App\Models\CampaignWhatsappMessage;
use App\Models\CampaignWhatsappRecipient;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Client;
use App\Models\WhatsAppFlow;
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

            // Campaign receipts remain on their existing hot path. Only look in
            // live chat when the provider ID did not match a campaign message.
            if (!$recipient) {
                $chatMessage = ChatMessage::query()->where('provider_message_id', $messageId)->first();
                if ($chatMessage) {
                    $this->updateChatMessageStatus($chatMessage, $statusName, $status);
                    return;
                }
            }
        }

        // A status with its own Meta ID must never be attributed to an
        // unrelated campaign message solely because the phone matches.
        if (!$recipient && !$messageId && $recipientPhone) {
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

        $isEcosystemWarning = (string)$errorCode === '131049'
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

    protected function updateChatMessageStatus(ChatMessage $message, string $statusName, array $status): void
    {
        // Webhooks may arrive out of order. Only advance the visible receipt;
        // a late "delivered" event must never replace an existing "read".
        $previousStatuses = match ($statusName) {
            'sent' => ['pending', 'accepted', 'unknown'],
            'delivered' => ['pending', 'accepted', 'unknown', 'sent', 'failed'],
            'read' => ['pending', 'accepted', 'unknown', 'sent', 'delivered', 'failed'],
            'failed' => ['pending', 'accepted', 'unknown', 'sent'],
            default => null,
        };

        if ($previousStatuses === null) {
            return;
        }

        $statusAt = is_numeric($status['timestamp'] ?? null)
            ? Carbon::createFromTimestamp((int) $status['timestamp'], 'UTC')
            : now();

        [$errorCode, $errorMessage] = $this->chatDeliveryError($status);

        ChatMessage::query()
            ->whereKey($message->id)
            ->where(function ($query) use ($previousStatuses) {
                $query->whereNull('delivery_status')->orWhereIn('delivery_status', $previousStatuses);
            })
            ->update([
                'delivery_status' => $statusName,
                'delivery_status_at' => $statusAt,
                'delivery_error_code' => $statusName === 'failed' ? $errorCode : null,
                'delivery_error_message' => $statusName === 'failed' ? $errorMessage : null,
            ]);
    }

    /**
     * Extract the useful failure information from a Meta status webhook.
     * Meta may put the customer-facing reason in title, message, or error_data.details.
     */
    protected function chatDeliveryError(array $status): array
    {
        $error = $status['errors'][0] ?? null;
        if (!is_array($error)) {
            return [null, null];
        }

        $parts = [];
        foreach ([
            $error['title'] ?? null,
            $error['message'] ?? null,
            $error['error_data']['details'] ?? null,
        ] as $part) {
            $part = trim((string) $part);
            if ($part !== '' && !in_array($part, $parts, true)) {
                $parts[] = $part;
            }
        }

        return [
            isset($error['code']) ? (string) $error['code'] : null,
            $parts !== [] ? implode(' — ', $parts) : null,
        ];
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
        $contextId = $message['context']['id'] ?? null;
        $recipient = null;
        if (!empty($contextId)) {
            $recipient = CampaignWhatsappRecipient::where('provider_message_id', $contextId)
                ->orWhere('message_sid', $contextId)
                ->first();
        }

        if (!$recipient) {
            $recipient = $this->findRecipientByPhone($from, $phoneNumberId);
        }
        $client = $this->findClientByPhone($from, $phoneNumberId);

        if (!$recipient && $client) {
            $recipient = CampaignWhatsappRecipient::where('client_id', $client->id)->latest('id')->first();
        }

        if (!$client && $recipient?->client) {
            $client = $recipient->client;
        }

        $messageBatch = $recipient?->message;
        $isFlow = ($messageBatch?->mode === 'flow' || !empty($messageBatch?->whatsapp_flow_id));
        $shouldTrackResponse = (bool) ($messageBatch?->track_responses ?? false) || $isFlow;
        $shouldOpenLiveChat = !$messageBatch || (bool) ($messageBatch->enable_live_chat ?? false);
        $isOptOut = $this->isOptOutMessage($body, $reply['keywords']);
        $sentFlowReply = null;

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

            // Strictly only send automated messages if the message was sent from the Flow tab or has a flow attached
            if ($recipient && !$isOptOut && $isFlow) {
                $flowDef = $messageBatch?->flow_definition ?? [];
                if (is_string($flowDef)) {
                    $flowDef = json_decode($flowDef, true) ?? [];
                }
                if (empty($flowDef) && $messageBatch?->whatsapp_flow_id) {
                    $flow = \App\Models\WhatsAppFlow::find($messageBatch->whatsapp_flow_id);
                    $flowDef = $flow?->flow_definition ?? [];
                    if (is_string($flowDef)) {
                        $flowDef = json_decode($flowDef, true) ?? [];
                    }
                }

                $currentStepId = $recipient->current_flow_step_id;
                $stepToSend = null;
                $nextStepId = null;

                if (empty($currentStepId)) {
                    $initialExpectedReplies = $messageBatch?->initial_expected_replies;
                    if ($initialExpectedReplies === null && $messageBatch?->whatsapp_flow_id) {
                        $initialExpectedReplies = WhatsAppFlow::find($messageBatch->whatsapp_flow_id)
                            ?->initial_expected_replies;
                    }
                    $initialExpectedReplies = array_values(array_filter(
                        is_array($initialExpectedReplies) ? $initialExpectedReplies : [],
                        fn ($value) => is_scalar($value) && trim((string) $value) !== ''
                    ));
                    $replyMatches = empty($initialExpectedReplies)
                        || $this->replyMatchesExpectedValues(
                            $initialExpectedReplies,
                            array_merge([$body], $reply['keywords'])
                        );

                    if (!$replyMatches) {
                        Log::info('WhatsApp flow initial reply did not match the expected values.', [
                            'from' => $from,
                            'recipient_id' => $recipient->id,
                            'expected_replies' => $initialExpectedReplies,
                            'received_reply' => $body,
                        ]);
                    } else {
                        $stepToSend = $flowDef[0] ?? null;
                        if ($stepToSend) {
                            $nextStepId = $stepToSend['id'] ?? 'greeting';
                        }
                    }
                } else {
                    // Subsequent reply: Advance according to decision or linear sequence
                    $currentStep = collect($flowDef)->firstWhere('id', $currentStepId);
                    if ($currentStep) {
                        $expectedReplies = array_values(array_filter(
                            is_array($currentStep['expected_replies'] ?? null)
                                ? $currentStep['expected_replies']
                                : [],
                            fn ($value) => is_scalar($value) && trim((string) $value) !== ''
                        ));
                        $replyMatches = empty($expectedReplies)
                            || $this->replyMatchesExpectedValues($expectedReplies, array_merge([$body], $reply['keywords']));

                        if (!$replyMatches) {
                            Log::info('WhatsApp flow reply did not match the expected values.', [
                                'from' => $from,
                                'recipient_id' => $recipient->id,
                                'current_step_id' => $currentStepId,
                                'expected_replies' => $expectedReplies,
                                'received_reply' => $body,
                            ]);
                        } elseif (!empty($currentStep['decision'])) {
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
                            $stepToSend = collect($flowDef)->firstWhere('id', $nextStepId);
                        }
                    }
                }

                Log::info('Evaluating WhatsApp flow auto-responder', [
                    'from' => $from,
                    'recipient_id' => $recipient->id,
                    'batch_id' => $messageBatch?->id,
                    'mode' => $messageBatch?->mode,
                    'whatsapp_flow_id' => $messageBatch?->whatsapp_flow_id,
                    'is_flow' => $isFlow,
                    'flow_def_count' => is_countable($flowDef) ? count($flowDef) : 0,
                    'current_step_id' => $currentStepId,
                    'next_step_id' => $nextStepId,
                    'reply_type' => $stepToSend['reply_type'] ?? 'message',
                    'step_message' => $stepToSend['message'] ?? null,
                    'step_template' => $stepToSend['template_sid'] ?? null,
                ]);

                $replyType = $stepToSend['reply_type'] ?? 'message';
                $hasReply = $stepToSend && (
                    ($replyType === 'template' && !empty($stepToSend['template_sid']))
                    || ($replyType === 'message' && !empty($stepToSend['message']))
                );

                if ($hasReply) {
                    try {
                        // Resolve sender phone number: prioritize metadata from incoming webhook (guaranteed valid recipient number),
                        // followed by batch provider number, ensuring WABA ID is never used as sender number.
                        $wabaIdFromEntry = (string) ($payload['entry'][0]['id'] ?? '');
                        $metaPhoneId = $payload['metadata']['phone_number_id'] ?? null;
                        $metaDisplayNumber = $payload['metadata']['display_phone_number'] ?? null;

                        $candidateSenders = array_filter([
                            $metaPhoneId,
                            $metaDisplayNumber,
                            $messageBatch?->provider_display_phone_number,
                            $messageBatch?->provider_phone_number_id,
                            $messageBatch?->campaign?->whatsapp_from,
                        ], fn ($val) => !empty($val) && (string)$val !== $wabaIdFromEntry);

                        $senderNumber = reset($candidateSenders) ?: null;

                        $whatsAppService = app(WhatsAppServiceInterface::class);
                        if ($replyType === 'template') {
                            $campaign = $messageBatch?->campaign;
                            if (!$campaign) {
                                throw new \RuntimeException('The campaign for this WhatsApp flow could not be loaded.');
                            }

                            $resolvedVariables = app(WhatsAppBatchService::class)->resolveTemplateVariableValues(
                                $stepToSend['template_variables'] ?? [],
                                $client,
                                $campaign
                            );
                            $sendResult = $whatsAppService->sendTemplateFromSubjectMessage(
                                $from,
                                $stepToSend['template_sid'],
                                '',
                                '',
                                $resolvedVariables,
                                $senderNumber
                            );
                            $sentContent = $this->renderFlowTemplateMessage($stepToSend, $resolvedVariables);
                        } else {
                            $sendResult = method_exists($whatsAppService, 'sendTextMessage')
                                ? $whatsAppService->sendTextMessage($from, $stepToSend['message'], $senderNumber)
                                : $whatsAppService->sendPlainWhatsapp($from, $stepToSend['message'], $senderNumber);
                            $sentContent = $stepToSend['message'];
                        }

                        $recipient->current_flow_step_id = $nextStepId;
                        $recipient->save();

                        $providerMessageId = $sendResult['message_id']
                            ?? $sendResult['sid']
                            ?? data_get($sendResult, 'messages.0.id')
                            ?? data_get($sendResult, 'raw.messages.0.id');
                        $sentFlowReply = [
                            'content' => $sentContent,
                            'is_template' => $replyType === 'template',
                            'provider_message_id' => $providerMessageId,
                            'delivery_status' => $providerMessageId ? 'accepted' : 'unknown',
                        ];

                        Log::info('Meta WhatsApp flow step sent.', [
                            'from' => $from,
                            'recipient_id' => $recipient->id,
                            'step_id' => $nextStepId,
                            'reply_type' => $replyType,
                            'template_sid' => $stepToSend['template_sid'] ?? null,
                            'provider_message_id' => $providerMessageId,
                            'sender' => $senderNumber,
                            'is_greeting' => empty($currentStepId),
                        ]);
                    } catch (\Throwable $e) {
                        Log::error('Failed to send flow message.', [
                            'error' => $e->getMessage(),
                            'phone' => $from,
                            'step_id' => $nextStepId,
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

        $matchedBank = app(\App\Services\BankWabaResolver::class)->resolveBankForSender($phoneNumberId, $from);
        $wabaBankId = $matchedBank?->id;
        if (!$wabaBankId && $phoneNumberId) {
            $wabaBankId = \App\Models\WhatsappAccount::where('phone_number_id', $phoneNumberId)->value('bank_id');
        }
        $resolvedSessionBankId = $client?->bank_id ?: $wabaBankId;

        if ($client) {
            $session = ChatSession::firstOrCreate(
                ['client_id' => $client->id, 'platform' => 'whatsapp'],
                [
                    'client_name' => $client->name,
                    'phone' => $client->phone ?? $from,
                    'bank_id' => $resolvedSessionBankId,
                    'status' => 'active',
                    'unread_count' => 0,
                    'waba_phone_number_id' => $phoneNumberId,
                ]
            );
        } else {
            $session = ChatSession::firstOrCreate(
                ['client_name' => $from, 'platform' => 'whatsapp'],
                [
                    'phone' => $from,
                    'bank_id' => $resolvedSessionBankId,
                    'status' => 'active',
                    'unread_count' => 0,
                    'waba_phone_number_id' => $phoneNumberId,
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
            'bank_id' => $resolvedSessionBankId ?: $session->bank_id,
            'status' => 'active',
            'waba_phone_number_id' => $phoneNumberId ?: $session->waba_phone_number_id,
        ]);

        if ($sentFlowReply) {
            $session->messages()->create([
                'sender' => 'agent',
                'content' => $sentFlowReply['content'],
                'is_template' => $sentFlowReply['is_template'],
                'provider_message_id' => $sentFlowReply['provider_message_id'],
                'delivery_status' => $sentFlowReply['delivery_status'],
                'delivery_status_at' => now(),
                'sent_at' => Carbon::now(),
            ]);
            $session->update([
                'last_message' => $sentFlowReply['content'],
                'updated_at' => now(),
            ]);
        }

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

    protected function renderFlowTemplateMessage(array $step, array $variables): string
    {
        $render = static function (?string $text, string $prefix) use ($variables): string {
            return preg_replace_callback(
                '/{{(\d+)}}/',
                static fn (array $match) => $variables["{$prefix}_{$match[1]}"] ?? $match[0],
                (string) $text
            );
        };

        $parts = array_filter([
            $render($step['template_header_text'] ?? null, 'header'),
            $render($step['template_preview'] ?? null, 'body'),
            trim((string) ($step['template_footer_text'] ?? '')),
        ], static fn (string $part) => trim($part) !== '');

        return $parts
            ? implode("\n", $parts)
            : 'Template: ' . ($step['template_name'] ?? $step['template_sid'] ?? 'WhatsApp template');
    }

    protected function refreshWhatsappMessageCounts(?CampaignWhatsappMessage $message): void
    {
        if (!$message) {
            return;
        }

        \App\Jobs\SyncCampaignWhatsappMessageProgressJob::dispatch($message->id);
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
                    ->orWhereRaw("REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', '') = ?", [$digits])
                    ->orWhereRaw("SUBSTR(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), -9) = ?", [substr($digits, -9)]);
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
                    ->orWhereRaw("REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', '') = ?", [$digits])
                    ->orWhereRaw("SUBSTR(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), -9) = ?", [substr($digits, -9)]);
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

    protected function replyMatchesExpectedValues(array $expectedReplies, array $receivedValues): bool
    {
        $variants = function (array $values): array {
            return collect($values)
                ->flatMap(function ($value) {
                    $normalized = $this->normalizeExpectedReplyValue((string) $value);
                    if ($normalized === null) {
                        return [];
                    }

                    $decisionAlias = match ($normalized) {
                        '1', 'yes', 'y' => 'yes',
                        '2', 'no', 'n' => 'no',
                        default => $normalized,
                    };

                    return array_values(array_unique([$normalized, $decisionAlias]));
                })
                ->unique()
                ->values()
                ->all();
        };

        return count(array_intersect($variants($expectedReplies), $variants($receivedValues))) > 0;
    }

    protected function normalizeExpectedReplyValue(string $value): ?string
    {
        $normalized = mb_strtolower(trim($value));
        $normalized = preg_replace('/[_-]+/u', ' ', $normalized);
        $normalized = preg_replace('/[^\pL\pN]+/u', ' ', $normalized);
        $normalized = trim((string) preg_replace('/\s+/u', ' ', (string) $normalized));

        return $normalized === '' ? null : $normalized;
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
                $activeBatches = \App\Models\CampaignWhatsappMessage::whereIn('status', ['Queued', 'Processing'])->get();
                $batchService = app(\App\Services\WhatsAppBatchService::class);

                foreach ($activeBatches as $batch) {
                    $batchService->pauseMessage($batch, 'Auto-paused due to Meta Account Alert.');
                    Log::critical("Auto-paused WhatsApp Batch #{$batch->id} due to Meta Account Alert.");
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
