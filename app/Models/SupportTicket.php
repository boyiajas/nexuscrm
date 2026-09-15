<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SupportTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_number',
        'user_id',
        'bank_id',
        'department_id',
        'subject',
        'category',
        'priority',
        'status',
        'description',
        'resolved_at',
        'resolved_by_user_id',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (SupportTicket $ticket) {
            if (empty($ticket->ticket_number)) {
                $prefix = 'TCK-' . date('Ym') . '-';
                $lastTicket = static::query()
                    ->where('ticket_number', 'like', "{$prefix}%")
                    ->latest('id')
                    ->first();

                $nextNumber = 1;
                if ($lastTicket && preg_match('/-(\d+)$/', $lastTicket->ticket_number, $matches)) {
                    $nextNumber = ((int) $matches[1]) + 1;
                }

                $ticket->ticket_number = $prefix . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class)->orderBy('created_at', 'asc');
    }
}
