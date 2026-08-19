<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\CampaignWhatsappMessage;
use App\Models\CampaignWhatsappRecipient;
use App\Models\CampaignClient;
use App\Services\WhatsAppBatchService;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Update recipient records in campaign_whatsapp_recipients
        $affectedRecipients = CampaignWhatsappRecipient::query()
            ->where(function ($q) {
                $q->whereIn('error_code', ['131049', '131026'])
                  ->orWhere('error_message', 'like', '%maintain healthy ecosystem engagement%')
                  ->orWhere('status', 'Delivered (Ecosystem Warning)');
            })
            ->get();

        foreach ($affectedRecipients as $recipient) {
            $recipient->update([
                'status' => 'Delivered',
                'delivered_at' => $recipient->delivered_at ?: ($recipient->updated_at ?: now()),
            ]);

            if ($recipient->client_id && $recipient->message?->campaign_id) {
                CampaignClient::where('campaign_id', $recipient->message->campaign_id)
                    ->where('client_id', $recipient->client_id)
                    ->update([
                        'whatsapp_status' => 'Delivered',
                        'whatsapp_sent_at' => $recipient->delivered_at ?: now(),
                        'updated_at' => now(),
                    ]);
            }
        }

        // 2. Re-sync batch progress & metrics for all WhatsApp messages
        $batchService = app(WhatsAppBatchService::class);
        $messages = CampaignWhatsappMessage::all();
        foreach ($messages as $message) {
            $batchService->syncMessageProgress($message);
        }
    }

    public function down(): void
    {
        // Non-destructive down migration
    }
};
