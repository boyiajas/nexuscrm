<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'twilio_api_key',
        'twilio_sid',
        'twilio_auth_token',
        'twilio_msg_sid',
        'twilio_template_sid',
        'twilio_whatsapp_from',
        'twilio_status_callback',
        'zoomconnect_api_key',
        'zoomconnect_base_url',
        'backup_frequency',
        'enable_auto_backup',
        'email_provider',
    ];
}
