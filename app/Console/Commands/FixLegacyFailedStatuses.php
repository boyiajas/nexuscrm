<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CampaignWhatsappRecipient;
use App\Models\CampaignWhatsappMessage;
use App\Models\Campaign;
use App\Services\WhatsAppBatchService;
use Illuminate\Support\Facades\DB;

class FixLegacyFailedStatuses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nexuscrm:fix-failed-statuses';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fixes legacy 131026 ecosystem warnings to show accurately as Failed in the database.';

    /**
     * Execute the console command.
     */
    public function handle(WhatsAppBatchService $batchService)
    {
        $this->info('Starting database sweep for 131026 errors...');

        // 1. Update the recipients table
        $updatedRecipients = CampaignWhatsappRecipient::where('error_code', '131026')
            ->update(['status' => 'Failed']);
        $this->info("Updated {$updatedRecipients} records in campaign_whatsapp_recipients.");

        // 2. Loop through all campaigns and update the pivot table
        $campaigns = Campaign::all();
        $totalPivotUpdates = 0;

        foreach ($campaigns as $campaign) {
            $failedClientIds = DB::table('campaign_whatsapp_recipients')
                ->join('campaign_whatsapp_messages', 'campaign_whatsapp_recipients.whatsapp_message_id', '=', 'campaign_whatsapp_messages.id')
                ->where('campaign_whatsapp_messages.campaign_id', $campaign->id)
                ->where('campaign_whatsapp_recipients.error_code', '131026')
                ->pluck('campaign_whatsapp_recipients.client_id');
                
            if ($failedClientIds->count() > 0) {
                $affected = DB::table('campaign_clients')
                    ->where('campaign_id', $campaign->id)
                    ->whereIn('client_id', $failedClientIds)
                    ->update(['whatsapp_status' => 'Failed']);
                
                $totalPivotUpdates += $affected;
            }
        }
        $this->info("Updated {$totalPivotUpdates} records in campaign_clients pivot table.");

        // 3. Resync all campaign dashboard metrics
        $this->info('Resyncing campaign progress metrics...');
        $messages = CampaignWhatsappMessage::whereIn('status', ['Completed', 'Completed With Failures'])->get();
        
        $bar = $this->output->createProgressBar(count($messages));
        $bar->start();

        foreach ($messages as $m) {
            $batchService->syncMessageProgress($m);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info('Database sweep completed successfully!');
    }
}
