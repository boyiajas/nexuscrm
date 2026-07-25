<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\CampaignWhatsappRecipient;
use Illuminate\Support\Facades\DB;

class DepartmentStatsController extends Controller
{
    public function whatsappStats(Department $department)
    {
        $stats = CampaignWhatsappRecipient::query()
            ->select(
                'campaign_whatsapp_recipients.provider_display_phone_number as number',
                DB::raw('COUNT(campaign_whatsapp_recipients.id) as total_sent'),
                DB::raw("SUM(CASE WHEN LOWER(campaign_whatsapp_recipients.status) = 'delivered' THEN 1 ELSE 0 END) as total_delivered"),
                DB::raw("SUM(CASE WHEN LOWER(campaign_whatsapp_recipients.status) = 'read' THEN 1 ELSE 0 END) as total_read"),
                DB::raw("SUM(CASE WHEN LOWER(campaign_whatsapp_recipients.status) = 'failed' THEN 1 ELSE 0 END) as total_failed"),
                DB::raw("SUM(CASE WHEN campaign_whatsapp_recipients.last_response IS NOT NULL THEN 1 ELSE 0 END) as total_responses")
            )
            ->join('campaign_whatsapp_messages', 'campaign_whatsapp_recipients.whatsapp_message_id', '=', 'campaign_whatsapp_messages.id')
            ->join('campaigns', 'campaign_whatsapp_messages.campaign_id', '=', 'campaigns.id')
            ->join('campaign_department', 'campaigns.id', '=', 'campaign_department.campaign_id')
            ->where('campaign_department.department_id', $department->id)
            ->whereNotNull('campaign_whatsapp_recipients.provider_display_phone_number')
            ->groupBy('campaign_whatsapp_recipients.provider_display_phone_number')
            ->get();

        return response()->json($stats);
    }
}
