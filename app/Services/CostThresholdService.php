<?php

namespace App\Services;

use App\Mail\CostThresholdAlertMail;
use App\Models\Bank;
use App\Models\CostThresholdSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CostThresholdService
{
    public function __construct(
        protected MetaBillingService $billingService
    ) {
    }

    /**
     * Calculate current month WhatsApp spend for a threshold rule.
     */
    public function calculateSpend(CostThresholdSetting $rule, ?Carbon $startDate = null, ?Carbon $endDate = null): float
    {
        $startDate ??= Carbon::now()->startOfMonth();
        $endDate ??= Carbon::now()->endOfMonth();

        $bankIds = !empty($rule->bank_ids) ? array_values(array_filter(array_map('intval', $rule->bank_ids))) : null;

        $charges = $this->billingService->getReconciledWhatsAppCharges($startDate, $endDate, $bankIds);

        return (float) ($charges['summary']['raw_total_spend'] ?? 0.0);
    }

    /**
     * Get computed metrics and presentation data for a rule.
     */
    public function getRuleMetrics(CostThresholdSetting $rule): array
    {
        $currentMonth = Carbon::now()->format('Y-m');
        $currentSpend = $this->calculateSpend($rule);
        $budget = (float) $rule->threshold_amount;

        $spendPercentage = $budget > 0 ? round(($currentSpend / $budget) * 100, 1) : 0.0;
        $remaining = max(0.0, round($budget - $currentSpend, 2));

        $triggeredThisMonth = $rule->triggered_percentages[$currentMonth] ?? [];

        $status = 'normal';
        if ($spendPercentage >= 100) {
            $status = 'exceeded';
        } elseif ($spendPercentage >= 80) {
            $status = 'warning';
        }

        $bankNames = [];
        if (!empty($rule->bank_ids)) {
            $bankNames = Bank::whereIn('id', $rule->bank_ids)->pluck('name')->all();
        }

        return [
            'id' => $rule->id,
            'name' => $rule->name,
            'threshold_amount' => $budget,
            'bank_ids' => $rule->bank_ids ?? [],
            'bank_names' => $bankNames,
            'notification_emails' => $rule->notification_emails ?? [],
            'threshold_percentages' => array_values(array_map('intval', $rule->threshold_percentages ?? [80, 90, 100])),
            'is_active' => (bool) $rule->is_active,
            'current_month_spend' => $currentSpend,
            'spend_percentage' => $spendPercentage,
            'remaining_budget' => $remaining,
            'status' => $status,
            'last_alerted_at' => $rule->last_alerted_at?->toIso8601String(),
            'triggered_tiers_this_month' => $triggeredThisMonth,
            'description' => $rule->description,
            'created_at' => $rule->created_at?->toIso8601String(),
            'updated_at' => $rule->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Evaluate a rule against current spend and dispatch alert emails if thresholds crossed.
     */
    public function evaluateRule(CostThresholdSetting $rule, bool $force = false): array
    {
        if (!$rule->is_active && !$force) {
            return [
                'rule_id' => $rule->id,
                'evaluated' => false,
                'reason' => 'Rule is inactive.',
            ];
        }

        $budget = (float) $rule->threshold_amount;
        if ($budget <= 0) {
            return [
                'rule_id' => $rule->id,
                'evaluated' => false,
                'reason' => 'Threshold amount is zero or negative.',
            ];
        }

        $currentMonth = Carbon::now()->format('Y-m');
        $monthName = Carbon::now()->format('F Y');
        $currentSpend = $this->calculateSpend($rule);
        $spendPercentage = ($currentSpend / $budget) * 100;

        $tiers = array_values(array_unique(array_filter(array_map('intval', $rule->threshold_percentages ?? []))));
        sort($tiers);

        $triggeredThisMonth = $rule->triggered_percentages[$currentMonth] ?? [];
        $emails = array_values(array_filter($rule->notification_emails ?? []));
        $bankNames = !empty($rule->bank_ids) ? Bank::whereIn('id', $rule->bank_ids)->pluck('name')->all() : [];

        $alertsSent = [];

        foreach ($tiers as $tier) {
            if ($spendPercentage >= $tier) {
                $alreadyTriggered = in_array($tier, $triggeredThisMonth, true);

                if (!$alreadyTriggered || $force) {
                    if (!empty($emails)) {
                        foreach ($emails as $email) {
                            try {
                                Mail::to($email)->send(new CostThresholdAlertMail(
                                    ruleName: $rule->name,
                                    percentage: $tier,
                                    currentSpend: $currentSpend,
                                    budgetAmount: $budget,
                                    monthName: $monthName,
                                    bankNames: $bankNames,
                                    isTest: false
                                ));
                            } catch (\Throwable $e) {
                                Log::error("Failed to send budget threshold alert email to {$email}: " . $e->getMessage());
                            }
                        }
                    }

                    $rule->recordTriggeredPercentage($tier, $currentMonth);
                    $triggeredThisMonth[] = $tier;
                    $alertsSent[] = $tier;
                }
            }
        }

        return [
            'rule_id' => $rule->id,
            'evaluated' => true,
            'current_spend' => $currentSpend,
            'budget' => $budget,
            'spend_percentage' => round($spendPercentage, 1),
            'alerts_sent' => $alertsSent,
        ];
    }

    /**
     * Send a test notification for a rule.
     */
    public function sendTestNotification(CostThresholdSetting $rule, ?string $targetEmail = null): array
    {
        $emails = !empty($targetEmail) ? [$targetEmail] : ($rule->notification_emails ?? []);
        $emails = array_values(array_filter($emails));

        if (empty($emails)) {
            throw new \InvalidArgumentException('No recipient email specified for test notification.');
        }

        $monthName = Carbon::now()->format('F Y');
        $budget = (float) $rule->threshold_amount;
        $currentSpend = $this->calculateSpend($rule);
        $bankNames = !empty($rule->bank_ids) ? Bank::whereIn('id', $rule->bank_ids)->pluck('name')->all() : [];
        $previewPercentage = !empty($rule->threshold_percentages) ? (int) $rule->threshold_percentages[0] : 80;

        foreach ($emails as $email) {
            Mail::to($email)->send(new CostThresholdAlertMail(
                ruleName: $rule->name,
                percentage: $previewPercentage,
                currentSpend: $currentSpend,
                budgetAmount: $budget,
                monthName: $monthName,
                bankNames: $bankNames,
                isTest: true
            ));
        }

        return [
            'success' => true,
            'recipients' => $emails,
            'message' => 'Test alert email dispatched successfully.',
        ];
    }

    /**
     * Evaluate all active rules in the system.
     */
    public function evaluateAll(bool $force = false): array
    {
        $rules = CostThresholdSetting::where('is_active', true)->get();
        $results = [];

        foreach ($rules as $rule) {
            $results[] = $this->evaluateRule($rule, $force);
        }

        return $results;
    }
}
