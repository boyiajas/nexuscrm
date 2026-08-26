<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WhatsAppAccountAlertNotification extends Mailable
{
    use Queueable, SerializesModels;

    public string $alertType;
    public string $wabaId;
    public string $eventDetails;
    public string $phoneOrAccount;

    /**
     * Create a new message instance.
     */
    public function __construct(string $alertType, string $wabaId, string $phoneOrAccount, string $eventDetails)
    {
        $this->alertType = $alertType;
        $this->wabaId = $wabaId;
        $this->phoneOrAccount = $phoneOrAccount;
        $this->eventDetails = $eventDetails;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'URGENT: Meta WhatsApp Account Alert - ' . $this->alertType,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.whatsapp.account_alert',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
