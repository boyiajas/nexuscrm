<?php

namespace App\Services;

use App\Models\CampaignWhatsappRecipient;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WhatsAppSendAttempt;
use Illuminate\Database\Eloquent\Builder;

class WhatsAppDailyLimitService
{
    public function summaryFor(?User $user): array
    {
        $systemLimit = $this->systemLimit();
        $systemUsed = $this->countSystemUsedToday();
        $systemRemaining = $this->remaining($systemLimit, $systemUsed);

        $roleLimit = $user ? $this->roleLimitForUser($user) : null;
        $userUsed = $user ? $this->countUserUsedToday($user) : 0;
        $roleRemaining = $this->remaining($roleLimit, $userUsed);

        $effectiveLimit = $this->minimumNonNull([$systemLimit, $roleLimit]);
        $effectiveRemaining = $this->minimumNonNull([$systemRemaining, $roleRemaining]);

        return [
            'date' => now()->toDateString(),
            'system_limit' => $systemLimit,
            'system_used' => $systemUsed,
            'system_remaining' => $systemRemaining,
            'role_limit' => $roleLimit,
            'user_used' => $userUsed,
            'role_remaining' => $roleRemaining,
            'effective_limit' => $effectiveLimit,
            'effective_remaining' => $effectiveRemaining,
            'status' => $this->statusFor($effectiveLimit, $effectiveRemaining),
        ];
    }

    public function validateSendAllowance(User $user, int $requestedCount): array
    {
        $summary = $this->summaryFor($user);
        $remaining = $summary['effective_remaining'];

        if ($requestedCount <= 0) {
            return ['allowed' => true, 'summary' => $summary];
        }

        if ($remaining === null) {
            return ['allowed' => true, 'summary' => $summary];
        }

        if ($requestedCount <= $remaining) {
            return ['allowed' => true, 'summary' => $summary];
        }

        $message = "WhatsApp daily sending limit exceeded. Requested {$requestedCount} message(s), but only {$remaining} remain today.";
        if (!is_null($summary['system_remaining'])) {
            $message .= " System remaining: {$summary['system_remaining']}.";
        }
        if (!is_null($summary['role_remaining'])) {
            $message .= " Role remaining: {$summary['role_remaining']}.";
        }

        return [
            'allowed' => false,
            'summary' => $summary,
            'message' => $message,
        ];
    }

    public function countSystemUsedToday(): int
    {
        return WhatsAppSendAttempt::query()
            ->whereDate('attempted_at', now()->toDateString())
            ->count();
    }

    public function countUserUsedToday(User $user): int
    {
        return WhatsAppSendAttempt::query()
            ->whereDate('attempted_at', now()->toDateString())
            ->where('user_id', $user->id)
            ->count();
    }

    public function systemLimit(): ?int
    {
        $limit = SystemSetting::query()->value('meta_daily_whatsapp_limit');
        if (is_null($limit) || (int) $limit <= 0) {
            return null;
        }

        return (int) $limit;
    }

    public function roleLimitForUser(User $user): ?int
    {
        $codes = collect($user->resolvedRoleCodes())
            ->filter()
            ->map(function ($code) {
                return strtoupper(str_replace(' ', '_', (string) $code));
            })
            ->unique()
            ->values()
            ->all();

        if (empty($codes) && !empty($user->role)) {
            $codes = [
                strtoupper(str_replace(' ', '_', (string) $user->role)),
            ];
        }

        if (empty($codes)) {
            return $this->fallbackRoleLimitForUser($user);
        }

        $limit = Role::query()
            ->whereIn('code', $codes)
            ->where('is_active', true)
            ->max('whatsapp_daily_limit');

        if (is_null($limit)) {
            return $this->fallbackRoleLimitForUser($user);
        }

        return (int) $limit;
    }

    protected function fallbackRoleLimitForUser(User $user): ?int
    {
        if ($user->canManageSystemSettings()) {
            return 1000;
        }

        if ($user->canManageOperationalData()) {
            return 500;
        }

        return null;
    }

    protected function remaining(?int $limit, int $used): ?int
    {
        if (is_null($limit)) {
            return null;
        }

        return max($limit - $used, 0);
    }

    protected function minimumNonNull(array $values): ?int
    {
        $filtered = array_values(array_filter($values, fn ($value) => !is_null($value)));
        if (empty($filtered)) {
            return null;
        }

        return min($filtered);
    }

    protected function statusFor(?int $limit, ?int $remaining): string
    {
        if (is_null($limit) || is_null($remaining)) {
            return 'unlimited';
        }

        if ($remaining <= 0) {
            return 'critical';
        }

        $ratio = $limit > 0 ? ($remaining / $limit) : 0;

        if ($ratio <= 0.2) {
            return 'low';
        }

        if ($ratio <= 0.5) {
            return 'warning';
        }

        return 'healthy';
    }
}
