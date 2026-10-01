<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\CampaignWhatsappRecipient;
use App\Models\Client;

class CampaignWhatsappReportService
{
    /**
     * Build campaign-wide totals using client IDs as the counting key. This
     * prevents a client being counted more than once across multiple sends.
     */
    public function build(Campaign $campaign, ?callable $scopeRecipients = null): array
    {
        $recipientQuery = CampaignWhatsappRecipient::query()
            ->whereIn('whatsapp_message_id', $campaign->whatsappMessages()->select('id'));

        if ($scopeRecipients) {
            $scopeRecipients($recipientQuery);
        }

        $repliedClientIds = [];
        $quickReplyClientIds = [];
        $optOutClientIds = [];
        $sentClientIds = [];
        $messagesSent = 0;
        $messagesAccepted = 0;
        $messagesDeliveredRead = 0;
        $messagesDeliveredUnread = 0;
        $messagesFailed = 0;

        $recipientQuery->select([
            'id',
            'client_id',
            'last_response',
            'reply_type',
            'reply_label',
            'reply_key',
            'reply_source',
            'status',
            'error_code',
            'error_message',
            'status_payload',
            'provider_status_payload',
        ])->chunkById(500, function ($recipients) use (&$repliedClientIds, &$quickReplyClientIds, &$optOutClientIds, &$sentClientIds, &$messagesSent, &$messagesAccepted, &$messagesDeliveredRead, &$messagesDeliveredUnread, &$messagesFailed) {
            foreach ($recipients as $recipient) {
                $clientId = (int) $recipient->client_id;
                if ($clientId <= 0) {
                    continue;
                }

                if ($this->wasAttempted($recipient)) {
                    $messagesSent++;
                }
                if ($this->wasSent($recipient)) {
                    $sentClientIds[$clientId] = true;
                    $messagesAccepted++;
                }
                if ($this->wasDeliveredRead($recipient)) {
                    $messagesDeliveredRead++;
                } elseif ($this->wasDeliveredUnread($recipient)) {
                    $messagesDeliveredUnread++;
                }
                if ($this->wasFailed($recipient)) {
                    $messagesFailed++;
                }

                $replyMeta = $this->replyMeta($recipient);

                if (!$replyMeta['type'] && trim((string) $recipient->last_response) === '') {
                    continue;
                }

                $repliedClientIds[$clientId] = true;

                if (
                    $replyMeta['type'] === 'Quick Reply'
                    || in_array($replyMeta['source'], ['interactive.button_reply', 'button'], true)
                ) {
                    $quickReplyClientIds[$clientId] = true;
                }

                if ($replyMeta['type'] === 'Opt Out') {
                    $optOutClientIds[$clientId] = true;
                }
            }
        });

        $sentClients = Client::query()
            ->whereIn('id', array_keys($sentClientIds))
            ->get(['id', 'payment_option']);

        $paymentOptions = [
            'ptp' => $sentClients->where('payment_option', Client::PAYMENT_OPTION_PTP)->count(),
            'debit_order' => $sentClients->where('payment_option', Client::PAYMENT_OPTION_DEBIT_ORDER)->count(),
        ];
        $paymentOptions['total_selected'] = $paymentOptions['ptp'] + $paymentOptions['debit_order'];
        $paymentOptions['not_set'] = max($sentClients->count() - $paymentOptions['total_selected'], 0);

        $messagesDelivered = $messagesDeliveredRead + $messagesDeliveredUnread;

        return [
            'messages_sent' => $messagesSent,
            'messages_accepted' => $messagesAccepted,
            'messages_delivered' => $messagesDelivered,
            'messages_delivered_read' => $messagesDeliveredRead,
            'messages_delivered_unread' => $messagesDeliveredUnread,
            'messages_failed' => $messagesFailed,
            'delivery_rate' => $messagesSent > 0 ? round(($messagesDelivered / $messagesSent) * 100, 1) : 0.0,
            'delivery_read_rate' => $messagesSent > 0 ? round(($messagesDeliveredRead / $messagesSent) * 100, 1) : 0.0,
            'clients_replied' => count($repliedClientIds),
            'quick_reply_clients' => count($quickReplyClientIds),
            'opt_out_clients' => count($optOutClientIds),
            'payment_options' => $paymentOptions,
        ];
    }

    public function wasAttempted(CampaignWhatsappRecipient $recipient): bool
    {
        $status = strtolower(trim((string) $recipient->status));

        return in_array($status, ['sent', 'accepted', 'delivered', 'read', 'delivered (ecosystem warning)', 'failed', 'pending', 'queued', 'processing', 'scheduled'], true)
            || $this->isEcosystemDelivery($recipient);
    }

    public function wasSent(CampaignWhatsappRecipient $recipient): bool
    {
        $status = strtolower(trim((string) $recipient->status));

        return in_array($status, ['sent', 'accepted', 'delivered', 'read', 'delivered (ecosystem warning)', 'pending', 'queued', 'processing', 'scheduled'], true)
            || $this->isEcosystemDelivery($recipient);
    }

    public function wasDeliveredRead(CampaignWhatsappRecipient $recipient): bool
    {
        $status = strtolower(trim((string) $recipient->status));

        return in_array($status, ['delivered', 'read', 'delivered (ecosystem warning)'], true)
            || $this->isEcosystemDelivery($recipient);
    }

    public function wasDeliveredUnread(CampaignWhatsappRecipient $recipient): bool
    {
        $status = strtolower(trim((string) $recipient->status));

        return in_array($status, ['sent', 'accepted', 'pending', 'queued', 'processing', 'scheduled'], true)
            && !$this->wasDeliveredRead($recipient);
    }

