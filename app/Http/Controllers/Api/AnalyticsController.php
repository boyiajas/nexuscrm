<?php

namespace App\Http\Controllers\Api;

use App\Concerns\AppliesAccessScopes;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Campaign;
use App\Models\CampaignWhatsappRecipient;
use App\Models\ChatSession;
use App\Models\User;
use App\Models\WhatsappTemplateCache;
use App\Services\CampaignWhatsappReportService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    use AppliesAccessScopes;

    public function __construct(
        protected CampaignWhatsappReportService $campaignReportService
    ) {
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        
        $timeframe = $request->query('timeframe', 'daily');
        $dateRange = $request->query('date_range');
        $bankId = $request->query('bank_id');

        $selectedBankId = ($bankId && $bankId !== 'all') ? (int) $bankId : null;
        if ($selectedBankId && $user && !$user->canAccessAllBanks() && !$user->isSuperAdmin()) {
            $accessibleBankIds = $user->accessibleBankIds() ?? [];
            if (!in_array($selectedBankId, $accessibleBankIds, true)) {
                $selectedBankId = -1; // Force empty result if unauthorized
            }
        }

        $startDate = null;
        $endDate = Carbon::now()->endOfDay();

        if ($dateRange === 'year_to_date' || $dateRange === 'ytd') {
            $startDate = Carbon::now()->startOfYear();
        } elseif ($dateRange === 'all_time' || $dateRange === 'all') {
            $startDate = null;
            $endDate = null;
        } elseif ($dateRange === 'last_30_days') {
            $startDate = Carbon::now()->subDays(29)->startOfDay();
        } elseif ($dateRange === 'this_month') {
            $startDate = Carbon::now()->startOfMonth();
        } elseif ($dateRange === 'last_90_days') {
            $startDate = Carbon::now()->subDays(89)->startOfDay();
        } elseif ($dateRange) {
            $startDate = Carbon::now()->startOfYear();
        } else {
            // Default when date_range is omitted (backwards compatibility)
            if ($timeframe === 'monthly') {
                $monthsBack = 12;
                $startDate = Carbon::now()->subMonths($monthsBack - 1)->startOfMonth();
            } elseif ($timeframe === 'weekly' || $timeframe === '3month') {
                $weeksBack = $timeframe === '3month' ? 12 : 8;
                $startDate = Carbon::now()->subWeeks($weeksBack - 1)->startOfWeek();
            } else {
                $daysBack = 30;
                $startDate = Carbon::now()->subDays($daysBack - 1)->startOfDay();
            }
        }

        // OVERALL STATISTICS
        $statsQuery = DB::table('campaign_whatsapp_recipients')
            ->join('clients', 'campaign_whatsapp_recipients.client_id', '=', 'clients.id')
            ->selectRaw('
                COUNT(*) as total_dispatched,
                SUM(CASE WHEN LOWER(campaign_whatsapp_recipients.status) IN ("delivered", "read", "delivered (ecosystem warning)") THEN 1 ELSE 0 END) as total_delivered,
                SUM(CASE WHEN LOWER(campaign_whatsapp_recipients.status) = "read" THEN 1 ELSE 0 END) as total_read,
                SUM(CASE WHEN campaign_whatsapp_recipients.last_response IS NOT NULL OR campaign_whatsapp_recipients.reply_type IS NOT NULL THEN 1 ELSE 0 END) as total_replied
            ')
            ->whereIn(DB::raw('LOWER(campaign_whatsapp_recipients.status)'), [
                'sent',
                'accepted',
                'delivered',
                'read',
                'delivered (ecosystem warning)',
                'failed',
            ]);

        if ($startDate) {
            $statsQuery->where('campaign_whatsapp_recipients.created_at', '>=', $startDate);
        }
        if ($endDate) {
            $statsQuery->where('campaign_whatsapp_recipients.created_at', '<=', $endDate);
        }
        if ($selectedBankId) {
            $statsQuery->where('clients.bank_id', $selectedBankId);
        }

        $stats = $statsQuery
            ->tap(fn ($query) => $this->scopeWhatsappRecipientStatsQuery($query, $user))
            ->first();

        $dispatched = (int) ($stats->total_dispatched ?? 0);
        $delivered = (int) ($stats->total_delivered ?? 0);
        $read = (int) ($stats->total_read ?? 0);
        
        // Combine with ChatSessions for inbound engaged
        $chatInboundQuery = ChatSession::where('platform', 'whatsapp')
            ->where('unread_count', '>', 0);
        if ($startDate) {
            $chatInboundQuery->where('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $chatInboundQuery->where('created_at', '<=', $endDate);
        }
        if ($selectedBankId) {
            $chatInboundQuery->where('bank_id', $selectedBankId);
        }
        $this->scopeChatSessionQueryToUser($chatInboundQuery, $user);
        $chatInbound = $chatInboundQuery->count();
        $inbound = max((int) ($stats->total_replied ?? 0), $chatInbound);

        $deliveryRate = $dispatched > 0 ? round(($delivered / $dispatched) * 100, 1) : 0;
        $readRate = $delivered > 0 ? round(($read / $delivered) * 100, 1) : 0;
        $engagementRate = $dispatched > 0 ? round(($inbound / $dispatched) * 100, 1) : 0;

        // SPEND & COST
        // Meta pricing based on South Africa rates (USD)
        $marketingCost = 0.0175; // Estimated SA rate for Marketing
        $utilityCost = 0.0076;   // From South Africa List rate
        $authCost = 0.0076;      // From South Africa List rate

        // Estimate based on templates (if actual billing isn't stored)
        $totalMarketingMsgs = round($dispatched * 0.58);
        $totalUtilityMsgs = round($dispatched * 0.40);
        $totalAuthMsgs = $dispatched - $totalMarketingMsgs - $totalUtilityMsgs;

        $spendMarketing = $totalMarketingMsgs * $marketingCost;
        $spendUtility = $totalUtilityMsgs * $utilityCost;
        $spendAuth = $totalAuthMsgs * $authCost;
        $totalSpend = $spendMarketing + $spendUtility + $spendAuth;
        
        $avgSpend = $delivered > 0 ? round($totalSpend / $delivered, 4) : 0;

        // ASSETS
        $activeCampaignsQuery = Campaign::where('status', 'Active');
        if ($selectedBankId) {
            $activeCampaignsQuery->where('bank_id', $selectedBankId);
        }
        $this->scopeCampaignQueryToUser($activeCampaignsQuery, $user);
        $activeCampaigns = $activeCampaignsQuery->count();
        $approvedTemplates = WhatsappTemplateCache::where('status', 'APPROVED')->count();

        $chartLabels = [];
        $chartDispatched = [];
        $chartDelivered = [];
        $chartRead = [];
        $chartReplied = [];

        if ($timeframe === 'monthly') {
            $monthsBack = 12;
            $chartQuery = DB::table('campaign_whatsapp_recipients')
                ->join('clients', 'campaign_whatsapp_recipients.client_id', '=', 'clients.id')
                ->selectRaw('
                    DATE_FORMAT(campaign_whatsapp_recipients.created_at, "%Y-%m") as period,
                    COUNT(*) as dispatched,
                    SUM(CASE WHEN LOWER(campaign_whatsapp_recipients.status) IN ("delivered", "read", "delivered (ecosystem warning)") THEN 1 ELSE 0 END) as delivered,
                    SUM(CASE WHEN LOWER(campaign_whatsapp_recipients.status) = "read" THEN 1 ELSE 0 END) as read_count,
                    SUM(CASE WHEN campaign_whatsapp_recipients.last_response IS NOT NULL OR campaign_whatsapp_recipients.reply_type IS NOT NULL THEN 1 ELSE 0 END) as replied
                ')
                ->where('campaign_whatsapp_recipients.created_at', '>=', Carbon::now()->subMonths($monthsBack - 1)->startOfMonth())
                ->whereIn(DB::raw('LOWER(campaign_whatsapp_recipients.status)'), [
                    'sent',
                    'accepted',
                    'delivered',
                    'read',
                    'delivered (ecosystem warning)',
                    'failed',
                ]);

            if ($selectedBankId) {
                $chartQuery->where('clients.bank_id', $selectedBankId);
            }

            $statsMonthly = $chartQuery
                ->tap(fn ($query) => $this->scopeWhatsappRecipientStatsQuery($query, $user))
                ->groupBy('period')
                ->orderBy('period')
                ->get();

            for ($i = $monthsBack - 1; $i >= 0; $i--) {
                $date = Carbon::now()->subMonths($i);
                $periodStr = $date->format('Y-m');
                $chartLabels[] = $date->format('M Y');
                
                $data = $statsMonthly->firstWhere('period', $periodStr);
                $chartDispatched[] = $data ? (int) $data->dispatched : 0;
                $chartDelivered[] = $data ? (int) $data->delivered : 0;
                $chartRead[] = $data ? (int) $data->read_count : 0;
                $chartReplied[] = $data ? (int) $data->replied : 0;
            }
        } elseif ($timeframe === 'weekly' || $timeframe === '3month') {
            $weeksBack = $timeframe === '3month' ? 12 : 8;
            
            $chartQuery = DB::table('campaign_whatsapp_recipients')
                ->join('clients', 'campaign_whatsapp_recipients.client_id', '=', 'clients.id')
                ->selectRaw('
                    YEARWEEK(campaign_whatsapp_recipients.created_at, 1) as period,
                    COUNT(*) as dispatched,
                    SUM(CASE WHEN LOWER(campaign_whatsapp_recipients.status) IN ("delivered", "read", "delivered (ecosystem warning)") THEN 1 ELSE 0 END) as delivered,
                    SUM(CASE WHEN LOWER(campaign_whatsapp_recipients.status) = "read" THEN 1 ELSE 0 END) as read_count,
                    SUM(CASE WHEN campaign_whatsapp_recipients.last_response IS NOT NULL OR campaign_whatsapp_recipients.reply_type IS NOT NULL THEN 1 ELSE 0 END) as replied
                ')
                ->where('campaign_whatsapp_recipients.created_at', '>=', Carbon::now()->subWeeks($weeksBack - 1)->startOfWeek())
                ->whereIn(DB::raw('LOWER(campaign_whatsapp_recipients.status)'), [
                    'sent',
                    'accepted',
                    'delivered',
                    'read',
                    'delivered (ecosystem warning)',
                    'failed',
                ]);

            if ($selectedBankId) {
                $chartQuery->where('clients.bank_id', $selectedBankId);
            }

            $statsWeekly = $chartQuery
                ->tap(fn ($query) => $this->scopeWhatsappRecipientStatsQuery($query, $user))
                ->groupBy('period')
                ->orderBy('period')
                ->get();

            for ($i = $weeksBack - 1; $i >= 0; $i--) {
                $date = Carbon::now()->subWeeks($i);
                $periodStr = $date->format('oW'); // ISO year and week number
                $chartLabels[] = 'Week of ' . $date->startOfWeek()->format('M d');
                
                $data = $statsWeekly->firstWhere('period', $periodStr);
                $chartDispatched[] = $data ? (int) $data->dispatched : 0;
                $chartDelivered[] = $data ? (int) $data->delivered : 0;
                $chartRead[] = $data ? (int) $data->read_count : 0;
                $chartReplied[] = $data ? (int) $data->replied : 0;
            }
        } else {
            // Daily (default, 30 days)
            $daysBack = 30;
            
            $chartQuery = DB::table('campaign_whatsapp_recipients')
                ->join('clients', 'campaign_whatsapp_recipients.client_id', '=', 'clients.id')
                ->selectRaw('
                    DATE(campaign_whatsapp_recipients.created_at) as period,
                    COUNT(*) as dispatched,
                    SUM(CASE WHEN LOWER(campaign_whatsapp_recipients.status) IN ("delivered", "read", "delivered (ecosystem warning)") THEN 1 ELSE 0 END) as delivered,
                    SUM(CASE WHEN LOWER(campaign_whatsapp_recipients.status) = "read" THEN 1 ELSE 0 END) as read_count,
                    SUM(CASE WHEN campaign_whatsapp_recipients.last_response IS NOT NULL OR campaign_whatsapp_recipients.reply_type IS NOT NULL THEN 1 ELSE 0 END) as replied
                ')
                ->where('campaign_whatsapp_recipients.created_at', '>=', Carbon::now()->subDays($daysBack - 1)->startOfDay())
                ->whereIn(DB::raw('LOWER(campaign_whatsapp_recipients.status)'), [
                    'sent',
                    'accepted',
                    'delivered',
                    'read',
                    'delivered (ecosystem warning)',
                    'failed',
                ]);

            if ($selectedBankId) {
                $chartQuery->where('clients.bank_id', $selectedBankId);
            }

            $statsDaily = $chartQuery
                ->tap(fn ($query) => $this->scopeWhatsappRecipientStatsQuery($query, $user))
                ->groupBy('period')
                ->orderBy('period')
                ->get();

            for ($i = $daysBack - 1; $i >= 0; $i--) {
                $date = Carbon::now()->subDays($i);
                $periodStr = $date->format('Y-m-d');
                $chartLabels[] = $date->format('M d');
                
                $data = $statsDaily->firstWhere('period', $periodStr);
                $chartDispatched[] = $data ? (int) $data->dispatched : 0;
                $chartDelivered[] = $data ? (int) $data->delivered : 0;
                $chartRead[] = $data ? (int) $data->read_count : 0;
                $chartReplied[] = $data ? (int) $data->replied : 0;
            }
        }

        // TEMPLATES DATA
        $templatesData = WhatsappTemplateCache::get()->map(function ($tpl) {
            $sent = rand(1000, 20000); // Mock data for now since we don't track by template_id easily without joining message table
            $cat = $tpl->category;
            $rate = $cat === 'MARKETING' ? 0.0175 : 0.0076;
            $cost = $sent * $rate;
            
            return [
                'name' => $tpl->friendly_name,
                'id' => $tpl->sid,
                'category' => ucfirst(strtolower($tpl->category)),
                'campaign' => 'Campaign Binding TBD', // Future enhancement
                'sub_campaign' => 'Batch Reference TBD',
                'sent' => number_format($sent),
                'delivery' => rand(90, 99) . '.' . rand(0, 9) . '%',
                'reply' => rand(15, 45) . '.' . rand(0, 9) . '%',
                'rate' => '$' . number_format($rate, 4),
                'cost' => '$' . number_format($cost, 2),
                'status' => $tpl->status,
            ];
        });

        // CAMPAIGNS DATA
        $templateCaches = WhatsappTemplateCache::all();

        $campaignsQuery = Campaign::with(['bank', 'whatsappMessages.createdBy:id,name'])
            ->orderBy('created_at', 'desc');

        if ($selectedBankId) {
            $campaignsQuery->where('campaigns.bank_id', $selectedBankId);
        }

        $campaignDateScope = $request->query('campaign_date_scope', 'all');
        if ($campaignDateScope === 'range' || $campaignDateScope === 'filter') {
            if ($startDate) {
                $campaignsQuery->where('campaigns.created_at', '>=', $startDate);
            }
            if ($endDate) {
                $campaignsQuery->where('campaigns.created_at', '<=', $endDate);
            }
        }

        $limit = $request->query('limit');
        if ($limit && is_numeric($limit) && (int) $limit > 0) {
            $campaignsQuery->limit((int) $limit);
        }

        $this->scopeCampaignQueryToUser($campaignsQuery, $user);
        $campaignsData = $campaignsQuery->get()->map(function ($cmp) use ($user, $templateCaches) {
            $report = $this->campaignReportService->build(
                $cmp,
                !$user?->isSuperAdmin()
                    ? function ($recipientQuery) use ($user) {
                        $recipientQuery->whereHas('client', function ($clientQuery) use ($user) {
                            $this->scopeClientQueryToUser($clientQuery, $user);
                        });
                    }
                    : null
            );
            $sent = $report['messages_sent'];
            [$rate, $templateCategory, $templateName] = $this->resolveCampaignTemplateRate($cmp, $templateCaches);
            $cost = $report['messages_accepted'] * $rate;
            $agents = $cmp->whatsappMessages
                ->pluck('createdBy')
                ->filter()
                ->unique('id')
                ->map(function ($agent) {
                    $names = preg_split('/\s+/', trim((string) $agent->name)) ?: [];

                    return strtoupper(
                        substr($names[0] ?? 'A', 0, 1)
                        . substr($names[1] ?? '', 0, 1)
                    );
                })
                ->values()
                ->all();

            return [
                'id' => $cmp->id,
                'name' => $cmp->name,
                'batch' => 'ID-' . $cmp->id,
                'bank' => $cmp->bank ? $cmp->bank->name : 'N/A',
                'status' => $cmp->status,
                'created_at' => $cmp->created_at ? $cmp->created_at->format('Y-m-d') : null,
                'agents' => $agents,
                'sent' => number_format($sent),
                'delivered' => number_format($report['messages_delivered']),
                'delivered_read' => number_format($report['messages_delivered_read']),
                'delivered_unread' => number_format($report['messages_delivered_unread']),
                'failed' => number_format($report['messages_failed']),
                'delivery' => number_format($report['delivery_rate'], 1) . '%',
                'replies' => $report['clients_replied'],
                'quick_replies' => $report['quick_reply_clients'],
                'opt_outs' => $report['opt_out_clients'],
                'ptp' => $report['payment_options']['ptp'],
                'debit_order' => $report['payment_options']['debit_order'],
                'payment_not_set' => $report['payment_options']['not_set'],
                'cost' => '$' . number_format($cost, 2),
                'rate' => '$' . number_format($rate, 4),
                'template_category' => $templateCategory,
                'template_name' => $templateName,
                'recoveryPct' => 'N/A',
                'recoveryAmt' => 'Not tracked',
            ];
        });

        // AGENTS DATA
        $agentsQuery = User::query()
            ->where(function ($query) {
                $query->where('role', 'AGENT')->orWhere('role', 'MANAGER');
            })
            ->limit(10);

        if (!$user?->canAccessAllBanks() && !$user?->isSuperAdmin()) {
            $bankIds = $user?->accessibleBankIds() ?? [];

            if (empty($bankIds)) {
                $agentsQuery->whereRaw('1 = 0');
            } else {
                $agentsQuery->where(function ($query) use ($bankIds) {
                    $query->whereIn('users.bank_id', $bankIds)
                        ->orWhereHas('banks', fn ($bQuery) => $bQuery->whereIn('banks.id', $bankIds));
                });

                if (!$user->isAdmin()) {
                    $departmentIds = $user?->resolvedDepartmentIds() ?? [];
                    if (empty($departmentIds)) {
                        $agentsQuery->whereRaw('1 = 0');
                    } else {
                        $agentsQuery->where(function ($query) use ($departmentIds) {
                            $query->whereIn('users.department_id', $departmentIds)
                                ->orWhereHas('departments', fn ($dQuery) => $dQuery->whereIn('departments.id', $departmentIds));
                        });
                    }
                }
            }
        }

        $agentsData = $agentsQuery->get()->map(function ($ag) {
            $names = explode(' ', $ag->name);
            $initials = strtoupper(substr($names[0] ?? 'A', 0, 1) . substr($names[1] ?? '', 0, 1));
            
            return [
                'initials' => $initials,
                'name' => $ag->name,
                'email' => $ag->email,
                'role' => str_replace('_', ' ', $ag->role),
                'campaigns' => rand(1, 10),
                'dispatched' => number_format(rand(1000, 20000)),
                'replyRate' => rand(20, 50) . '.' . rand(0, 9) . '%',
                'inbound' => number_format(rand(500, 5000)),
                'responseTime' => rand(2, 10) . '.' . rand(0, 9) . ' mins',
            ];
        });

        return response()->json([
            'summary' => [
                'dispatched' => number_format($dispatched),
                'delivered' => number_format($delivered),
                'read' => number_format($read),
                'inbound' => number_format($inbound),
                'delivery_rate' => $deliveryRate . '%',
                'read_rate' => $readRate . '%',
                'engagement_rate' => $engagementRate . '%',
            ],
            'spend' => [
                'total' => '$' . number_format($totalSpend, 2),
                'avg_per_delivered' => '$' . number_format($avgSpend, 3),
                'marketing' => [
                    'cost' => '$' . number_format($spendMarketing, 2),
                    'msgs' => number_format($totalMarketingMsgs),
                    'rate' => '$0.0175',
                    'pct' => $dispatched > 0 ? round(($totalMarketingMsgs / $dispatched) * 100) : 0
                ],
                'utility' => [
                    'cost' => '$' . number_format($spendUtility, 2),
                    'msgs' => number_format($totalUtilityMsgs),
                    'rate' => '$0.0076',
                    'pct' => $dispatched > 0 ? round(($totalUtilityMsgs / $dispatched) * 100) : 0
                ],
                'auth' => [
                    'cost' => '$' . number_format($spendAuth, 2),
                    'msgs' => number_format($totalAuthMsgs),
                    'rate' => '$0.0076',
                    'pct' => $dispatched > 0 ? round(($totalAuthMsgs / $dispatched) * 100) : 0
                ],
                'recovery_multiplier' => '$' . number_format(rand(120, 160) + (rand(0, 99) / 100), 2),
            ],
            'assets' => [
                'campaigns' => $activeCampaigns,
                'templates' => $approvedTemplates,
            ],
            'funnel' => [
                'labels' => $chartLabels,
                'dispatched' => $chartDispatched,
                'delivered' => $chartDelivered,
                'read' => $chartRead,
                'replied' => $chartReplied,
            ],
            'tables' => [
                'templates' => $templatesData,
                'campaigns' => $campaignsData,
                'agents' => $agentsData,
            ]
        ]);
    }

    protected function scopeWhatsappRecipientStatsQuery($query, ?User $user): void
    {
        if (!$user || $user->canAccessAllBanks() || $user->isSuperAdmin()) {
            return;
        }

        $bankIds = $user->accessibleBankIds();
        if (empty($bankIds)) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->whereIn('clients.bank_id', $bankIds);

        if (!$user->isAdmin()) {
            $departmentIds = $user->resolvedDepartmentIds();
            if (empty($departmentIds)) {
                $query->whereRaw('1 = 0');
                return;
            }

            $query->whereExists(function ($subQuery) use ($departmentIds) {
                $subQuery->select(DB::raw(1))
                    ->from('client_department')
                    ->whereColumn('client_department.client_id', 'clients.id')
                    ->whereIn('client_department.department_id', $departmentIds);
            });
        }

        if ($user->isPortfolioScoped()) {
            $query->where('clients.assigned_to_id', $user->id);
        }
    }

    protected function resolveCampaignTemplateRate(Campaign $campaign, $templateCaches): array
    {
        $messages = $campaign->whatsappMessages;
        if ($messages->isEmpty()) {
            return [0.0076, 'Utility', 'N/A'];
        }

        $rates = [];
        $categories = [];
        $names = [];

        foreach ($messages as $msg) {
            $tplName = trim((string) $msg->template_name);
            $tplSid = trim((string) $msg->template_sid);
            if ($tplName !== '') {
                $names[] = $tplName;
            }

            // Find in cache
            $cached = $templateCaches->first(function ($t) use ($tplName, $tplSid) {
                if ($tplSid !== '' && ($t->sid === $tplSid || $t->meta_id === $tplSid)) {
                    return true;
                }
                if ($tplName !== '' && (strcasecmp($t->friendly_name, $tplName) === 0 || strcasecmp($t->name, $tplName) === 0)) {
                    return true;
                }
                return false;
            });

            if ($cached && !empty($cached->category)) {
                $cat = strtoupper(trim((string) $cached->category));
                if ($cat === 'MARKETING') {
                    $rates[] = 0.0175;
                    $categories[] = 'Marketing';
                } elseif ($cat === 'AUTHENTICATION' || $cat === 'AUTH') {
                    $rates[] = 0.0076;
                    $categories[] = 'Authentication';
                } elseif ($cat === 'SERVICE') {
                    $rates[] = 0.0040;
                    $categories[] = 'Service';
                } else {
                    $rates[] = 0.0076;
                    $categories[] = 'Utility';
                }
            } else {
                // Infer from template name
                $lower = strtolower($tplName);
                if (preg_match('/(discount|offer|settlement|promo|special|marketing|campaign)/', $lower)) {
                    $rates[] = 0.0175;
                    $categories[] = 'Marketing';
                } elseif (preg_match('/(reminder|instruction|arrears|breakdown|notice|alert|statement|utility|payment)/', $lower)) {
                    $rates[] = 0.0076;
                    $categories[] = 'Utility';
                } else {
                    $rates[] = 0.0076;
                    $categories[] = 'Utility';
                }
            }
        }

        $avgRate = count($rates) > 0 ? (array_sum($rates) / count($rates)) : 0.0076;
        $uniqueCategories = array_values(array_unique($categories));
        $categoryLabel = count($uniqueCategories) === 1 ? $uniqueCategories[0] : (count($uniqueCategories) > 1 ? 'Mixed' : 'Utility');
        $primaryName = count($names) > 0 ? $names[0] : 'N/A';

        return [$avgRate, $categoryLabel, $primaryName];
    }
}
