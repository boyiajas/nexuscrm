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
        'phone',
        'email',
        'assigned_to_id',
        'tags',
        'last_contacted_at',
        'id_number',
        'bank_name',
        'account_number',
        'branch_code',
        'whatsapp_opted_out_at',
        'whatsapp_opt_out_reason',
        'whatsapp_contact_basis',
        'whatsapp_contact_basis_details',
        'whatsapp_opted_in_at',
        'whatsapp_opt_in_source',
        // Note: removed 'department' column since we're using many-to-many
    ];

    protected $casts = [
        'tags' => 'array',
        'last_contacted_at' => 'datetime',
        'whatsapp_opted_out_at' => 'datetime',
        'whatsapp_opted_in_at' => 'datetime',
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

    public function markWhatsappOptOut(string $reason = 'stop'): void
    {
        $this->forceFill([
            'whatsapp_opted_out_at' => now(),
            'whatsapp_opt_out_reason' => $reason,
        ])->save();
    }

    public function clearWhatsappOptOut(): void
    {
        $this->forceFill([
            'whatsapp_opted_out_at' => null,
            'whatsapp_opt_out_reason' => null,
        ])->save();
    }
}
