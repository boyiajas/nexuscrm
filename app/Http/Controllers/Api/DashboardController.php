<?php

namespace App\Http\Controllers\Api;

use App\Concerns\AppliesAccessScopes;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Campaign;
use App\Models\AuditLog;
use App\Models\ChatSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    use AppliesAccessScopes;

    public function index()
    {
        // Get counts with proper department scoping for non-super admins
        $user = auth()->user();
        $userDeptIds = $user?->resolvedDepartmentIds() ?? [];
        $userBankIds = $user?->accessibleBankIds() ?? [];
        
        // Total clients (bank and department-scoped)
        $totalClientsQuery = Client::query();
        $this->scopeClientQueryToUser($totalClientsQuery, $user);
        $totalClients = $totalClientsQuery->count();

        // Campaign counts (bank and department-scoped)
        $campaignQuery = Campaign::query();
        $this->scopeCampaignQueryToUser($campaignQuery, $user);
        
        $activeCampaigns = (clone $campaignQuery)->where('status', 'Active')->count();
        $completedCampaigns = (clone $campaignQuery)->where('status', 'Completed')->count();

        // Open chats: count chat sessions that have unread messages
        $openChatsQuery = ChatSession::where('unread_count', '>', 0);
        $this->scopeQueryToUserBanks($openChatsQuery, $user, 'bank_id');
        $this->scopeQueryToUserDepartments($openChatsQuery, $user, 'client.departments');
        if (!$user->isSuperAdmin() && $user->isPortfolioScoped()) {
            $openChatsQuery->whereHas('client', fn ($q) => $q->where('assigned_to_id', $user->id));
        }
        $openChats = $openChatsQuery->count();

        // Delivery statistics strictly from the webhook-driven WhatsApp Recipients table
        $recipientStats = DB::table('campaign_whatsapp_recipients')
            ->join('campaign_whatsapp_messages', 'campaign_whatsapp_recipients.whatsapp_message_id', '=', 'campaign_whatsapp_messages.id')
            ->join('campaigns', 'campaign_whatsapp_messages.campaign_id', '=', 'campaigns.id')
            ->selectRaw('
                COUNT(*) as total_sends,
                SUM(CASE WHEN LOWER(campaign_whatsapp_recipients.status) IN ("delivered", "read", "delivered (ecosystem warning)") THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN LOWER(campaign_whatsapp_recipients.status) = "failed" THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN LOWER(campaign_whatsapp_recipients.status) IN ("pending", "queued", "sent", "pending dispatch", "processing") THEN 1 ELSE 0 END) as pending
            ')
            ->join('clients', 'campaign_whatsapp_recipients.client_id', '=', 'clients.id')
            ->when(!$user->canAccessAllBanks(), function ($q) use ($userBankIds) {
                if (empty($userBankIds)) {
                    $q->whereRaw('1 = 0');
                    return;
                }

                $q->whereIn('clients.bank_id', $userBankIds);
            })
            ->when(!$user->canAccessAllBanks() && !$user->isAdmin(), function ($q) use ($userDeptIds) {
                if (empty($userDeptIds)) {
                    $q->whereRaw('1 = 0');
                    return;
                }

                $q->whereExists(function ($subQuery) use ($userDeptIds) {
                    $subQuery->select(DB::raw(1))
                        ->from('client_department')
                        ->whereColumn('client_department.client_id', 'clients.id')
                        ->whereIn('client_department.department_id', $userDeptIds);
                });
            })
            ->when(!$user->canAccessAllBanks() && !$user->isAdmin() && $user->isPortfolioScoped(), function ($q) use ($user) {
                $q->where('clients.assigned_to_id', $user->id);
            })
            ->first();

        $totalSends = (int) ($recipientStats->total_sends ?? 0);
        $delivered = (int) ($recipientStats->delivered ?? 0);
        $failed = (int) ($recipientStats->failed ?? 0);
        $pending = (int) ($recipientStats->pending ?? 0);

        // Calculate delivery rate (avoid division by zero)
        $deliveryRate = $totalSends > 0 ? round(($delivered / $totalSends) * 100, 1) : 0;

        // Get channel breakdown
        $channelBreakdownQuery = (clone $campaignQuery)->select(['id', 'channels']);

        $channelBreakdown = [
            'whatsapp_count' => 0,
            'email_count' => 0,
            'sms_count' => 0,
        ];

        $channelBreakdownQuery->chunk(500, function ($campaigns) use (&$channelBreakdown) {
            foreach ($campaigns as $campaign) {
                $channels = $campaign->channels;

                if (is_string($channels)) {
                    $decoded = json_decode($channels, true);
                    $channels = json_last_error() === JSON_ERROR_NONE ? $decoded : [$channels];
                }

                $channels = collect($channels)
                    ->filter()
                    ->map(fn ($channel) => mb_strtolower(trim((string) $channel)))
                    ->values()
                    ->all();

                if (in_array('whatsapp', $channels, true)) {
                    $channelBreakdown['whatsapp_count']++;
                }
                if (in_array('email', $channels, true)) {
                    $channelBreakdown['email_count']++;
                }
                if (in_array('sms', $channels, true)) {
                    $channelBreakdown['sms_count']++;
                }
            }
        });

        // Recent audit logs (human-readable activity, bank-scoped)
        $auditQuery = AuditLog::with('user')
            ->where('action', 'not like', '[%]% -> HTTP %')
            ->orderBy('created_at', 'desc')
            ->limit(10);

        if (!$user->canAccessAllBanks() && !empty($userBankIds)) {
            $auditQuery->where(function ($q) use ($userBankIds) {
                $q->whereIn('bank_id', $userBankIds)
                  ->orWhereNull('bank_id');
            });
        }
            
        if (!$user->canViewAuditLogsAllUsers()) {
            $auditQuery->where('user_id', $user->id);
        }
        
        $recentActivity = $auditQuery->get()
            ->map(function ($log) {
                $refId = null;
                if (is_array($log->meta)) {
                    $refId = $log->meta['client_id'] ?? $log->meta['campaign_id'] ?? $log->meta['user_id'] ?? null;
                }
                if ($refId) {
                    $refId = '#' . $refId;
                } else {
                    $refId = 'LOG-' . $log->id;
                }

                return [
                    'id'        => $log->id,
                    'user_name' => $log->user ? $log->user->name : 'System',
                    'module'    => $log->module,
                    'action'    => $log->action,
                    'ref_id'    => $refId,
                    'status'    => 'Completed',
                    'logged_at' => $log->logged_at ? $log->logged_at->diffForHumans() : $log->created_at->diffForHumans(),
                ];
            });

        // Daily campaign creation for the last 7 days
        $dailyCampaigns = (clone $campaignQuery)
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
                'total_messages' => (int) $totalSends,
            ],
            'channels' => [
                'WhatsApp' => (int) ($channelBreakdown['whatsapp_count'] ?? 0),
                'Email' => (int) ($channelBreakdown['email_count'] ?? 0),
                'SMS' => (int) ($channelBreakdown['sms_count'] ?? 0),
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
        $user = auth()->user();
        $userDeptIds = $user?->resolvedDepartmentIds() ?? [];
        $campaignPortfolioScoped = $user?->isPortfolioScoped() ?? false;

        // Alternative endpoint for campaign activity chart
        $dailyCampaigns = Campaign::query()
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->tap(fn ($q) => $this->scopeCampaignQueryToUser($q, $user))
            ->when($campaignPortfolioScoped && !$user?->isSuperAdmin(), function ($q) use ($user) {
                $q->whereHas('clients', function ($qq) use ($user) {
                    $qq->where('clients.assigned_to_id', $user->id);
                });
            })
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
            ->tap(fn ($q) => $this->scopeQueryToUserBanks($q, auth()->user(), 'bank_id'))
            ->tap(fn ($q) => $this->scopeQueryToUserDepartments($q, auth()->user(), 'client.departments'))
            ->when(auth()->user()?->isPortfolioScoped() && !auth()->user()?->isSuperAdmin(), function ($q) {
                $q->whereHas('client', fn ($qq) => $qq->where('assigned_to_id', auth()->id()));
            })
            ->orderByDesc('updated_at')
            ->take(2000)
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
