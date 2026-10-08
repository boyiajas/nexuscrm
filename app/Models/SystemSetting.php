<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'app_name',
        'live_chat_locked',
        'live_chat_locked_message',
        'live_chat_locked_phone_numbers',
        'disable_chat_for_opted_out_clients',
        'opted_out_chat_message',
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
        'meta_ad_account_id',
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
        'meta_daily_whatsapp_limit',
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
        'meta_daily_whatsapp_limit' => 'integer',
        'meta_permissions_last_checked_at' => 'datetime',
        'meta_permissions_snapshot' => 'array',
        'live_chat_locked' => 'boolean',
        'live_chat_locked_phone_numbers' => 'array',
        'disable_chat_for_opted_out_clients' => 'boolean',
    ];

    public function adminIpAllowlistEntries(): array
    {
        return collect(preg_split('/[\r\n,;]+/', (string) $this->admin_ip_allowlist))
            ->map(fn ($item) => trim($item))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Get a flattened list of unique string identifiers for all locked WhatsApp phone numbers.
     */
    public function getLiveChatLockedPhoneNumberIdentifiers(): array
    {
        $raw = $this->live_chat_locked_phone_numbers;
        if (empty($raw) || !is_array($raw)) {
            return [];
        }

        $identifiers = [];
        foreach ($raw as $item) {
            if (is_array($item)) {
                if (!empty($item['id'])) {
                    $identifiers[] = (string) $item['id'];
                }
                if (!empty($item['phone_number_id'])) {
                    $identifiers[] = (string) $item['phone_number_id'];
                }
                if (!empty($item['display_phone_number'])) {
                    $identifiers[] = (string) $item['display_phone_number'];
                    $clean = preg_replace('/\D+/', '', (string) $item['display_phone_number']);
                    if ($clean) {
                        $identifiers[] = $clean;
                    }
                }
                if (!empty($item['number'])) {
                    $identifiers[] = (string) $item['number'];
                    $clean = preg_replace('/\D+/', '', (string) $item['number']);
                    if ($clean) {
                        $identifiers[] = $clean;
                    }
                }
            } elseif (is_string($item) || is_numeric($item)) {
                $str = (string) $item;
                $identifiers[] = $str;
                $clean = preg_replace('/\D+/', '', $str);
                if ($clean) {
                    $identifiers[] = $clean;
                }
            }
        }

        return array_values(array_unique(array_filter($identifiers)));
    }

    /**
     * Determine if live chat communication is locked for a specific ChatSession.
     */
    public function isLiveChatLockedForSession(?ChatSession $session = null): bool
    {
        if ($this->live_chat_locked) {
            return true;
        }

        $lockedIdentifiers = $this->getLiveChatLockedPhoneNumberIdentifiers();
        if (empty($lockedIdentifiers) || !$session) {
            return false;
        }

        $sessionIdentifiers = [];
        if (!empty($session->waba_phone_number_id)) {
            $sessionIdentifiers[] = (string) $session->waba_phone_number_id;
            $cleanWaba = preg_replace('/\D+/', '', (string) $session->waba_phone_number_id);
            if ($cleanWaba) {
                $sessionIdentifiers[] = $cleanWaba;
            }
        }

        if ($session->relationLoaded('bank') || $session->bank_id) {
            $bank = $session->bank ?: Bank::find($session->bank_id);
            if ($bank && !empty($bank->primary_whatsapp_number)) {
                $sessionIdentifiers[] = (string) $bank->primary_whatsapp_number;
                $cleanBankNum = preg_replace('/\D+/', '', (string) $bank->primary_whatsapp_number);
                if ($cleanBankNum) {
                    $sessionIdentifiers[] = $cleanBankNum;
                }
            }
            if ($bank && $bank->relationLoaded('whatsappAccount') && $bank->whatsappAccount) {
                if (!empty($bank->whatsappAccount->phone_number_id)) {
                    $sessionIdentifiers[] = (string) $bank->whatsappAccount->phone_number_id;
                }
                if (!empty($bank->whatsappAccount->display_phone_number)) {
                    $sessionIdentifiers[] = (string) $bank->whatsappAccount->display_phone_number;
                    $cleanAccNum = preg_replace('/\D+/', '', (string) $bank->whatsappAccount->display_phone_number);
                    if ($cleanAccNum) {
                        $sessionIdentifiers[] = $cleanAccNum;
                    }
                }
            }
        }

        foreach ($sessionIdentifiers as $ident) {
            if (in_array((string) $ident, $lockedIdentifiers, true)) {
                return true;
            }
        }

        return false;
    }
}