    public function wasDelivered(CampaignWhatsappRecipient $recipient): bool
    {
        return $this->wasDeliveredRead($recipient) || $this->wasDeliveredUnread($recipient);
    }

    public function wasFailed(CampaignWhatsappRecipient $recipient): bool
    {
        $status = strtolower(trim((string) $recipient->status));

        return $status === 'failed' && !$this->isEcosystemDelivery($recipient);
    }

    public function isEcosystemDelivery(CampaignWhatsappRecipient $recipient): bool
    {
        return (string) $recipient->error_code === '131049'
            || str_contains(strtolower((string) $recipient->error_message), 'maintain healthy ecosystem engagement');
    }

    public function replyMeta(CampaignWhatsappRecipient $recipient): array
    {
        $storedType = trim((string) $recipient->reply_type);
        if ($storedType !== '') {
            return [
                'type' => match ($storedType) {
                    'quick_reply' => 'Quick Reply',
                    'list_reply' => 'List Reply',
                    'opt_out' => 'Opt Out',
                    'text' => 'Text Reply',
                    default => ucwords(str_replace('_', ' ', $storedType)),
                },
                'label' => $recipient->reply_label ?: $recipient->last_response,
                'key' => $recipient->reply_key,
                'source' => $recipient->reply_source,
            ];
        }

        $payload = $recipient->provider_status_payload ?: $recipient->status_payload ?: [];
        $message = $this->extractInboundPayloadMessage(is_array($payload) ? $payload : []);

        $textBody = trim((string) data_get($message, 'text.body', ''));
        $buttonText = trim((string) data_get($message, 'button.text', ''));
        $buttonPayload = trim((string) data_get($message, 'button.payload', ''));
        $interactiveType = strtolower(trim((string) data_get($message, 'interactive.type', '')));
        $interactiveButtonTitle = trim((string) data_get($message, 'interactive.button_reply.title', ''));
        $interactiveButtonId = trim((string) data_get($message, 'interactive.button_reply.id', ''));
        $interactiveListTitle = trim((string) data_get($message, 'interactive.list_reply.title', ''));
        $interactiveListId = trim((string) data_get($message, 'interactive.list_reply.id', ''));
        $normalizedResponse = trim((string) ($recipient->last_response ?? ''));

        $keywords = array_values(array_filter([
            $interactiveButtonTitle,
            $interactiveButtonId,
            $buttonText,
            $buttonPayload,
            $interactiveListTitle,
            $interactiveListId,
            $textBody,
            $normalizedResponse,
        ], fn ($value) => trim((string) $value) !== ''));

        $replyType = null;
        $replyLabel = null;
        $replyKey = null;
        $replySource = null;

        if ($interactiveType === 'button_reply') {
            $replyType = 'Quick Reply';
            $replyLabel = $interactiveButtonTitle ?: $normalizedResponse;
            $replyKey = $interactiveButtonId ?: null;
            $replySource = 'interactive.button_reply';
        } elseif ($interactiveType === 'list_reply') {
            $replyType = 'List Reply';
            $replyLabel = $interactiveListTitle ?: $normalizedResponse;
            $replyKey = $interactiveListId ?: null;
            $replySource = 'interactive.list_reply';
        } elseif ($buttonText !== '' || $buttonPayload !== '') {
            $replyType = 'Quick Reply';
            $replyLabel = $buttonText ?: $normalizedResponse;
            $replyKey = $buttonPayload ?: null;
            $replySource = 'button';
        } elseif ($textBody !== '' || $normalizedResponse !== '') {
            $replyType = 'Text Reply';
            $replyLabel = $textBody ?: $normalizedResponse;
            $replySource = 'text';
        }

        if ($this->isOptOutMessage($normalizedResponse !== '' ? $normalizedResponse : ($replyLabel ?? ''), $keywords)) {
            $replyType = 'Opt Out';
            $replyLabel = $replyLabel ?: ($normalizedResponse !== '' ? $normalizedResponse : 'Opt Out');
            $replySource = $replySource ?: 'opt_out';
        } elseif (in_array(strtolower($normalizedResponse), ['yes', 'no'], true) && $replyType === 'Text Reply') {
            $replyType = 'Yes/No Reply';
        }

        return [
            'type' => $replyType,
            'label' => $replyLabel ?: ($normalizedResponse !== '' ? $normalizedResponse : null),
            'key' => $replyKey ?: null,
            'source' => $replySource,
        ];
    }

    private function extractInboundPayloadMessage(array $payload): ?array
    {
        // Inbound replies are stored from the webhook's `value` object, where
        // messages are top-level. Older records may contain the full webhook.
        foreach (($payload['messages'] ?? []) as $message) {
            if (is_array($message)) {
                return $message;
            }
        }

        foreach (($payload['entry'] ?? []) as $entry) {
            foreach (($entry['changes'] ?? []) as $change) {
                foreach ((($change['value'] ?? [])['messages'] ?? []) as $message) {
                    if (is_array($message)) {
                        return $message;
                    }
                }
            }
        }

        return null;
    }

    private function isOptOutMessage(string $body, array $keywords = []): bool
    {
        $phrases = array_filter(array_map(
            fn ($value) => strtolower(trim((string) $value)),
            array_merge([$body], $keywords)
        ));

        $optOutTriggers = ['stop', 'unsubscribe', 'opt out', 'optout', 'cancel', 'end', 'quit'];

        foreach ($phrases as $phrase) {
            if (in_array($phrase, $optOutTriggers, true)) {
                return true;
            }
        }

        return false;
    }
}
