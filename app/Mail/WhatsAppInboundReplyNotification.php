<?php

namespace App\Mail;

use App\Models\User;
use App\Models\Client;
use App\Models\CampaignWhatsappMessage;
use App\Models\CampaignWhatsappRecipient;
use App\Models\SystemSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WhatsAppInboundReplyNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $appName;
    public string $chatUrl;

    public function __construct(
        public User $targetUser,
        public ?Client $client,
        public ?CampaignWhatsappRecipient $recipient,
        public ?CampaignWhatsappMessage $messageBatch,
        public string $clientMessage,
        public string $phone
    ) {
        $this->appName = SystemSetting::first()?->app_name ?: 'NexusCRM';
        
        $baseUrl = config('app.url', url('/'));
        if ($client?->id) {
            $this->chatUrl = rtrim($baseUrl, '/') . '/chat?client_id=' . $client->id;
        } else {
            $this->chatUrl = rtrim($baseUrl, '/') . '/chat';
        }
    }

    public function envelope(): Envelope
    {
        $clientName = $this->client?->name ?: $this->phone;
        return new Envelope(
            subject: "New WhatsApp Reply from {$clientName} - {$this->appName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.whatsapp.inbound_reply',
            with: [
                'appName' => $this->appName,
                'chatUrl' => $this->chatUrl,
                'clientName' => $this->client?->name ?: 'Unknown Client',
                'clientPhone' => $this->phone,
                'clientEmail' => $this->client?->email ?: 'N/A',
                'bankName' => $this->client?->bank_name ?: ($this->messageBatch?->campaign?->bank?->name ?: 'N/A'),
                'campaignName' => $this->messageBatch?->campaign?->name ?: 'N/A',
                'templateName' => $this->messageBatch?->template_name ?: ($this->messageBatch?->flow_name ?: 'WhatsApp Message'),
                'sentAt' => $this->recipient?->whatsapp_sent_at?->format('Y-m-d H:i') ?: ($this->messageBatch?->sent_at?->format('Y-m-d H:i') ?: 'N/A'),
                'outboundBody' => $this->messageBatch?->preview_body ?: 'N/A',
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
