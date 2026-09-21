<?php

namespace App\Jobs;

use App\Models\CampaignWhatsappMessage;
use App\Services\WhatsAppBatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class SyncCampaignWhatsappMessageProgressJob implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $messageId;

    // Status webhooks for one campaign can arrive in bursts. Keep one pending
    // reconciliation per campaign while allowing a later one during processing.
    public int $uniqueFor = 300;

    public int $tries = 30;

    public int $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct($messageId)
    {
        $this->messageId = $messageId;
        $this->onQueue('whatsapp');
    }

    public function uniqueId(): string
    {
        return (string) $this->messageId;
    }

    /**
     * Prevent overlapping jobs for the same message ID.
     */
    public function middleware()
    {
        return [(new WithoutOverlapping($this->messageId))
            ->releaseAfter(5)
            ->expireAfter(120)];
    }

    public function backoff(): array
    {
        return [2, 5, 10];
    }

    /**
     * Execute the job.
     */
    public function handle(WhatsAppBatchService $batchService): void
    {
        DB::transaction(function () use ($batchService) {
            $message = CampaignWhatsappMessage::lockForUpdate()->find($this->messageId);
            if ($message) {
                $batchService->syncMessageProgress($message);
            }
        }, 3);
    }
}
