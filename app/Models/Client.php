<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'bank_id',
        'status',
        'phone',
        'email',
        'assigned_to_id',
        'tags',
        'last_contacted_at',
        'id_number',
        'title',
        'initials',
        'first_name',
        'surname',
        'bank_name',
        'account_number',
        'branch_code',
        'easy_pay_number',
        'store_number',
        'cell_phone',
        'home_phone',
        'work_phone',
        'arrears_amount',
        'outstanding_balance',
        'settlement_amount',
        'three_months_amount',
        'installment_amount',
        'last_payment_amount',
        'total_payment_amount',
        'import_batch_number',
        'whatsapp_opted_out_at',
        'whatsapp_opt_out_reason',
        'whatsapp_contact_basis',
        'whatsapp_contact_basis_details',
        'whatsapp_opted_in_at',
        'whatsapp_opt_in_source',
        'opt_in',
        'opt_in_updated_at',
        'account_type',
        'type',
        // Note: removed 'department' column since we're using many-to-many
    ];

    protected $casts = [
        'tags' => 'array',
        'last_contacted_at' => 'datetime',
        'arrears_amount' => 'decimal:2',
        'outstanding_balance' => 'decimal:2',
        'settlement_amount' => 'decimal:2',
        'three_months_amount' => 'decimal:2',
        'installment_amount' => 'decimal:2',
        'last_payment_amount' => 'decimal:2',
        'total_payment_amount' => 'decimal:2',
        'whatsapp_opted_out_at' => 'datetime',
        'whatsapp_opted_in_at' => 'datetime',
        'opt_in_updated_at' => 'datetime',
    ];

    // Many-to-many departments (NEW)
    public function departments()
    {
        return $this->belongsToMany(Department::class, 'client_department')
            ->withTimestamps();
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function chatSessions()
    {
        return $this->hasMany(ChatSession::class);
    }

    protected static function booted()
    {
        static::deleting(function ($client) {
            $client->chatSessions()->each(function ($session) {
                $session->delete();
            });
        });
    }

    // Campaigns this client belongs to (EXISTING - matches your pivot table)
    public function campaigns()
    {
        return $this->belongsToMany(Campaign::class, 'campaign_clients')
            ->using(CampaignClient::class)
            ->withPivot([
                'whatsapp_status',
                'whatsapp_sent_at',
                'email_status',
                'email_sent_at',
                'sms_status',
                'sms_sent_at',
            ])
            ->withTimestamps();
    }

    // Accessor for backward compatibility with existing code that might reference department
    public function getDepartmentAttribute()
    {
        $firstDept = $this->departments->first();
        return $firstDept ? $firstDept->name : null;
    }

    // Helper method to get department names
    public function getDepartmentNamesAttribute()
    {
        return $this->departments->pluck('name')->join(', ');
    }

    // Helper method to get department IDs
    public function getDepartmentIdsAttribute()
    {
        return $this->departments->pluck('id')->toArray();
    }

    public function maskedIdNumber(): ?string
    {
        return self::maskSensitiveValue($this->id_number, 4);
    }

    public function maskedAccountNumber(): ?string
    {
        return self::maskSensitiveValue($this->account_number, 4);
    }

    public static function maskSensitiveValue(?string $value, int $visibleTail = 4): ?string
    {
        if (!$value) {
            return null;
        }

        $length = mb_strlen($value);
        if ($length <= $visibleTail) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', max($length - $visibleTail, 4)) . mb_substr($value, -$visibleTail);
    }

    public function isWhatsappSuppressed(): bool
    {
        return !is_null($this->whatsapp_opted_out_at);
    }

    public function hasWhatsappLawfulBasis(): bool
    {
        return !empty($this->whatsapp_contact_basis) || !is_null($this->whatsapp_opted_in_at);
    }

    public function canReceiveWhatsapp(): bool
    {
        return !$this->isWhatsappSuppressed() && $this->hasWhatsappLawfulBasis();
    }

    public function setOptIn(string $status, ?string $reason = null): void
    {
        $status = strtolower(trim($status));
        if (!in_array($status, ['yes', 'no', 'none'], true)) {
            $status = 'none';
        }

        $attributes = [
            'opt_in' => $status,
            'opt_in_updated_at' => now(),
        ];

        if ($status === 'no') {
            $attributes['whatsapp_opted_out_at'] = now();
            $attributes['whatsapp_opt_out_reason'] = $reason ?? 'Opt-Out';
        } elseif ($status === 'yes') {
            $attributes['whatsapp_opted_in_at'] = now();
            $attributes['whatsapp_opted_out_at'] = null;
            $attributes['whatsapp_opt_out_reason'] = null;
        } elseif ($status === 'none') {
            $attributes['whatsapp_opted_out_at'] = null;
            $attributes['whatsapp_opt_out_reason'] = null;
        }

        $this->forceFill($attributes)->save();
    }

    public function markWhatsappOptOut(string $reason = 'stop'): void
    {
        $this->setOptIn('no', $reason);
    }

    public function clearWhatsappOptOut(): void
    {
        $this->setOptIn('none');
    }
}
