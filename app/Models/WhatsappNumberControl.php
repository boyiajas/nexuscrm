<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappNumberControl extends Model
{
    protected $fillable = [
        'phone_number_id',
        'display_phone_number',
        'normalized_display_phone_number',
        'quality_rating',
        'is_paused',
        'pause_reason',
        'paused_at',
        'paused_by_user_id',
    ];

    protected $casts = [
        'is_paused' => 'boolean',
        'paused_at' => 'datetime',
    ];

    public function pausedBy()
    {
        return $this->belongsTo(User::class, 'paused_by_user_id');
    }
}
