<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsappAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'app_id',
        'app_secret',
        'access_token',
        'waba_id',
        'phone_number_id',
        'display_phone_number',
        'webhook_verify_token',
    ];

    protected $casts = [
        'app_secret' => 'encrypted',
        'access_token' => 'encrypted',
        'webhook_verify_token' => 'encrypted',
    ];
}
