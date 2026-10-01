<?php

namespace App\Http\Controllers\Api;

use App\Concerns\AppliesAccessScopes;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Campaign;
use App\Models\CampaignWhatsappRecipient;
use App\Models\ChatSession;
use App\Models\Client;
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
        } elseif ($dateRange === 'last_30_days' || $dateRange === '30_days') {
            $startDate = Carbon::now()->subDays(29)->startOfDay();
        } elseif ($dateRange === 'this_month') {
            $startDate = Carbon::now()->startOfMonth();
        } elseif ($dateRange === 'last_90_days' || $dateRange === '3_months' || $dateRange === '3_month') {
            $startDate = Carbon::now()->subMonths(3)->startOfDay();
        } elseif ($dateRange === '6_months' || $dateRange === '6_month') {
            $startDate = Carbon::now()->subMonths(6)->startOfDay();
        } elseif ($dateRange === '1_year' || $dateRange === '1_years' || $dateRange === '12_months') {
            $startDate = Carbon::now()->subYear()->startOfDay();
        } elseif ($dateRange === '2_years' || $dateRange === '2_year' || $dateRange === '24_months') {
            $startDate = Carbon::now()->subYears(2)->startOfDay();
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

        // 1. CAMPAIGNS DATA (Computed first so all KPIs, spend, templates and agent metrics are reconciled)
        $templateCaches = WhatsappTemplateCache::all();

        $campaignsQuery = Campaign::with(['bank', 'whatsappMessages.createdBy:id,name'])
            ->orderBy('created_at', 'desc');

        if ($selectedBankId) {
            $campaignsQuery->where('campaigns.bank_id', $selectedBankId);
        }

        $campaignDateScope = $request->query('campaign_date_scope', 'filter');
        if ($campaignDateScope !== 'all') {
            if ($startDate && $endDate) {
                $campaignsQuery->where(function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('campaigns.created_at', [$startDate, $endDate])
                      ->orWhereHas('whatsappMessages', function ($mq) use ($startDate, $endDate) {
                          $mq->whereBetween('created_at', [$startDate, $endDate]);
                      });
                });
            } elseif ($startDate) {
                $campaignsQuery->where(function ($q) use ($startDate) {
                    $q->where('campaigns.created_at', '>=', $startDate)
                      ->orWhereHas('whatsappMessages', function ($mq) use ($startDate) {
                          $mq->where('created_at', '>=', $startDate);
                      });
                });
            } elseif ($endDate) {
                $campaignsQuery->where(function ($q) use ($endDate) {
                    $q->where('campaigns.created_at', '<=', $endDate)
                      ->orWhereHas('whatsappMessages', function ($mq) use ($endDate) {
                          $mq->where('created_at', '<=', $endDate);
                      });
                });
            }
        }

        $limit = $request->query('limit');
        if ($limit && is_numeric($limit) && (int) $limit > 0) {
            $campaignsQuery->limit((int) $limit);
        }

        $this->scopeCampaignQueryToUser($campaignsQuery, $user);
        $campaignsCollection = $campaignsQuery->get();

        $campaignReports = [];
        $totalMarketingMsgs = 0;
        $totalUtilityMsgs = 0;
        $totalAuthMsgs = 0;
        $spendMarketing = 0.0;
        $spendUtility = 0.0;
        $spendAuth = 0.0;

        $campaignsData = $campaignsCollection->map(function ($cmp) use ($user, $templateCaches, &$campaignReports, &$totalMarketingMsgs, &$totalUtilityMsgs, &$totalAuthMsgs, &$spendMarketing, &$spendUtility, &$spendAuth) {
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
            $campaignReports[$cmp->id] = $report;
            $sent = $report['messages_sent'];
            $accepted = $report['messages_accepted'];
            [$rate, $templateCategory, $templateName] = $this->resolveCampaignTemplateRate($cmp, $templateCaches);
            $cost = $accepted * $rate;

            $catLower = strtolower($templateCategory);
            if (str_contains($catLower, 'marketing')) {
                $totalMarketingMsgs += $accepted;
                $spendMarketing += $cost;
            } elseif (str_contains($catLower, 'auth') || str_contains($catLower, 'service')) {
                $totalAuthMsgs += $accepted;
                $spendAuth += $cost;
            } else {
                $totalUtilityMsgs += $accepted;
                $spendUtility += $cost;
            }

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

        // 2. OVERALL STATISTICS
        $statsQuery = DB::table('campaign_whatsapp_recipients')
            ->join('clients', 'campaign_whatsapp_recipients.client_id', '=', 'clients.id')
            ->selectRaw('
                COUNT(*) as total_dispatched,
                SUM(CASE 
                    WHEN LOWER(campaign_whatsapp_recipients.status) IN ("delivered", "read", "delivered (ecosystem warning)", "sent", "accepted", "pending", "queued", "processing", "scheduled")
                         OR campaign_whatsapp_recipients.error_code = "131049"
                         OR campaign_whatsapp_recipients.error_message LIKE "%maintain healthy ecosystem engagement%"
                    THEN 1 ELSE 0 END
                ) as total_delivered,
                SUM(CASE WHEN LOWER(campaign_whatsapp_recipients.status) = "read" THEN 1 ELSE 0 END) as total_read,
                SUM(CASE WHEN campaign_whatsapp_recipients.last_response IS NOT NULL OR campaign_whatsapp_recipients.reply_type IS NOT NULL THEN 1 ELSE 0 END) as total_replied
            ')
            ->where(function ($q) {
                $q->whereIn(DB::raw('LOWER(campaign_whatsapp_recipients.status)'), [
                    'sent',
                    'accepted',
                    'delivered',
                    'read',
                    'delivered (ecosystem warning)',
                    'failed',
                    'pending',
                    'queued',
                    'processing',
                    'scheduled',
                ])
                ->orWhere('campaign_whatsapp_recipients.error_code', '131049')
                ->orWhere('campaign_whatsapp_recipients.error_message', 'like', '%maintain healthy ecosystem engagement%');
            });

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
        $replied = (int) ($stats->total_replied ?? 0);

        // Combine with ChatSessions for inbound engaged
        $chatInboundQuery = ChatSession::where('platform', 'whatsapp');
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
        $inbound = max($replied, $chatInbound);

        $deliveryRate = $dispatched > 0 ? round(($delivered / $dispatched) * 100, 1) : 0;
        $readRate = $delivered > 0 ? round(($read / $delivered) * 100, 1) : 0;
        $engagementRate = $dispatched > 0 ? round(($inbound / $dispatched) * 100, 1) : 0;

        // 3. SPEND & RECOVERY
        $totalSpend = $spendMarketing + $spendUtility + $spendAuth;
        $avgSpend = $delivered > 0 ? round($totalSpend / $delivered, 3) : 0;
        $totalAccepted = $totalMarketingMsgs + $totalUtilityMsgs + $totalAuthMsgs;

        $recoveryClientsQuery = Client::whereIn('payment_option', [Client::PAYMENT_OPTION_PTP, Client::PAYMENT_OPTION_DEBIT_ORDER]);
        if ($selectedBankId) {
            $recoveryClientsQuery->where('bank_id', $selectedBankId);
        }
        $recoveryAmount = (float) $recoveryClientsQuery->sum(DB::raw('COALESCE(ptp_amount, settlement_amount, 0)'));
        if ($recoveryAmount <= 0) {
            $recoveryAmount = (float) $recoveryClientsQuery->sum(DB::raw('COALESCE(settlement_amount, arrears_amount, 0)'));
        }
        $recoveryMultiplier = ($totalSpend > 0 && $recoveryAmount > 0) ? round($recoveryAmount / $totalSpend, 2) : 0.00;

        // 4. ASSETS
        $activeCampaignsQuery = Campaign::where('status', 'Active');
        if ($selectedBankId) {
            $activeCampaignsQuery->where('bank_id', $selectedBankId);
        }
        $this->scopeCampaignQueryToUser($activeCampaignsQuery, $user);
        $activeCampaigns = $activeCampaignsQuery->count();
        $approvedTemplates = WhatsappTemplateCache::where('status', 'APPROVED')->count();

        // 5. CHART DATA
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
                    SUM(CASE 
                        WHEN LOWER(campaign_whatsapp_recipients.status) IN ("delivered", "read", "delivered (ecosystem warning)", "sent", "accepted", "pending", "queued", "processing", "scheduled")
                             OR campaign_whatsapp_recipients.error_code = "131049"
                             OR campaign_whatsapp_recipients.error_message LIKE "%maintain healthy ecosystem engagement%"
                        THEN 1 ELSE 0 END
                    ) as delivered,
                    SUM(CASE WHEN LOWER(campaign_whatsapp_recipients.status) = "read" THEN 1 ELSE 0 END) as read_count,
                    SUM(CASE WHEN campaign_whatsapp_recipients.last_response IS NOT NULL OR campaign_whatsapp_recipients.reply_type IS NOT NULL THEN 1 ELSE 0 END) as replied
                ')
                ->where('campaign_whatsapp_recipients.created_at', '>=', Carbon::now()->subMonths($monthsBack - 1)->startOfMonth())
                ->where(function ($q) {
                    $q->whereIn(DB::raw('LOWER(campaign_whatsapp_recipients.status)'), [
                        'sent',
                        'accepted',
                        'delivered',
                        'read',
                        'delivered (ecosystem warning)',
                        'failed',
                        'pending',
                        'queued',
                        'processing',
                        'scheduled',
                    ])
                    ->orWhere('campaign_whatsapp_recipients.error_code', '131049')
                    ->orWhere('campaign_whatsapp_recipients.error_message', 'like', '%maintain healthy ecosystem engagement%');
                });

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
                    SUM(CASE 
                        WHEN LOWER(campaign_whatsapp_recipients.status) IN ("delivered", "read", "delivered (ecosystem warning)", "sent", "accepted", "pending", "queued", "processing", "scheduled")
                             OR campaign_whatsapp_recipients.error_code = "131049"
                             OR campaign_whatsapp_recipients.error_message LIKE "%maintain healthy ecosystem engagement%"
                        THEN 1 ELSE 0 END
                    ) as delivered,
                    SUM(CASE WHEN LOWER(campaign_whatsapp_recipients.status) = "read" THEN 1 ELSE 0 END) as read_count,
                    SUM(CASE WHEN campaign_whatsapp_recipients.last_response IS NOT NULL OR campaign_whatsapp_recipients.reply_type IS NOT NULL THEN 1 ELSE 0 END) as replied
                ')
                ->where('campaign_whatsapp_recipients.created_at', '>=', Carbon::now()->subWeeks($weeksBack - 1)->startOfWeek())
                ->where(function ($q) {
                    $q->whereIn(DB::raw('LOWER(campaign_whatsapp_recipients.status)'), [
                        'sent',
                        'accepted',
                        'delivered',
                        'read',
                        'delivered (ecosystem warning)',
                        'failed',
                        'pending',
                        'queued',
                        'processing',
                        'scheduled',
                    ])
                    ->orWhere('campaign_whatsapp_recipients.error_code', '131049')
                    ->orWhere('campaign_whatsapp_recipients.error_message', 'like', '%maintain healthy ecosystem engagement%');
                });

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
                $periodStr = $date->format('oW');
                $chartLabels[] = 'Week of ' . $date->startOfWeek()->format('M d');
                
                $data = $statsWeekly->firstWhere('period', $periodStr);
                $chartDispatched[] = $data ? (int) $data->dispatched : 0;
                $chartDelivered[] = $data ? (int) $data->delivered : 0;
                $chartRead[] = $data ? (int) $data->read_count : 0;
                $chartReplied[] = $data ? (int) $data->replied : 0;
            }
        } else {
            $daysBack = 30;
            
            $chartQuery = DB::table('campaign_whatsapp_recipients')
                ->join('clients', 'campaign_whatsapp_recipients.client_id', '=', 'clients.id')
                ->selectRaw('
                    DATE(campaign_whatsapp_recipients.created_at) as period,
                    COUNT(*) as dispatched,
                    SUM(CASE 
                        WHEN LOWER(campaign_whatsapp_recipients.status) IN ("delivered", "read", "delivered (ecosystem warning)", "sent", "accepted", "pending", "queued", "processing", "scheduled")
                             OR campaign_whatsapp_recipients.error_code = "131049"
                             OR campaign_whatsapp_recipients.error_message LIKE "%maintain healthy ecosystem engagement%"
                        THEN 1 ELSE 0 END
                    ) as delivered,
                    SUM(CASE WHEN LOWER(campaign_whatsapp_recipients.status) = "read" THEN 1 ELSE 0 END) as read_count,
                    SUM(CASE WHEN campaign_whatsapp_recipients.last_response IS NOT NULL OR campaign_whatsapp_recipients.reply_type IS NOT NULL THEN 1 ELSE 0 END) as replied
                ')
                ->where('campaign_whatsapp_recipients.created_at', '>=', Carbon::now()->subDays($daysBack - 1)->startOfDay())
                ->where(function ($q) {
                    $q->whereIn(DB::raw('LOWER(campaign_whatsapp_recipients.status)'), [
                        'sent',
                        'accepted',
                        'delivered',
                        'read',
                        'delivered (ecosystem warning)',
                        'failed',
                        'pending',
                        'queued',
                        'processing',
                        'scheduled',
                    ])
                    ->orWhere('campaign_whatsapp_recipients.error_code', '131049')
                    ->orWhere('campaign_whatsapp_recipients.error_message', 'like', '%maintain healthy ecosystem engagement%');
                });

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

        // 6. TEMPLATES DATA
        $templateStatsQuery = DB::table('campaign_whatsapp_messages')
            ->join('campaign_whatsapp_recipients', 'campaign_whatsapp_recipients.whatsapp_message_id', '=', 'campaign_whatsapp_messages.id')
            ->join('campaigns', 'campaigns.id', '=', 'campaign_whatsapp_messages.campaign_id')
            ->selectRaw("
                campaign_whatsapp_messages.template_sid,
                campaign_whatsapp_messages.template_name,
                COUNT(*) as total_sent,
                SUM(CASE 
                    WHEN LOWER(campaign_whatsapp_recipients.status) IN ('delivered', 'read', 'delivered (ecosystem warning)', 'sent', 'accepted', 'pending', 'queued', 'processing', 'scheduled')
                         OR campaign_whatsapp_recipients.error_code = '131049'
                         OR campaign_whatsapp_recipients.error_message LIKE '%maintain healthy ecosystem engagement%'
                    THEN 1 ELSE 0 END
                ) as total_delivered,
                SUM(CASE 
                    WHEN LOWER(campaign_whatsapp_recipients.status) IN ('sent', 'accepted', 'delivered', 'read', 'delivered (ecosystem warning)', 'pending', 'queued', 'processing', 'scheduled')
                         OR campaign_whatsapp_recipients.error_code = '131049'
                         OR campaign_whatsapp_recipients.error_message LIKE '%maintain healthy ecosystem engagement%'
                    THEN 1 ELSE 0 END
                ) as total_accepted,
                SUM(CASE 
                    WHEN campaign_whatsapp_recipients.last_response IS NOT NULL OR campaign_whatsapp_recipients.reply_type IS NOT NULL 
                    THEN 1 ELSE 0 END
                ) as total_replied
            ");

        if ($selectedBankId) {
            $templateStatsQuery->where('campaigns.bank_id', $selectedBankId);
        }
        if ($startDate) {
            $templateStatsQuery->where('campaign_whatsapp_recipients.created_at', '>=', $startDate);
        }
        if ($endDate) {
            $templateStatsQuery->where('campaign_whatsapp_recipients.created_at', '<=', $endDate);
        }
        $templateStats = $templateStatsQuery
            ->groupBy('campaign_whatsapp_messages.template_sid', 'campaign_whatsapp_messages.template_name')
            ->get();

        $templateCampaignsQuery = DB::table('campaign_whatsapp_messages')
            ->join('campaigns', 'campaigns.id', '=', 'campaign_whatsapp_messages.campaign_id')
            ->leftJoin('banks', 'banks.id', '=', 'campaigns.bank_id')
            ->select('campaign_whatsapp_messages.template_sid', 'campaign_whatsapp_messages.template_name', 'campaigns.name as campaign_name', 'banks.name as bank_name')
            ->distinct();

        if ($selectedBankId) {
            $templateCampaignsQuery->where('campaigns.bank_id', $selectedBankId);
        }
        $templateCampaigns = $templateCampaignsQuery->get();

        $templatesData = [];
        $matchedTemplateKeys = [];

        foreach ($templateCaches as $tpl) {
            $statMatches = $templateStats->filter(function ($s) use ($tpl) {
                return ($s->template_sid && ($s->template_sid === $tpl->sid || $s->template_sid === $tpl->friendly_name))
                    || ($s->template_name && ($s->template_name === $tpl->friendly_name || $s->template_name === $tpl->sid));
            });
            $campMatches = $templateCampaigns->filter(function ($c) use ($tpl) {
                return ($c->template_sid && ($c->template_sid === $tpl->sid || $c->template_sid === $tpl->friendly_name))
                    || ($c->template_name && ($c->template_name === $tpl->friendly_name || $c->template_name === $tpl->sid));
            });

            foreach ($statMatches as $sm) {
                $key = $sm->template_sid ?: $sm->template_name;
                if ($key) {
                    $matchedTemplateKeys[$key] = true;
                }
            }

            $sent = (int) $statMatches->sum('total_sent');
            $accepted = (int) $statMatches->sum('total_accepted');
            $deliveredTpl = (int) $statMatches->sum('total_delivered');
            $repliedTpl = (int) $statMatches->sum('total_replied');
            $cNames = $campMatches->pluck('campaign_name')->filter()->unique()->values()->all();
            $bankName = $campMatches->pluck('bank_name')->filter()->first();

            $templatesData[] = $this->formatTemplateRow(
                $tpl->friendly_name ?: ($tpl->sid ?: 'Unnamed Template'),
                $tpl->sid ?: ($tpl->meta_id ?: 'N/A'),
                (string) ($tpl->category ?? 'UTILITY'),
                (string) ($tpl->status ?: 'APPROVED'),
                $sent,
                $accepted,
                $deliveredTpl,
                $repliedTpl,
                $cNames,
                $bankName
            );
        }

        // Include any uncached templates used in campaigns
        foreach ($templateStats as $s) {
            $k = $s->template_sid ?: $s->template_name;
            if ($k && isset($matchedTemplateKeys[$k])) {
                continue;
            }

            $campMatches = $templateCampaigns->filter(function ($c) use ($s) {
                return ($c->template_sid && $c->template_sid === $s->template_sid)
                    || ($c->template_name && $c->template_name === $s->template_name);
            });

            $sent = (int) $s->total_sent;
            $accepted = (int) $s->total_accepted;
            $deliveredTpl = (int) $s->total_delivered;
            $repliedTpl = (int) $s->total_replied;
            $cNames = $campMatches->pluck('campaign_name')->filter()->unique()->values()->all();
            $bankName = $campMatches->pluck('bank_name')->filter()->first();

            $name = $s->template_name ?: ($s->template_sid ?: 'Custom Template');
            $cat = preg_match('/(discount|offer|settlement|promo|special|marketing)/i', $name) ? 'Marketing' : 'Utility';

            $templatesData[] = $this->formatTemplateRow(
                $name,
                $s->template_sid ?: 'N/A',
                $cat,
                'APPROVED',
                $sent,
                $accepted,
                $deliveredTpl,
                $repliedTpl,
                $cNames,
                $bankName
            );
        }

        // 7. AGENTS DATA
        $agentStatsQuery = DB::table('campaign_whatsapp_messages')
            ->join('campaign_whatsapp_recipients', 'campaign_whatsapp_recipients.whatsapp_message_id', '=', 'campaign_whatsapp_messages.id')
            ->join('campaigns', 'campaigns.id', '=', 'campaign_whatsapp_messages.campaign_id')
            ->selectRaw("
                campaign_whatsapp_messages.created_by_user_id,
                COUNT(DISTINCT campaign_whatsapp_messages.campaign_id) as campaigns_count,
                COUNT(*) as total_sent,
                SUM(CASE 
                    WHEN LOWER(campaign_whatsapp_recipients.status) IN ('delivered', 'read', 'delivered (ecosystem warning)', 'sent', 'accepted', 'pending', 'queued', 'processing', 'scheduled')
                         OR campaign_whatsapp_recipients.error_code = '131049'
                         OR campaign_whatsapp_recipients.error_message LIKE '%maintain healthy ecosystem engagement%'
                    THEN 1 ELSE 0 END
                ) as total_delivered,
                SUM(CASE 
                    WHEN campaign_whatsapp_recipients.last_response IS NOT NULL OR campaign_whatsapp_recipients.reply_type IS NOT NULL 
                    THEN 1 ELSE 0 END
                ) as total_replied
            ")
            ->whereNotNull('campaign_whatsapp_messages.created_by_user_id');

        if ($selectedBankId) {
            $agentStatsQuery->where('campaigns.bank_id', $selectedBankId);
        }
        if ($startDate) {
            $agentStatsQuery->where('campaign_whatsapp_recipients.created_at', '>=', $startDate);
        }
        if ($endDate) {
            $agentStatsQuery->where('campaign_whatsapp_recipients.created_at', '<=', $endDate);
        }
        $agentStats = $agentStatsQuery
            ->groupBy('campaign_whatsapp_messages.created_by_user_id')
            ->get()
            ->keyBy('created_by_user_id');

        $chatSessionsByAgent = DB::table('chat_sessions')
            ->selectRaw('agent_id, COUNT(*) as total_chats, SUM(unread_count) as total_unread')
            ->whereNotNull('agent_id')
            ->groupBy('agent_id')
            ->get()
            ->keyBy('agent_id');

        $agentsQuery = User::query()
            ->whereIn('role', [
                User::ROLE_SUPER_ADMIN,
                User::ROLE_ADMIN,
                'STAFF',
                'AGENT',
                'MANAGER',
            ]);

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

        $agentsData = $agentsQuery->get()->map(function ($ag) use ($agentStats, $chatSessionsByAgent) {
            $names = preg_split('/\s+/', trim((string) $ag->name)) ?: [];
            $initials = strtoupper(substr($names[0] ?? 'A', 0, 1) . substr($names[1] ?? '', 0, 1));
            
            $stats = $agentStats->get($ag->id);
            $chatStats = $chatSessionsByAgent->get($ag->id);

            $campaignsCount = (int) ($stats->campaigns_count ?? 0);
            $dispatchedCount = (int) ($stats->total_sent ?? 0);
            $repliedCount = (int) ($stats->total_replied ?? 0);
            $inboundChats = (int) ($chatStats->total_chats ?? 0);
            $inboundCount = max($repliedCount, $inboundChats);

            $replyRate = $dispatchedCount > 0 ? number_format(round(($repliedCount / $dispatchedCount) * 100, 1), 1) . '%' : '0.0%';
            $responseTime = ($inboundCount > 0 || $dispatchedCount > 0) ? '< 5 mins' : '—';

            return [
                'initials' => $initials,
                'name' => $ag->name,
                'email' => $ag->email,
                'role' => ucwords(strtolower(str_replace('_', ' ', (string) $ag->role))),
                'campaigns' => $campaignsCount,
                'dispatched' => number_format($dispatchedCount),
                '_dispatched_raw' => $dispatchedCount,
                'replyRate' => $replyRate,
                'inbound' => number_format($inboundCount),
                '_inbound_raw' => $inboundCount,
                'responseTime' => $responseTime,
            ];
        })->sortByDesc(function ($ag) {
            return ($ag['_dispatched_raw'] * 1000) + $ag['_inbound_raw'];
        })->values()->map(function ($ag) {
            unset($ag['_dispatched_raw'], $ag['_inbound_raw']);
            return $ag;
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
                    'pct' => $totalAccepted > 0 ? round(($totalMarketingMsgs / $totalAccepted) * 100) : 0
                ],
                'utility' => [
                    'cost' => '$' . number_format($spendUtility, 2),
                    'msgs' => number_format($totalUtilityMsgs),
                    'rate' => '$0.0076',
                    'pct' => $totalAccepted > 0 ? round(($totalUtilityMsgs / $totalAccepted) * 100) : 0
                ],
                'auth' => [
                    'cost' => '$' . number_format($spendAuth, 2),
                    'msgs' => number_format($totalAuthMsgs),
                    'rate' => '$0.0076',
                    'pct' => $totalAccepted > 0 ? round(($totalAuthMsgs / $totalAccepted) * 100) : 0
                ],
                'recovery_multiplier' => '$' . number_format($recoveryMultiplier, 2),
                'recovery_amount' => '$' . number_format($recoveryAmount, 2),
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

    protected function formatTemplateRow(
        string $name,
        string $id,
        string $category,
        string $status,
        int $sent,
        int $accepted,
        int $delivered,
        int $replied,
        array $campaignNames,
        ?string $bankName = null
    ): array {
        $catUpper = strtoupper(trim($category ?: 'UTILITY'));
        $rate = match ($catUpper) {
            'MARKETING' => 0.0175,
            'AUTHENTICATION', 'AUTH' => 0.0076,
            'SERVICE' => 0.0040,
            default => 0.0076,
        };
        $categoryLabel = ucfirst(strtolower($catUpper ?: 'utility'));

        $campaignCount = count($campaignNames);
        if ($campaignCount === 1) {
            $campaignDisplay = $campaignNames[0];
            $subCampaignDisplay = $bankName ?: '—';
        } elseif ($campaignCount > 1) {
            $campaignDisplay = "{$campaignCount} Campaigns";
            $subCampaignDisplay = implode(', ', array_slice($campaignNames, 0, 2)) . ($campaignCount > 2 ? '...' : '');
        } else {
            $campaignDisplay = 'Not used yet';
            $subCampaignDisplay = '—';
        }

        $deliveryRate = $sent > 0 ? round(($delivered / $sent) * 100, 1) : 0.0;
        $replyRate = $sent > 0 ? round(($replied / $sent) * 100, 1) : 0.0;
        $cost = $accepted * $rate;

        return [
            'name' => $name,
            'id' => $id,
            'category' => $categoryLabel,
            'campaign' => $campaignDisplay,
            'sub_campaign' => $subCampaignDisplay,
            'sent' => number_format($sent),
            'delivery' => number_format($deliveryRate, 1) . '%',
            'reply' => number_format($replyRate, 1) . '%',
            'rate' => '$' . number_format($rate, 4),
            'cost' => '$' . number_format($cost, 2),
            'status' => $status,
        ];
    }
}
