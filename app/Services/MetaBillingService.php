<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\CampaignWhatsappMessage;
use App\Models\CampaignWhatsappRecipient;
use App\Models\SystemSetting;
use App\Models\WhatsappTemplateCache;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaBillingService
{
    protected string $baseUrl = 'https://graph.facebook.com/v21.0';
    protected ?SystemSetting $settings;
    protected ?string $token;
    protected ?string $wabaId;
    protected ?string $appId;
    protected ?string $appSecret;
    protected ?string $adAccountId;

    public function __construct()
    {
        $this->settings = SystemSetting::first();
        $this->token = $this->settings?->meta_access_token;
        $this->wabaId = $this->settings?->meta_whatsapp_business_account_id;
        $this->appId = $this->settings?->meta_app_id;
        $this->appSecret = $this->settings?->meta_app_secret;
        $this->adAccountId = $this->settings?->meta_ad_account_id;
    }

    /**
     * Get complete billing and usage telemetry.
     */
    public function getBillingOverview(array $params = [], bool $forceRefresh = false): array
    {
        $dateRange = $params['date_range'] ?? 'last_30_days';
        $bankId = $params['bank_id'] ?? null;
        if ($bankId === 'all' || $bankId === '' || $bankId === 0 || $bankId === '0') {
            $bankId = null;
        }

        $dateBounds = $this->resolveDateBounds($dateRange);
        $startTimestamp = $dateBounds['start'] ? $dateBounds['start']->timestamp : Carbon::now()->subDays(30)->timestamp;
        $endTimestamp = $dateBounds['end'] ? $dateBounds['end']->timestamp : Carbon::now()->timestamp;

        if ($forceRefresh) {
            $this->clearBillingCache();
        }

        $liveWaba = $this->fetchLiveWabaAccountDetails();
        $liveTemplateAnalytics = $this->fetchMetaTemplateAnalytics($startTimestamp, $endTimestamp);
        $adAccountData = $this->fetchAdAccountInsights($this->adAccountId, $dateRange, $dateBounds['start'], $dateBounds['end']);
        $whatsappBilling = $this->getReconciledWhatsAppCharges($dateBounds['start'], $dateBounds['end'], $bankId);

        $ownerBusinessId = $liveWaba['owner_business']['id'] ?? '1323375205187636';

        return [
            'meta_config' => [
                'system_name' => $this->settings?->app_name ?: 'SR Solution',
                'app_short_name' => $this->settings?->app_short_name ?: 'SR',
                'app_id' => $this->appId,
                'app_name' => $liveWaba['app']['name'] ?? 'CRM System API',
                'app_category' => $liveWaba['app']['category'] ?? 'Business',
                'waba_id' => $this->wabaId,
                'waba_name' => $liveWaba['waba']['name'] ?? 'Iconis CRM',
                'currency' => $liveWaba['waba']['currency'] ?? 'USD',
                'timezone' => $liveWaba['waba']['timezone_id'] ?? '141',
                'status' => $liveWaba['waba']['status'] ?? 'ACTIVE',
                'review_status' => $liveWaba['waba']['account_review_status'] ?? 'APPROVED',
                'owner_business' => $liveWaba['owner_business'] ?? null,
                'phone_numbers' => $liveWaba['phone_numbers'] ?? [],
                'subscribed_apps' => $liveWaba['subscribed_apps'] ?? [],
                'ad_account_id' => $this->adAccountId,
            ],
            'whatsapp_billing' => $whatsappBilling,
            'template_analytics' => $liveTemplateAnalytics,
            'ad_account_insights' => $adAccountData,
            'quick_links' => [
                'billing_hub' => "https://business.facebook.com/billing_hub/payment_settings?business_id={$ownerBusinessId}",
                'invoices' => "https://business.facebook.com/billing_hub/accounts?business_id={$ownerBusinessId}",
                'whatsapp_manager' => "https://business.facebook.com/wa/manage/home/?waba_id={$this->wabaId}",
                'app_dashboard' => "https://developers.facebook.com/apps/{$this->appId}/whatsapp-business/overview/",
                'system_users' => "https://business.facebook.com/settings/system-users?business_id={$ownerBusinessId}",
            ],
            'synced_at' => Carbon::now()->toIso8601String(),
        ];
    }

    /**
     * Clear cached Meta telemetry.
     */
    public function clearBillingCache(): void
    {
        Cache::forget('meta_billing_waba_details_' . $this->wabaId);
        Cache::forget('meta_billing_ad_insights_' . $this->adAccountId);
        Cache::forget('meta_billing_template_analytics_' . $this->wabaId);
    }

    /**
     * Fetch Live WABA & App Details directly from Meta Graph API.
     */
    public function fetchLiveWabaAccountDetails(): array
    {
        $cacheKey = 'meta_billing_waba_details_' . $this->wabaId;

        return Cache::remember($cacheKey, 300, function () {
            $details = [
                'waba' => [
                    'id' => $this->wabaId,
                    'name' => 'Iconis CRM',
                    'currency' => 'USD',
                    'timezone_id' => '141',
                    'status' => 'ACTIVE',
                    'account_review_status' => 'APPROVED',
                ],
                'app' => [
                    'id' => $this->appId,
                    'name' => 'CRM System API',
                    'category' => 'Business',
                ],
                'owner_business' => [
                    'id' => '1323375205187636',
                    'name' => 'ICON INFORMATION SYSTEMS',
                    'verification_status' => 'verified',
                ],
                'phone_numbers' => [],
                'subscribed_apps' => [],
            ];

            if (empty($this->token) || empty($this->wabaId)) {
                return $details;
            }

            try {
                // 1. WABA Info
                $wabaRes = Http::withToken($this->token)->timeout(10)->get("{$this->baseUrl}/{$this->wabaId}", [
                    'fields' => 'id,name,currency,timezone_id,message_template_namespace,status,account_review_status,owner_business_info',
                ]);
                if ($wabaRes->successful()) {
                    $wabaData = $wabaRes->json();
                    $details['waba']['id'] = $wabaData['id'] ?? $this->wabaId;
                    $details['waba']['name'] = $wabaData['name'] ?? 'Iconis CRM';
                    $details['waba']['currency'] = $wabaData['currency'] ?? 'USD';
                    $details['waba']['timezone_id'] = $wabaData['timezone_id'] ?? '141';
                    $details['waba']['status'] = $wabaData['status'] ?? 'ACTIVE';
                    $details['waba']['account_review_status'] = $wabaData['account_review_status'] ?? 'APPROVED';

                    if (!empty($wabaData['owner_business_info'])) {
                        $details['owner_business']['id'] = $wabaData['owner_business_info']['id'] ?? '1323375205187636';
                        $details['owner_business']['name'] = $wabaData['owner_business_info']['name'] ?? 'ICON INFORMATION SYSTEMS';
                    }
                }

                // 2. App Info
                if ($this->appId) {
                    $appRes = Http::withToken($this->token)->timeout(10)->get("{$this->baseUrl}/{$this->appId}", [
                        'fields' => 'id,name,category,link',
                    ]);
                    if ($appRes->successful()) {
                        $appData = $appRes->json();
                        $details['app']['id'] = $appData['id'] ?? $this->appId;
                        $details['app']['name'] = $appData['name'] ?? 'CRM System API';
                        $details['app']['category'] = $appData['category'] ?? 'Business';
                    }
                }

                // 3. Registered Phone Numbers
                $phoneRes = Http::withToken($this->token)->timeout(10)->get("{$this->baseUrl}/{$this->wabaId}/phone_numbers");
                if ($phoneRes->successful()) {
                    $details['phone_numbers'] = $phoneRes->json('data') ?? [];
                }

                // 4. Subscribed Apps
                $subRes = Http::withToken($this->token)->timeout(10)->get("{$this->baseUrl}/{$this->wabaId}/subscribed_apps");
                if ($subRes->successful()) {
                    $details['subscribed_apps'] = $subRes->json('data') ?? [];
                }

                // 5. Business verification
                if (!empty($details['owner_business']['id'])) {
                    $bizRes = Http::withToken($this->token)->timeout(10)->get("{$this->baseUrl}/{$details['owner_business']['id']}", [
                        'fields' => 'id,name,verification_status',
                    ]);
                    if ($bizRes->successful()) {
                        $details['owner_business']['verification_status'] = $bizRes->json('verification_status') ?? 'verified';
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Error fetching live WABA details from Meta: ' . $e->getMessage());
            }

            return $details;
        });
    }

    /**
     * Fetch Live Meta Template Analytics for active templates.
     */
    public function fetchMetaTemplateAnalytics(int $startTimestamp, int $endTimestamp): array
    {
        $cacheKey = "meta_billing_template_analytics_{$this->wabaId}_{$startTimestamp}_{$endTimestamp}";

        return Cache::remember($cacheKey, 600, function () use ($startTimestamp, $endTimestamp) {
            if (empty($this->token) || empty($this->wabaId)) {
                return ['available' => false, 'templates' => []];
            }

            try {
                $templatesWithMetaId = WhatsappTemplateCache::query()
                    ->whereNotNull('meta_id')
                    ->where('status', 'APPROVED')
                    ->limit(10)
                    ->get();

                if ($templatesWithMetaId->isEmpty()) {
                    return ['available' => false, 'templates' => []];
                }

                $metaIds = $templatesWithMetaId->pluck('meta_id')->all();

                $response = Http::withToken($this->token)->timeout(15)->get("{$this->baseUrl}/{$this->wabaId}/template_analytics", [
                    'start' => $startTimestamp,
                    'end' => $endTimestamp,
                    'granularity' => 'DAILY',
                    'template_ids' => $metaIds,
                ]);

                if ($response->successful()) {
                    $data = $response->json('data') ?? [];
                    $templateMap = [];

                    foreach ($data as $entry) {
                        $points = $entry['data_points'] ?? [];
                        foreach ($points as $dp) {
                            $tId = (string) ($dp['template_id'] ?? '');
                            if (!isset($templateMap[$tId])) {
                                $templateMap[$tId] = [
                                    'template_id' => $tId,
                                    'sent' => 0,
                                    'delivered' => 0,
                                    'read' => 0,
                                    'replied' => 0,
                                    'data_points_count' => 0,
                                ];
                            }
                            $templateMap[$tId]['sent'] += (int) ($dp['sent'] ?? 0);
                            $templateMap[$tId]['delivered'] += (int) ($dp['delivered'] ?? 0);
                            $templateMap[$tId]['read'] += (int) ($dp['read'] ?? 0);
                            $templateMap[$tId]['replied'] += (int) ($dp['replied'] ?? 0);
                            $templateMap[$tId]['data_points_count']++;
                        }
                    }

                    return [
                        'available' => true,
                        'templates' => array_values($templateMap),
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('Error querying Meta template analytics: ' . $e->getMessage());
            }

            return ['available' => false, 'templates' => []];
        });
    }

    /**
     * Fetch Ad Account Insights & Marketing API Spend.
     */
    public function fetchAdAccountInsights(?string $adAccountId = null, string $datePreset = 'last_30_days', ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $targetAdAccountId = $adAccountId ?: ($this->adAccountId ?: SystemSetting::first()?->meta_ad_account_id);

        $permissionCheck = $this->checkAdPermissions();

        if (!$permissionCheck['has_ads_permission']) {
            return [
                'has_permission' => false,
                'permission_status' => 'missing_ads_read',
                'message' => 'To pull live Meta Ad Account spend, impressions, clicks, and campaign performance, the access token requires the ads_read or read_insights permission.',
                'guide' => [
                    'step_1' => 'Go to Meta Business Suite -> Settings -> System Users.',
                    'step_2' => 'Select your System User and ensure ads_read or read_insights permission is assigned.',
                    'step_3' => 'Enter your Meta Ad Account ID (format: act_123456789) below to sync spend and analytics.',
                ],
                'configured_ad_account_id' => $targetAdAccountId,
                'insights' => null,
                'campaigns' => [],
            ];
        }

        // If permission is present, query Ad Account Insights
        $cacheKey = "meta_billing_ad_insights_{$targetAdAccountId}_{$datePreset}";

        return Cache::remember($cacheKey, 300, function () use ($targetAdAccountId, $datePreset, $startDate, $endDate) {
            $resolvedId = $targetAdAccountId;

            if (empty($resolvedId)) {
                // Auto-discover ad accounts
                try {
                    $discoveryRes = Http::withToken($this->token)->timeout(10)->get("{$this->baseUrl}/me/adaccounts", [
                        'fields' => 'id,name,account_id,account_status,currency,amount_spent',
                    ]);
                    if ($discoveryRes->successful()) {
                        $firstAccount = $discoveryRes->json('data.0');
                        if (!empty($firstAccount['id'])) {
                            $resolvedId = $firstAccount['id'];
                        }
                    }
                } catch (\Throwable) {
                    // ignore
                }
            }

            if (empty($resolvedId)) {
                return [
                    'has_permission' => true,
                    'permission_status' => 'no_ad_account_selected',
                    'message' => 'Permission is granted, but no Ad Account ID is configured. Please enter your Meta Ad Account ID (e.g. act_123456789) to view spend and insights.',
                    'configured_ad_account_id' => null,
                    'insights' => null,
                    'campaigns' => [],
                ];
            }

            $cleanId = str_starts_with($resolvedId, 'act_') ? $resolvedId : "act_{$resolvedId}";

            try {
                // 1. Account Details
                $accRes = Http::withToken($this->token)->timeout(10)->get("{$this->baseUrl}/{$cleanId}", [
                    'fields' => 'id,name,account_id,account_status,currency,amount_spent,balance,spend_cap',
                ]);
                $accountInfo = $accRes->successful() ? $accRes->json() : ['id' => $cleanId, 'name' => 'Meta Ad Account'];

                // 2. Account-level Insights
                $insightParams = [
                    'fields' => 'spend,impressions,clicks,cpc,cpm,ctr,reach,cost_per_unique_click,frequency,date_start,date_stop',
                ];
                if ($startDate && $endDate) {
                    $insightParams['time_range'] = json_encode([
                        'since' => $startDate->format('Y-m-d'),
                        'until' => $endDate->format('Y-m-d'),
                    ]);
                } else {
                    $insightParams['date_preset'] = $this->mapDatePreset($datePreset);
                }

                $insightsRes = Http::withToken($this->token)->timeout(10)->get("{$this->baseUrl}/{$cleanId}/insights", $insightParams);
                $insightsData = $insightsRes->successful() ? ($insightsRes->json('data.0') ?? []) : [];

                // 3. Campaign-level Breakdown
                $campaignParams = [
                    'level' => 'campaign',
                    'fields' => 'campaign_name,campaign_id,spend,impressions,clicks,cpc,cpm,ctr,date_start,date_stop',
                    'limit' => 25,
                ];
                if ($startDate && $endDate) {
                    $campaignParams['time_range'] = json_encode([
                        'since' => $startDate->format('Y-m-d'),
                        'until' => $endDate->format('Y-m-d'),
                    ]);
                } else {
                    $campaignParams['date_preset'] = $this->mapDatePreset($datePreset);
                }

                $campaignsRes = Http::withToken($this->token)->timeout(10)->get("{$this->baseUrl}/{$cleanId}/insights", $campaignParams);
                $campaignsData = $campaignsRes->successful() ? ($campaignsRes->json('data') ?? []) : [];

                return [
                    'has_permission' => true,
                    'permission_status' => 'active',
                    'configured_ad_account_id' => $cleanId,
                    'account_info' => $accountInfo,
                    'insights' => [
                        'spend' => '$' . number_format((float) ($insightsData['spend'] ?? 0), 2),
                        'raw_spend' => (float) ($insightsData['spend'] ?? 0),
                        'impressions' => number_format((int) ($insightsData['impressions'] ?? 0)),
                        'clicks' => number_format((int) ($insightsData['clicks'] ?? 0)),
                        'reach' => number_format((int) ($insightsData['reach'] ?? 0)),
                        'ctr' => number_format((float) ($insightsData['ctr'] ?? 0), 2) . '%',
                        'cpc' => '$' . number_format((float) ($insightsData['cpc'] ?? 0), 2),
                        'cpm' => '$' . number_format((float) ($insightsData['cpm'] ?? 0), 2),
                        'date_start' => $insightsData['date_start'] ?? null,
                        'date_stop' => $insightsData['date_stop'] ?? null,
                    ],
                    'campaigns' => array_map(function ($c) {
                        return [
                            'campaign_name' => $c['campaign_name'] ?? 'Campaign',
                            'campaign_id' => $c['campaign_id'] ?? '',
                            'spend' => '$' . number_format((float) ($c['spend'] ?? 0), 2),
                            'impressions' => number_format((int) ($c['impressions'] ?? 0)),
                            'clicks' => number_format((int) ($c['clicks'] ?? 0)),
                            'ctr' => number_format((float) ($c['ctr'] ?? 0), 2) . '%',
                            'cpc' => '$' . number_format((float) ($c['cpc'] ?? 0), 2),
                        ];
                    }, $campaignsData),
                ];
            } catch (\Throwable $e) {
                Log::warning('Error fetching Ad Account insights: ' . $e->getMessage());
                return [
                    'has_permission' => true,
                    'permission_status' => 'api_error',
                    'message' => 'Unable to query Ad Account insights: ' . $e->getMessage(),
                    'configured_ad_account_id' => $cleanId,
                    'insights' => null,
                    'campaigns' => [],
                ];
            }
        });
    }

    /**
     * Check if token has ads_read or read_insights permission.
     */
    protected function checkAdPermissions(): array
    {
        if (empty($this->token) || empty($this->appId) || empty($this->appSecret)) {
            return ['has_ads_permission' => false, 'scopes' => []];
        }

        try {
            $response = Http::timeout(10)->get("{$this->baseUrl}/debug_token", [
                'input_token' => $this->token,
                'access_token' => "{$this->appId}|{$this->appSecret}",
            ]);

            if ($response->successful()) {
                $scopes = $response->json('data.scopes') ?? [];
                $hasAds = in_array('ads_read', $scopes, true) ||
                          in_array('ads_management', $scopes, true) ||
                          in_array('read_insights', $scopes, true);

                return [
                    'has_ads_permission' => $hasAds,
                    'scopes' => $scopes,
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('Token debug error during ad permission check: ' . $e->getMessage());
        }

        return ['has_ads_permission' => false, 'scopes' => []];
    }

    /**
     * Reconcile WhatsApp Cloud API usage & charges.
     */
    public function getReconciledWhatsAppCharges(?Carbon $startDate, ?Carbon $endDate, ?int $bankId = null): array
    {
        $recipientQuery = CampaignWhatsappRecipient::query()
            ->join('campaign_whatsapp_messages', 'campaign_whatsapp_messages.id', '=', 'campaign_whatsapp_recipients.whatsapp_message_id')
            ->join('campaigns', 'campaigns.id', '=', 'campaign_whatsapp_messages.campaign_id');

        if ($bankId) {
            $recipientQuery->where('campaigns.bank_id', $bankId);
        }
        if ($startDate) {
            $recipientQuery->where('campaign_whatsapp_recipients.created_at', '>=', $startDate);
        }
        if ($endDate) {
            $recipientQuery->where('campaign_whatsapp_recipients.created_at', '<=', $endDate);
        }

        // Metrics aggregation
        $summary = (clone $recipientQuery)
            ->selectRaw("
                COUNT(*) as total_dispatched,
                SUM(CASE 
                    WHEN LOWER(campaign_whatsapp_recipients.status) IN ('delivered', 'read', 'delivered (ecosystem warning)', 'sent', 'accepted', 'pending', 'queued', 'processing', 'scheduled')
                         OR campaign_whatsapp_recipients.error_code = '131049'
                         OR campaign_whatsapp_recipients.error_message LIKE '%maintain healthy ecosystem engagement%'
                    THEN 1 ELSE 0 END
                ) as total_delivered,
                SUM(CASE 
                    WHEN LOWER(campaign_whatsapp_recipients.status) = 'read'
                    THEN 1 ELSE 0 END
                ) as total_read,
                SUM(CASE 
                    WHEN LOWER(campaign_whatsapp_recipients.status) IN ('sent', 'accepted', 'delivered', 'read', 'delivered (ecosystem warning)', 'pending', 'queued', 'processing', 'scheduled')
                         OR campaign_whatsapp_recipients.error_code = '131049'
                         OR campaign_whatsapp_recipients.error_message LIKE '%maintain healthy ecosystem engagement%'
                    THEN 1 ELSE 0 END
                ) as total_accepted,
                SUM(CASE 
                    WHEN campaign_whatsapp_recipients.last_response IS NOT NULL OR campaign_whatsapp_recipients.reply_type IS NOT NULL 
                    THEN 1 ELSE 0 END
                ) as total_replied,
                SUM(CASE 
                    WHEN LOWER(campaign_whatsapp_recipients.status) IN ('failed', 'undelivered')
                         AND campaign_whatsapp_recipients.error_code != '131049'
                    THEN 1 ELSE 0 END
                ) as total_failed
            ")
            ->first();

        $dispatched = (int) ($summary->total_dispatched ?? 0);
        $delivered = (int) ($summary->total_delivered ?? 0);
        $read = (int) ($summary->total_read ?? 0);
        $accepted = (int) ($summary->total_accepted ?? 0);
        $replied = (int) ($summary->total_replied ?? 0);
        $failed = (int) ($summary->total_failed ?? 0);

        // Template Cache categories lookup
        $templateCaches = WhatsappTemplateCache::all()->keyBy(function ($item) {
            return strtolower(trim((string) ($item->sid ?: $item->friendly_name)));
        });

        // Group by template to categorize spend
        $templateUsage = (clone $recipientQuery)
            ->selectRaw("
                campaign_whatsapp_messages.template_sid,
                campaign_whatsapp_messages.template_name,
                COUNT(*) as sent_count,
                SUM(CASE 
                    WHEN LOWER(campaign_whatsapp_recipients.status) IN ('delivered', 'read', 'delivered (ecosystem warning)', 'sent', 'accepted', 'pending', 'queued', 'processing', 'scheduled')
                         OR campaign_whatsapp_recipients.error_code = '131049'
                         OR campaign_whatsapp_recipients.error_message LIKE '%maintain healthy ecosystem engagement%'
                    THEN 1 ELSE 0 END
                ) as delivered_count,
                SUM(CASE 
                    WHEN LOWER(campaign_whatsapp_recipients.status) IN ('sent', 'accepted', 'delivered', 'read', 'delivered (ecosystem warning)', 'pending', 'queued', 'processing', 'scheduled')
                         OR campaign_whatsapp_recipients.error_code = '131049'
                         OR campaign_whatsapp_recipients.error_message LIKE '%maintain healthy ecosystem engagement%'
                    THEN 1 ELSE 0 END
                ) as accepted_count,
                SUM(CASE 
                    WHEN campaign_whatsapp_recipients.last_response IS NOT NULL OR campaign_whatsapp_recipients.reply_type IS NOT NULL 
                    THEN 1 ELSE 0 END
                ) as replied_count
            ")
            ->groupBy('campaign_whatsapp_messages.template_sid', 'campaign_whatsapp_messages.template_name')
            ->get();

        $marketingMsgs = 0;
        $utilityMsgs = 0;
        $authMsgs = 0;
        $serviceMsgs = 0;

        $templateRows = [];

        // Linked campaigns mapping
        $templateCampaigns = CampaignWhatsappMessage::query()
            ->join('campaigns', 'campaigns.id', '=', 'campaign_whatsapp_messages.campaign_id')
            ->select('campaign_whatsapp_messages.template_sid', 'campaign_whatsapp_messages.template_name', 'campaigns.name as campaign_name')
            ->distinct()
            ->get()
            ->groupBy(fn ($item) => strtolower(trim((string) ($item->template_name ?: $item->template_sid))));

        foreach ($templateUsage as $tu) {
            $tplKey = strtolower(trim((string) ($tu->template_name ?: $tu->template_sid)));
            $cached = $templateCaches->get($tplKey);

            $rawCat = strtoupper(trim((string) ($cached?->category ?? '')));
            if (empty($rawCat)) {
                $rawCat = str_contains($tplKey, 'marketing') ? 'MARKETING' : 'UTILITY';
            }

            $rate = match ($rawCat) {
                'MARKETING' => 0.0175,
                'AUTHENTICATION', 'AUTH' => 0.0076,
                'SERVICE' => 0.0040,
                default => 0.0076,
            };

            $tAccepted = (int) $tu->accepted_count;
            $tSent = (int) $tu->sent_count;
            $tDelivered = (int) $tu->delivered_count;
            $tReplied = (int) $tu->replied_count;

            if ($rawCat === 'MARKETING') {
                $marketingMsgs += $tAccepted;
            } elseif ($rawCat === 'AUTHENTICATION' || $rawCat === 'AUTH') {
                $authMsgs += $tAccepted;
            } elseif ($rawCat === 'SERVICE') {
                $serviceMsgs += $tAccepted;
            } else {
                $utilityMsgs += $tAccepted;
            }

            $cNames = $templateCampaigns->get($tplKey, collect())->pluck('campaign_name')->unique()->values()->all();
            $cDisplay = count($cNames) === 1 ? $cNames[0] : (count($cNames) > 1 ? count($cNames) . ' Campaigns' : '1 Campaign');

            $delivPct = $tSent > 0 ? round(($tDelivered / $tSent) * 100, 1) : 0.0;
            $replyPct = $tSent > 0 ? round(($tReplied / $tSent) * 100, 1) : 0.0;
            $cost = $tAccepted * $rate;

            $templateRows[] = [
                'name' => $tu->template_name ?: $tu->template_sid,
                'meta_id' => $cached?->meta_id ?: 'Meta Direct',
                'category' => ucfirst(strtolower($rawCat)),
                'campaign' => $cDisplay,
                'sent' => number_format($tSent),
                'delivered' => number_format($tDelivered),
                'delivery_rate' => number_format($delivPct, 1) . '%',
                'reply_rate' => number_format($replyPct, 1) . '%',
                'rate' => '$' . number_format($rate, 4),
                'cost' => '$' . number_format($cost, 2),
                'raw_cost' => $cost,
                'status' => $cached?->status ?: 'APPROVED',
            ];
        }

        $spendMarketing = $marketingMsgs * 0.0175;
        $spendUtility = $utilityMsgs * 0.0076;
        $spendAuth = $authMsgs * 0.0076;
        $spendService = max(0, $serviceMsgs - 1000) * 0.0040; // 1,000 free service tier
        $totalSpend = $spendMarketing + $spendUtility + $spendAuth + $spendService;

        $deliveryRate = $dispatched > 0 ? round(($delivered / $dispatched) * 100, 1) : 0.0;
        $readRate = $delivered > 0 ? round(($read / $delivered) * 100, 1) : 0.0;
        $avgCostPerDelivered = $delivered > 0 ? round($totalSpend / $delivered, 4) : 0.0;

        // Daily trajectory for timeline charts
        $dailyTrajectory = $this->computeDailyTrajectory($recipientQuery, $startDate, $endDate);

        return [
            'summary' => [
                'total_spend' => '$' . number_format($totalSpend, 2),
                'raw_total_spend' => round($totalSpend, 2),
                'dispatched' => number_format($dispatched),
                'delivered' => number_format($delivered),
                'read' => number_format($read),
                'replied' => number_format($replied),
                'failed' => number_format($failed),
                'delivery_rate' => number_format($deliveryRate, 1) . '%',
                'read_rate' => number_format($readRate, 1) . '%',
                'avg_cost_per_delivered' => '$' . number_format($avgCostPerDelivered, 4),
                'free_tier_conversations_used' => min($serviceMsgs, 1000),
                'free_tier_conversations_limit' => 1000,
            ],
            'categories' => [
                'marketing' => [
                    'cost' => '$' . number_format($spendMarketing, 2),
                    'raw_cost' => round($spendMarketing, 2),
                    'messages' => number_format($marketingMsgs),
                    'rate' => '$0.0175',
                    'pct' => $accepted > 0 ? round(($marketingMsgs / $accepted) * 100) : 0,
                    'description' => 'Targeted promotional dispatches & customer engagement offers',
                ],
                'utility' => [
                    'cost' => '$' . number_format($spendUtility, 2),
                    'raw_cost' => round($spendUtility, 2),
                    'messages' => number_format($utilityMsgs),
                    'rate' => '$0.0076',
                    'pct' => $accepted > 0 ? round(($utilityMsgs / $accepted) * 100) : 0,
                    'description' => 'Transactional notices, statement alerts & debt settlement reminders',
                ],
                'auth' => [
                    'cost' => '$' . number_format($spendAuth, 2),
                    'raw_cost' => round($spendAuth, 2),
                    'messages' => number_format($authMsgs),
                    'rate' => '$0.0076',
                    'pct' => $accepted > 0 ? round(($authMsgs / $accepted) * 100) : 0,
                    'description' => 'One-time passwords, security codes & verification sequences',
                ],
                'service' => [
                    'cost' => '$' . number_format($spendService, 2),
                    'raw_cost' => round($spendService, 2),
                    'messages' => number_format($serviceMsgs),
                    'rate' => '$0.0040',
                    'pct' => $accepted > 0 ? round(($serviceMsgs / $accepted) * 100) : 0,
                    'free_allowance' => '1,000 Free conversations / month included by Meta',
                    'description' => 'User-initiated customer inquiries & 24-hour service conversation windows',
                ],
            ],
            'templates' => $templateRows,
            'daily_trajectory' => $dailyTrajectory,
        ];
    }

    /**
     * Compute daily transmission counts and estimated costs.
     */
    protected function computeDailyTrajectory($baseQuery, ?Carbon $startDate, ?Carbon $endDate): array
    {
        $days = 30;
        if ($startDate && $endDate) {
            $days = max(1, min(90, $startDate->diffInDays($endDate) + 1));
        }

        $dailyStats = (clone $baseQuery)
            ->selectRaw("
                DATE(campaign_whatsapp_recipients.created_at) as date,
                COUNT(*) as dispatched,
                SUM(CASE 
                    WHEN LOWER(campaign_whatsapp_recipients.status) IN ('delivered', 'read', 'delivered (ecosystem warning)', 'sent', 'accepted', 'pending', 'queued', 'processing', 'scheduled')
                         OR campaign_whatsapp_recipients.error_code = '131049'
                    THEN 1 ELSE 0 END
                ) as delivered
            ")
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get()
            ->keyBy('date');

        $labels = [];
        $dispatchedArr = [];
        $deliveredArr = [];
        $estimatedCostArr = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $dateStr = $date->format('Y-m-d');
            $labels[] = $date->format('M d');

            $row = $dailyStats->get($dateStr);
            $disp = $row ? (int) $row->dispatched : 0;
            $deliv = $row ? (int) $row->delivered : 0;
            $cost = $deliv * 0.0125; // Weighted blended Meta rate

            $dispatchedArr[] = $disp;
            $deliveredArr[] = $deliv;
            $estimatedCostArr[] = round($cost, 2);
        }

        return [
            'labels' => $labels,
            'dispatched' => $dispatchedArr,
            'delivered' => $deliveredArr,
            'cost' => $estimatedCostArr,
        ];
    }

    /**
     * Save Ad Account ID in system settings.
     */
    public function updateAdAccountId(?string $adAccountId): void
    {
        $clean = trim((string) $adAccountId);
        if ($clean !== '' && !str_starts_with($clean, 'act_') && is_numeric($clean)) {
            $clean = "act_{$clean}";
        }

        $this->settings = SystemSetting::first();
        if ($this->settings) {
            $this->settings->meta_ad_account_id = $clean ?: null;
            $this->settings->save();
        }

        $this->adAccountId = $clean ?: null;
        $this->clearBillingCache();
    }

    /**
     * Resolve date bounds for preset keys.
     */
    protected function resolveDateBounds(string $key): array
    {
        $now = Carbon::now();
        return match ($key) {
            'this_month' => [
                'start' => $now->copy()->startOfMonth(),
                'end' => $now->copy()->endOfDay(),
            ],
            '3_months' => [
                'start' => $now->copy()->subMonths(3)->startOfDay(),
                'end' => $now->copy()->endOfDay(),
            ],
            '6_months' => [
                'start' => $now->copy()->subMonths(6)->startOfDay(),
                'end' => $now->copy()->endOfDay(),
            ],
            '1_year' => [
                'start' => $now->copy()->subYear()->startOfDay(),
                'end' => $now->copy()->endOfDay(),
            ],
            'all_time' => [
                'start' => null,
                'end' => null,
            ],
            default => [
                'start' => $now->copy()->subDays(30)->startOfDay(),
                'end' => $now->copy()->endOfDay(),
            ],
        };
    }

    /**
     * Map filter preset to Meta Marketing API date_preset.
     */
    protected function mapDatePreset(string $key): string
    {
        return match ($key) {
            'this_month' => 'this_month',
            '3_months' => 'last_90d',
            '6_months' => 'last_90d',
            '1_year' => 'last_year',
            'all_time' => 'maximum',
            default => 'last_30d',
        };
    }
}
