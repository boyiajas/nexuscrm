<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CampaignWhatsappMessage;
use App\Services\WhatsAppBatchService;

class RepairWhatsappMessageCounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nexuscrm:repair-whatsapp-counts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Repairs drifted summary cache counts on CampaignWhatsappMessage table by recalculating from recipients';

    /**
     * Execute the console command.
     */
    public function handle(WhatsAppBatchService $batchService)
    {
        $this->info('Starting WhatsApp Message Count Repair...');

        $messages = CampaignWhatsappMessage::all();
        $bar = $this->output->createProgressBar($messages->count());

        $fixedCount = 0;

        foreach ($messages as $message) {
            $oldDelivered = $message->delivered;
            $oldPending = $message->pending;

            $batchService->syncMessageProgress($message);

            $message->refresh();

            if ($oldDelivered !== $message->delivered || $oldPending !== $message->pending) {
                $fixedCount++;
                $this->line("\nFixed Batch ID {$message->id}: Pending {$oldPending} -> {$message->pending}, Delivered {$oldDelivered} -> {$message->delivered}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Repair complete! Successfully resynced {$fixedCount} drifted batches.");
    }
}
