<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class CostThresholdSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'threshold_amount',
        'bank_ids',
        'notification_emails',
        'threshold_percentages',
        'is_active',
        'created_by_user_id',
        'last_alerted_at',
        'triggered_percentages',
        'description',
    ];

    protected $casts = [
        'threshold_amount'      => 'decimal:2',
        'bank_ids'              => 'array',
        'notification_emails'   => 'array',
        'threshold_percentages' => 'array',
        'triggered_percentages' => 'array',
        'is_active'             => 'boolean',
        'last_alerted_at'       => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Get associated Bank models.
     */
    public function getBanksAttribute()
    {
        $ids = $this->bank_ids ?? [];
        if (empty($ids)) {
            return collect();
        }

        return Bank::whereIn('id', $ids)->get();
    }

    /**
     * Check if a given user has access to view/manage this threshold setting.
     */
    public function isAccessibleBy(User $user): bool
    {
        if ($user->canAccessAllBanks() || $user->isSuperAdmin()) {
            return true;
        }

        $userBankIds = $user->accessibleBankIds() ?? [];
        if (empty($userBankIds)) {
            return false;
        }

        $ruleBankIds = array_map('intval', $this->bank_ids ?? []);
        if (empty($ruleBankIds)) {
            return false;
        }

        return !empty(array_intersect($ruleBankIds, $userBankIds));
    }

    /**
     * Scope query to rules accessible by the user.
     */
    public function scopeAccessibleBy($query, User $user)
    {
        if ($user->canAccessAllBanks() || $user->isSuperAdmin()) {
            return $query;
        }

        $userBankIds = $user->accessibleBankIds() ?? [];
        if (empty($userBankIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($q) use ($userBankIds) {
            foreach ($userBankIds as $bId) {
                $q->orWhereJsonContains('bank_ids', (int) $bId);
            }
        });
    }

    /**
     * Record triggered percentage for the given month.
     */
    public function recordTriggeredPercentage(int $percentage, string $month): void
    {
        $triggered = $this->triggered_percentages ?? [];
        $currentMonthTriggered = $triggered[$month] ?? [];

        if (!in_array($percentage, $currentMonthTriggered, true)) {
            $currentMonthTriggered[] = $percentage;
            sort($currentMonthTriggered);
        }

        $triggered[$month] = $currentMonthTriggered;

        $this->update([
            'triggered_percentages' => $triggered,
            'last_alerted_at' => Carbon::now(),
        ]);
    }
}
