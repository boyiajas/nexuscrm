<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Campaign;
use App\Models\AuditLog;
use App\Models\ChatSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        // Get counts with proper department scoping for non-super admins
        $user = auth()->user();
        $userDeptId = $user?->resolvedDepartmentId();
        
        // Total clients (department-scoped)
        $totalClientsQuery = Client::query();
        if ($user->role !== 'SUPER_ADMIN' && $userDeptId) {
            $totalClientsQuery->whereHas('departments', function ($q) use ($userDeptId) {
                $q->where('departments.id', $userDeptId);
            });
        }
        $totalClients = $totalClientsQuery->count();

        // Campaign counts (department-scoped)
        $campaignQuery = Campaign::query();
        if ($user->role !== 'SUPER_ADMIN') {
            $campaignQuery->where(function ($q) use ($userDeptId) {
                $q->whereDoesntHave('departments')
                  ;

                if ($userDeptId) {
                    $q->orWhereHas('departments', function ($qq) use ($userDeptId) {
                        $qq->where('departments.id', $userDeptId);
                    });
                }
            });
        }
        
        $activeCampaigns = $campaignQuery->where('status', 'Active')->count();
        $completedCampaigns = $campaignQuery->where('status', 'Completed')->count();

        // Open chats: count chat sessions that have unread messages (works even if client_id is null)
        $openChats = ChatSession::where('unread_count', '>', 0)->count();

        // Get delivery statistics from campaign_clients pivot table
        $deliveryStats = DB::table('campaign_clients')
            ->selectRaw('
                COUNT(*) as total_sends,
                SUM(CASE WHEN whatsapp_status = "Delivered" OR email_status = "Delivered" OR sms_status = "Delivered" THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN whatsapp_status = "Failed" OR email_status = "Failed" OR sms_status = "Failed" THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN whatsapp_status = "Pending" OR email_status = "Pending" OR sms_status = "Pending" THEN 1 ELSE 0 END) as pending
            ')
            ->first();

        $totalSends = $deliveryStats->total_sends ?? 0;
        $delivered = $deliveryStats->delivered ?? 0;
        $failed = $deliveryStats->failed ?? 0;
        $pending = $deliveryStats->pending ?? 0;

        // Calculate delivery rate (avoid division by zero)
        $deliveryRate = $totalSends > 0 ? round(($delivered / $totalSends) * 100, 1) : 0;

        // Get channel breakdown from campaigns table
        $channelBreakdown = Campaign::query()
            ->selectRaw('
                SUM(JSON_CONTAINS(channels, \'"WhatsApp"\')) as whatsapp_count,
                SUM(JSON_CONTAINS(channels, \'"Email"\')) as email_count,
                SUM(JSON_CONTAINS(channels, \'"SMS"\')) as sms_count
            ')
            ->first();

        // Recent audit logs (limit to user's department for non-super admins)
        $auditQuery = AuditLog::with('user')
            ->orderBy('created_at', 'desc')
            ->limit(20);
            
        if ($user->role !== 'SUPER_ADMIN') {
            $auditQuery->where('user_id', $user->id);
        }
        
        $recentActivity = $auditQuery->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'user_name' => $log->user ? $log->user->name : 'System',
                    'module' => $log->module,
                    'action' => $log->action,
                    'logged_at' => $log->created_at->diffForHumans(),
                ];
            });

        // Daily campaign creation for the last 7 days
        $dailyCampaigns = Campaign::query()
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Prepare chart data
        $chartLabels = [];
        $chartData = [];
        
        // Fill in missing days
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $chartLabels[] = Carbon::now()->subDays($i)->format('M d');
            
            $campaignForDay = $dailyCampaigns->firstWhere('date', $date);
            $chartData[] = $campaignForDay ? $campaignForDay->count : 0;
        }

        return response()->json([
            'summary' => [
                'total_clients' => $totalClients,
                'active_campaigns' => $activeCampaigns,
                'completed_campaigns' => $completedCampaigns,
                'open_chats' => $openChats,
                'delivery_rate' => $deliveryRate,
                'total_delivered' => (int) $delivered,
                'total_failed' => (int) $failed,
                'total_pending' => (int) $pending,
            ],
            'channels' => [
                'WhatsApp' => (int) ($channelBreakdown->whatsapp_count ?? 0),
                'Email' => (int) ($channelBreakdown->email_count ?? 0),
                'SMS' => (int) ($channelBreakdown->sms_count ?? 0),
            ],
            'recent_activity' => $recentActivity,
            'daily_campaigns' => [
                'labels' => $chartLabels,
                'data' => $chartData,
            ],
        ]);
    }

    public function campaignActivity()
    {
        // Alternative endpoint for campaign activity chart
        $dailyCampaigns = Campaign::query()
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $labels = [];
        $data = [];

        foreach ($dailyCampaigns as $day) {
            $labels[] = Carbon::parse($day->date)->format('M d');
            $data[] = $day->count;
        }

        return response()->json([
            'labels' => $labels,
            'data' => $data,
        ]);
    }

    /**
     * List clients who replied to WhatsApp messages (for dashboard "Open Chats" view).
     */
    public function whatsappReplies()
    {
        $replies = ChatSession::with(['client.departments'])
            ->where('unread_count', '>', 0)
            ->where('platform', 'whatsapp')
            ->orderByDesc('updated_at')
            ->take(500)
            ->get()
            ->map(function ($session) {
                $departments = $session->client?->departments?->pluck('name')->join(', ') ?: null;
                $lastMessage = $session->last_message;
                if (!$lastMessage) {
                    $lastMessage = $session->messages()->latest('created_at')->value('content');
                }
                return [
                    'id'               => $session->id, // chat session id
                    'client_id'        => $session->client_id,
                    'client_name'      => $session->client?->name ?? 'Unknown',
                    'phone'            => $session->phone ?: $session->client?->phone,
                    'campaign_id'      => null,
                    'campaign_name'    => null,
                    'template_name'    => null,
                    'departments'      => $departments,
                    'unread_count'     => $session->unread_count,
                    'last_response'    => $lastMessage,
                    'last_response_at' => optional($session->updated_at)->toDateTimeString(),
                ];
            });

        return response()->json($replies);
    }
}
