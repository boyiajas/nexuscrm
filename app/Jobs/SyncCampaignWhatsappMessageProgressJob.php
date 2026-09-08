<?php

namespace App\Jobs;

use App\Models\CampaignWhatsappMessage;
use App\Services\WhatsAppBatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class SyncCampaignWhatsappMessageProgressJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $messageId;

    /**
     * Create a new job instance.
     */
    public function __construct($messageId)
    {
        $this->messageId = $messageId;
    }

    /**
     * Prevent overlapping jobs for the same message ID.
     */
    public function middleware()
    {
        return [new WithoutOverlapping($this->messageId)];
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
        });
    }
}
