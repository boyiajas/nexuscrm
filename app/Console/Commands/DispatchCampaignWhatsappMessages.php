<?php

namespace App\Console\Commands;

use App\Jobs\ProcessCampaignWhatsappRecipientJob;
use App\Models\CampaignWhatsappMessage;
use App\Services\WhatsAppBatchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;

class DispatchCampaignWhatsappMessages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nexuscrm:dispatch-whatsapp';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fair-share dispatcher for WhatsApp campaigns to prevent queue buildup';

    /**
     * Execute the console command.
     */
    public function handle(WhatsAppBatchService $batchService)
    {
        // 1. Check Queue Depth
        $queueSize = Queue::size('whatsapp');
        $maxQueueDepth = 300; // Threshold before pausing dispatch
        $availableSlots = max(0, $maxQueueDepth - $queueSize);

        if ($availableSlots === 0) {
            $this->warn("WhatsApp queue size ($queueSize) exceeds threshold ($maxQueueDepth). Skipping dispatch.");
            Log::info("WhatsApp dispatcher paused. Queue depth: $queueSize");
            return;
        }

        // 2. Find active campaigns with 'Pending Dispatch' recipients
        $activeMessages = CampaignWhatsappMessage::whereIn('status', ['Queued', 'Processing'])
            ->whereHas('recipients', function ($q) {
                $q->where('status', 'Pending Dispatch');
            })
            ->get();

        if ($activeMessages->isEmpty()) {
            return;
        }

        // 3. Fair-share chunking
        $maxDispatchTotal = min(150, $availableSlots); // Respect queue headroom.
        $activeCount = $activeMessages->count();
        $dispatchPerMessage = (int) ceil($maxDispatchTotal / $activeCount);

        $totalDispatched = 0;

        foreach ($activeMessages as $message) {
            if ($totalDispatched >= $maxDispatchTotal) {
                break;
            }

            $recipients = $message->recipients()
                ->where('status', 'Pending Dispatch')
                ->limit(min($dispatchPerMessage, $maxDispatchTotal - $totalDispatched))
                ->get();

            if ($recipients->isEmpty()) {
                continue;
            }

            // Mark them as Queued and dispatch
            $now = now();
            foreach ($recipients as $recipient) {
                $recipient->update([
                    'status' => 'Queued',
                    'queued_at' => $now,
                ]);

                ProcessCampaignWhatsappRecipientJob::dispatch($recipient->id)->onQueue('whatsapp');
                $totalDispatched++;
            }

            // Sync progress for the UI
            $batchService->syncMessageProgress($message->fresh());
        }

        $this->info("Dispatched $totalDispatched messages across $activeCount campaigns.");
    }
}
