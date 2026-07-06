<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'app_name',
        'app_short_name',
        'app_tagline',
        'company_name',
        'support_email',
        'support_phone',
        'admin_ip_allowlist',
        'password_max_age_days',
        'enable_import_malware_scanning',
        'malware_scanner_socket_path',
        'malware_scanner_host',
        'malware_scanner_port',
        'malware_scanner_timeout_seconds',
        'app_logo_path',
        'whatsapp_provider',
        'meta_app_id',
        'meta_app_secret',
        'meta_access_token',
        'meta_whatsapp_business_account_id',
        'meta_whatsapp_phone_number_id',
        'meta_whatsapp_display_phone_number',
        'meta_webhook_verify_token',
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
        'meta_environment',
        'meta_token_last_rotated_at',
        'meta_token_expires_at',
        'meta_token_rotation_notes',
        'meta_permissions_last_checked_at',
        'meta_permissions_status',
        'meta_permissions_snapshot',
    ];

    protected $casts = [
        'meta_app_secret' => 'encrypted',
        'meta_access_token' => 'encrypted',
        'meta_webhook_verify_token' => 'encrypted',
        'twilio_api_key' => 'encrypted',
        'twilio_sid' => 'encrypted',
        'twilio_auth_token' => 'encrypted',
        'twilio_msg_sid' => 'encrypted',
        'twilio_template_sid' => 'encrypted',
        'twilio_whatsapp_from' => 'encrypted',
        'twilio_status_callback' => 'encrypted',
        'enable_auto_backup' => 'boolean',
        'password_max_age_days' => 'integer',
        'enable_import_malware_scanning' => 'boolean',
        'malware_scanner_port' => 'integer',
        'malware_scanner_timeout_seconds' => 'integer',
        'meta_token_last_rotated_at' => 'datetime',
        'meta_token_expires_at' => 'datetime',
        'meta_permissions_last_checked_at' => 'datetime',
        'meta_permissions_snapshot' => 'array',
    ];

    public function adminIpAllowlistEntries(): array
    {
        return collect(preg_split('/[\r\n,;]+/', (string) $this->admin_ip_allowlist))
            ->map(fn ($item) => trim($item))
            ->filter()
            ->values()
            ->all();
    }
}
