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

class WhatsAppInboundReplyNotification extends Mailable
{
    use Queueable, SerializesModels;

    public string $appName;
    public string $chatUrl;
    public string $clientName;
    public string $clientPhone;
    public string $clientEmail;
    public string $accountNumber;
    public string $bankName;
    public string $campaignName;
    public string $templateName;
    public string $sentAt;
    public string $outboundBody;

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

        $this->clientName = (string) ($client?->name ?: 'Unknown Client');
        $this->clientPhone = (string) ($phone ?: ($client?->phone ?: 'N/A'));
        $this->clientEmail = (string) ($client?->email ?: 'N/A');
        $this->accountNumber = (string) ($client?->account_number ?: 'N/A');
        $this->bankName = (string) ($client?->bank_name ?: ($messageBatch?->campaign?->bank?->name ?: 'N/A'));
        $this->campaignName = (string) ($messageBatch?->campaign?->name ?: 'N/A');
        $this->templateName = (string) ($messageBatch?->template_name ?: ($messageBatch?->flow_name ?: 'WhatsApp Message'));
        $this->sentAt = (string) ($recipient?->delivered_at?->format('Y-m-d H:i') ?: ($recipient?->last_attempted_at?->format('Y-m-d H:i') ?: ($messageBatch?->sent_at?->format('Y-m-d H:i') ?: 'N/A')));
        $this->outboundBody = (string) ($messageBatch?->preview_body ?: 'N/A');
    }

    public function envelope(): Envelope
    {
        $clientName = $this->clientName ?: $this->phone;
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
                'clientName' => $this->clientName,
                'clientPhone' => $this->clientPhone,
                'clientEmail' => $this->clientEmail,
                'accountNumber' => $this->accountNumber,
                'bankName' => $this->bankName,
                'campaignName' => $this->campaignName,
                'templateName' => $this->templateName,
                'sentAt' => $this->sentAt,
                'outboundBody' => $this->outboundBody,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
