<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CostThresholdAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $ruleName,
        public int $percentage,
        public float $currentSpend,
        public float $budgetAmount,
        public string $monthName,
        public array $bankNames = [],
        public bool $isTest = false
    ) {
    }

    public function envelope(): Envelope
    {
        $prefix = $this->isTest ? '[TEST ALERT] ' : '';
        $icon = $this->percentage >= 100 ? '🚨' : ($this->percentage >= 90 ? '⚠️' : '📊');
        $subject = sprintf(
            '%s%s %d%% Cost Budget Threshold Reached - %s (%s)',
            $prefix,
            $icon,
            $this->percentage,
            $this->ruleName,
            $this->monthName
        );

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.cost_threshold_alert',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
