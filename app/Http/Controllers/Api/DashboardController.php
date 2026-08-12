<?php

namespace App\Http\Controllers\Api;

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
    public function index()
    {
        // Get counts with proper department scoping for non-super admins
        $user = auth()->user();
        $userDeptIds = $user?->resolvedDepartmentIds() ?? [];
        $userBankId = $user?->resolvedBankId();
        $campaignPortfolioScoped = $user?->hasRole(User::ROLE_AGENT) ?? false;
        
        // Total clients (department-scoped)
        $totalClientsQuery = Client::query();
        if (!$user->canAccessAllBanks() && $userBankId) {
            $totalClientsQuery->where('bank_id', $userBankId);
        }
        if (!$user->canManageSystemSettings() && !empty($userDeptIds)) {
            $totalClientsQuery->whereHas('departments', function ($q) use ($userDeptIds) {
                $q->whereIn('departments.id', $userDeptIds);
            });
        }
        if ($user->isPortfolioScoped()) {
            $totalClientsQuery->where('assigned_to_id', $user->id);
        }
        $totalClients = $totalClientsQuery->count();

        // Campaign counts (department-scoped)
        $campaignQuery = Campaign::query();
        if (!$user->canAccessAllBanks() && $userBankId) {
            $campaignQuery->where('bank_id', $userBankId);
        }
        if (!$user->canManageSystemSettings()) {
            $campaignQuery->where(function ($q) use ($userDeptIds) {
                $q->whereDoesntHave('departments')
                  ;

                if (!empty($userDeptIds)) {
                    $q->orWhereHas('departments', function ($qq) use ($userDeptIds) {
                        $qq->whereIn('departments.id', $userDeptIds);
                    });
                }
            });
        }
        if ($campaignPortfolioScoped) {
            $campaignQuery->whereHas('clients', function ($q) use ($user) {
                $q->where('clients.assigned_to_id', $user->id);
            });
        }
        
        $activeCampaigns = $campaignQuery->where('status', 'Active')->count();
        $completedCampaigns = $campaignQuery->where('status', 'Completed')->count();

        // Open chats: count chat sessions that have unread messages (works even if client_id is null)
        $openChatsQuery = ChatSession::where('unread_count', '>', 0);
        if (!$user->canAccessAllBanks() && $userBankId) {
            $openChatsQuery->where('bank_id', $userBankId);
        }
        if ($user->isPortfolioScoped()) {
            $openChatsQuery->whereHas('client', function ($q) use ($user) {
                $q->where('assigned_to_id', $user->id);
            });
        }
        $openChats = $openChatsQuery->count();

        // Get delivery statistics from campaign_clients pivot table
        $deliveryStats = DB::table('campaign_clients')
            ->join('campaigns', 'campaigns.id', '=', 'campaign_clients.campaign_id')
            ->join('clients', 'clients.id', '=', 'campaign_clients.client_id')
            ->selectRaw('
                COUNT(*) as total_sends,
                SUM(CASE WHEN whatsapp_status = "Delivered" OR email_status = "Delivered" OR sms_status = "Delivered" THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN whatsapp_status = "Failed" OR email_status = "Failed" OR sms_status = "Failed" THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN whatsapp_status = "Pending" OR email_status = "Pending" OR sms_status = "Pending" THEN 1 ELSE 0 END) as pending
            ')
            ->when(!$user->canAccessAllBanks() && $userBankId, function ($q) use ($userBankId) {
                $q->where('campaigns.bank_id', $userBankId);
            })
            ->when($user->isPortfolioScoped(), function ($q) use ($user) {
                $q->where('clients.assigned_to_id', $user->id);
            })
            ->first();

        $totalSends = $deliveryStats->total_sends ?? 0;
        $delivered = $deliveryStats->delivered ?? 0;
        $failed = $deliveryStats->failed ?? 0;
        $pending = $deliveryStats->pending ?? 0;

        // Calculate delivery rate (avoid division by zero)
        $deliveryRate = $totalSends > 0 ? round(($delivered / $totalSends) * 100, 1) : 0;

        // Get channel breakdown in a database-agnostic way.
        $channelBreakdownQuery = Campaign::query()
            ->select(['id', 'channels'])
            ->when(!$user->canAccessAllBanks() && $userBankId, function ($q) use ($userBankId) {
                $q->where('bank_id', $userBankId);
            })
            ->when(!$user->canManageSystemSettings(), function ($q) use ($userDeptIds) {
                $q->where(function ($inner) use ($userDeptIds) {
                    $inner->whereDoesntHave('departments');

                    if (!empty($userDeptIds)) {
                        $inner->orWhereHas('departments', function ($deptQuery) use ($userDeptIds) {
                            $deptQuery->whereIn('departments.id', $userDeptIds);
                        });
                    }
                });
            })
            ->when($campaignPortfolioScoped, function ($q) use ($user) {
                $q->whereHas('clients', function ($qq) use ($user) {
                    $qq->where('clients.assigned_to_id', $user->id);
                });
            });

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

        // Recent audit logs (limit to user's department for non-super admins)
        $auditQuery = AuditLog::with('user')
            ->orderBy('created_at', 'desc')
            ->limit(5);

        if (!$user->canAccessAllBanks() && $userBankId) {
            $auditQuery->where('bank_id', $userBankId);
        }
            
        if (!$user->canReviewAuditData() && !$user->canManageSystemSettings()) {
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
            ->when(!$user->canAccessAllBanks() && $userBankId, function ($q) use ($userBankId) {
                $q->where('bank_id', $userBankId);
            })
            ->when($campaignPortfolioScoped, function ($q) use ($user) {
                $q->whereHas('clients', function ($qq) use ($user) {
                    $qq->where('clients.assigned_to_id', $user->id);
                });
            })
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
        $campaignPortfolioScoped = $user?->hasRole(User::ROLE_AGENT) ?? false;

        // Alternative endpoint for campaign activity chart
        $dailyCampaigns = Campaign::query()
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->when(!$user?->canAccessAllBanks() && $user?->resolvedBankId(), function ($q) use ($user) {
                $q->where('bank_id', $user->resolvedBankId());
            })
            ->when($user && !$user->canManageSystemSettings(), function ($q) use ($userDeptIds) {
                $q->where(function ($inner) use ($userDeptIds) {
                    $inner->whereDoesntHave('departments');

                    if (!empty($userDeptIds)) {
                        $inner->orWhereHas('departments', function ($deptQuery) use ($userDeptIds) {
                            $deptQuery->whereIn('departments.id', $userDeptIds);
                        });
                    }
                });
            })
            ->when($campaignPortfolioScoped, function ($q) use ($user) {
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
            ->when(!auth()->user()?->canAccessAllBanks() && auth()->user()?->resolvedBankId(), function ($q) {
                $q->where('bank_id', auth()->user()->resolvedBankId());
            })
            ->when(auth()->user()?->isPortfolioScoped(), function ($q) {
                $q->whereHas('client', function ($qq) {
                    $qq->where('assigned_to_id', auth()->id());
                });
            })
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
