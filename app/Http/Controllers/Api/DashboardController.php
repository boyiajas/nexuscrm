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

    public function index(Request $request)
    {
        $user = auth()->user();
        $userDeptIds = $user?->resolvedDepartmentIds() ?? [];
        $userBankIds = $user?->accessibleBankIds() ?? [];

        // 1. Resolve Bank Filter & Scoping
        $requestedBankIds = $request->input('bank_ids');
        if (is_string($requestedBankIds)) {
            $requestedBankIds = array_filter(explode(',', $requestedBankIds));
        } elseif (!is_array($requestedBankIds)) {
            $requestedBankIds = [];
        }
        $requestedBankIds = array_values(array_filter(array_map('intval', (array) $requestedBankIds)));

        $hasBankRestriction = false;
        $effectiveBankIds = [];

        if (!$user->canAccessAllBanks()) {
            $hasBankRestriction = true;
            if (!empty($requestedBankIds)) {
                $effectiveBankIds = array_values(array_intersect($requestedBankIds, $userBankIds));
            } else {
                $effectiveBankIds = $userBankIds;
            }
        } else {
            if (!empty($requestedBankIds)) {
                $hasBankRestriction = true;
                $effectiveBankIds = $requestedBankIds;
            }
        }

        // 2. Resolve Date Range Filter (Default: 'today')
        $dateRange = (string) $request->input('date_range', 'today');
        $now = Carbon::now();
        $endDate = (clone $now)->endOfDay();

        $startDate = match ($dateRange) {
            'today' => (clone $now)->startOfDay(),
            '1_week' => (clone $now)->subDays(7)->startOfDay(),
            '2_weeks', '2_week' => (clone $now)->subDays(14)->startOfDay(),
            '3_weeks', '3_week' => (clone $now)->subDays(21)->startOfDay(),
            '1_month' => (clone $now)->subDays(30)->startOfDay(),
            '3_months', '3_month' => (clone $now)->subDays(90)->startOfDay(),
            '6_months', '6_month' => (clone $now)->subDays(180)->startOfDay(),
            '1_year' => (clone $now)->subYear()->startOfDay(),
            default => (clone $now)->startOfDay(),
        };

        // 3. Total Clients (Bank and Department-scoped)
        $totalClientsQuery = Client::query();
        $this->scopeClientQueryToUser($totalClientsQuery, $user);
        if ($hasBankRestriction) {
            if (empty($effectiveBankIds)) {
                $totalClientsQuery->whereRaw('1 = 0');
            } else {
                $totalClientsQuery->whereIn('clients.bank_id', $effectiveBankIds);
            }
        }
        $totalClients = (clone $totalClientsQuery)->count();

        // Active / Engaged / New clients in the selected period
        $activeClients = (clone $totalClientsQuery)->where(function ($q) use ($startDate, $endDate) {
            $q->whereBetween('clients.created_at', [$startDate, $endDate])
              ->orWhereExists(function ($sub) use ($startDate, $endDate) {
                  $sub->select(DB::raw(1))
                      ->from('campaign_whatsapp_recipients')
                      ->whereColumn('campaign_whatsapp_recipients.client_id', 'clients.id')
                      ->whereBetween('campaign_whatsapp_recipients.created_at', [$startDate, $endDate]);
              });
        })->count();

        // 4. Campaign counts (Bank and Department-scoped)
        $campaignQuery = Campaign::query();
        $this->scopeCampaignQueryToUser($campaignQuery, $user);
        if ($hasBankRestriction) {
            if (empty($effectiveBankIds)) {
                $campaignQuery->whereRaw('1 = 0');
            } else {
                $campaignQuery->whereIn('campaigns.bank_id', $effectiveBankIds);
            }
        }

        // Active campaigns in the selected period
        $activeCampaigns = (clone $campaignQuery)
            ->where('status', 'Active')
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('campaigns.created_at', [$startDate, $endDate])
                  ->orWhereBetween('campaigns.updated_at', [$startDate, $endDate])
                  ->orWhereHas('whatsappMessages', function ($mq) use ($startDate, $endDate) {
                      $mq->whereBetween('created_at', [$startDate, $endDate]);
                  });
            })
            ->count();

        if ($activeCampaigns === 0) {
            // Fallback: If no campaign was specifically created/updated in date range, show current Active campaigns for selected bank(s)
            $activeCampaigns = (clone $campaignQuery)->where('status', 'Active')->count();
        }

        $completedCampaigns = (clone $campaignQuery)
            ->where('status', 'Completed')
            ->whereBetween('updated_at', [$startDate, $endDate])
            ->count();

        // 5. Open Chats & Chats Requiring Attention
        $chatQuery = ChatSession::query();
        $this->scopeChatSessionQueryToUser($chatQuery, $user);
        if ($hasBankRestriction) {
            if (empty($effectiveBankIds)) {
                $chatQuery->whereRaw('1 = 0');
            } else {
                $chatQuery->where(function ($q) use ($effectiveBankIds) {
                    $q->whereIn('chat_sessions.bank_id', $effectiveBankIds)
                      ->orWhereHas('client', fn ($cq) => $cq->whereIn('bank_id', $effectiveBankIds));
                });
            }
        }

        // Open chats: status is active / not closed
        $openChatsQuery = (clone $chatQuery)->where(function ($q) {
            $q->whereNull('status')->orWhere('status', '!=', 'closed');
        });
        $openChats = (clone $openChatsQuery)->count();

        // Chats requiring attention: open chats that have unread messages
        $chatsRequiringAttention = (clone $openChatsQuery)
            ->where('unread_count', '>', 0)
            ->count();

        // 6. Delivery statistics strictly from the webhook-driven WhatsApp Recipients table in period
        $recipientStatsQuery = DB::table('campaign_whatsapp_recipients')
            ->join('campaign_whatsapp_messages', 'campaign_whatsapp_recipients.whatsapp_message_id', '=', 'campaign_whatsapp_messages.id')
            ->join('campaigns', 'campaign_whatsapp_messages.campaign_id', '=', 'campaigns.id')
            ->join('clients', 'campaign_whatsapp_recipients.client_id', '=', 'clients.id')
            ->whereBetween('campaign_whatsapp_recipients.created_at', [$startDate, $endDate]);

        if ($hasBankRestriction) {
            if (empty($effectiveBankIds)) {
                $recipientStatsQuery->whereRaw('1 = 0');
            } else {
                $recipientStatsQuery->whereIn('clients.bank_id', $effectiveBankIds);
            }
        }

        if (!$user->canAccessAllBanks() && !$user->isAdmin()) {
            if (empty($userDeptIds)) {
                $recipientStatsQuery->whereRaw('1 = 0');
            } else {
                $recipientStatsQuery->whereExists(function ($subQuery) use ($userDeptIds) {
                    $subQuery->select(DB::raw(1))
                        ->from('client_department')
                        ->whereColumn('client_department.client_id', 'clients.id')
                        ->whereIn('client_department.department_id', $userDeptIds);
                });
            }
        }

        if (!$user->canAccessAllBanks() && !$user->isAdmin() && $user->isPortfolioScoped()) {
            $recipientStatsQuery->where('clients.assigned_to_id', $user->id);
        }

        $recipientStats = $recipientStatsQuery->selectRaw("
            COUNT(*) as total_sends,
            SUM(CASE WHEN LOWER(campaign_whatsapp_recipients.status) IN ('delivered', 'read', 'delivered (ecosystem warning)') THEN 1 ELSE 0 END) as delivered,
            SUM(CASE WHEN LOWER(campaign_whatsapp_recipients.status) = 'failed' THEN 1 ELSE 0 END) as failed,
            SUM(CASE WHEN LOWER(campaign_whatsapp_recipients.status) IN ('pending', 'queued', 'sent', 'pending dispatch', 'processing') THEN 1 ELSE 0 END) as pending
        ")->first();

        $totalSends = (int) ($recipientStats->total_sends ?? 0);
        $delivered = (int) ($recipientStats->delivered ?? 0);
        $failed = (int) ($recipientStats->failed ?? 0);
        $pending = (int) ($recipientStats->pending ?? 0);

        // Calculate delivery rate (avoid division by zero)
        $deliveryRate = $totalSends > 0 ? round(($delivered / $totalSends) * 100, 1) : 0;

        // 7. Channel breakdown for campaigns in the selected period
        $channelBreakdownQuery = (clone $campaignQuery)->select(['id', 'channels'])
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('campaigns.created_at', [$startDate, $endDate])
                  ->orWhereHas('whatsappMessages', function ($mq) use ($startDate, $endDate) {
                      $mq->whereBetween('created_at', [$startDate, $endDate]);
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

        // 8. Recent Audit Logs (scoped to period and bank)
        $auditQuery = AuditLog::with('user')
            ->where('action', 'not like', '[%]% -> HTTP %')
            ->orderBy('created_at', 'desc')
            ->limit(10);

        if ($hasBankRestriction) {
            if (empty($effectiveBankIds)) {
                $auditQuery->whereRaw('1 = 0');
            } else {
                $auditQuery->where(function ($q) use ($effectiveBankIds) {
                    $q->whereIn('bank_id', $effectiveBankIds)
                      ->orWhereNull('bank_id');
                });
            }
        }

        $auditQuery->whereBetween('created_at', [$startDate, $endDate]);

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

        return response()->json([
            'summary' => [
                'total_clients' => $totalClients,
                'active_clients' => $activeClients,
                'active_campaigns' => $activeCampaigns,
                'completed_campaigns' => $completedCampaigns,
                'open_chats' => $openChats,
                'chats_requiring_attention' => $chatsRequiringAttention,
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
            'filter' => [
                'date_range' => $dateRange,
                'start_date' => $startDate->toDateTimeString(),
                'end_date'   => $endDate->toDateTimeString(),
                'bank_ids'   => $effectiveBankIds,
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
        $user = auth()->user();
        $query = ChatSession::with(['client.departments', 'client.bank', 'bank'])
            ->where('unread_count', '>', 0)
            ->where('platform', 'whatsapp');

        $this->scopeChatSessionQueryToUser($query, $user);

        $replies = $query
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
