<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CampaignWhatsappMessage;
use App\Models\CampaignWhatsappRecipient;
use App\Services\WhatsAppBatchService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ProcessScheduledCampaigns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'campaigns:process-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Finds Scheduled campaign messages whose scheduled_at is past and queues them for sending.';

    /**
     * Execute the console command.
     */
    public function handle(WhatsAppBatchService $batchService)
    {
        $now = Carbon::now();
        
        $messages = CampaignWhatsappMessage::where('status', 'Scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', $now)
            ->get();
            
        if ($messages->isEmpty()) {
            return;
        }

        foreach ($messages as $message) {
            $this->info("Processing scheduled message ID: {$message->id}");
            
            // Update the parent message status to Queued
            $message->status = 'Queued';
            $message->queued_at = $now;
            $message->save();

            // Update all recipients' statuses
            CampaignWhatsappRecipient::where('whatsapp_message_id', $message->id)
                ->update([
                    'status' => 'Queued',
                    'queued_at' => $now,
                ]);

            // Hand it off to the batch service to enqueue the jobs
            try {
                $batchService->queueAllRecipients($message);
                $this->info("Successfully queued message ID: {$message->id}");
                Log::info("Scheduled campaign batch queued.", ['whatsapp_message_id' => $message->id]);
            } catch (\Exception $e) {
                $this->error("Failed to queue message ID: {$message->id}. Error: {$e->getMessage()}");
                Log::error("Failed to queue scheduled campaign batch.", [
                    'whatsapp_message_id' => $message->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
