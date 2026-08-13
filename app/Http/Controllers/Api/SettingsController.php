<?php

namespace App\Http\Controllers\Api;

use App\Concerns\HasAuditLogging;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\SystemSetting;
use App\Services\MetaWhatsAppService;
use App\Services\WhatsAppDailyLimitService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    use HasAuditLogging;

    public function branding()
    {
        return response()->json($this->transformBranding(SystemSetting::first()));
    }

    public function show()
    {
        $this->authorizeAdmin();

        $settings = SystemSetting::first();

        return response()->json($this->transformAdminSettings($settings));
    }

    public function validateMetaPermissions()
    {
        $this->authorizeAdmin();

        $settings = SystemSetting::firstOrCreate([]);

        try {
            $service = app(MetaWhatsAppService::class);
            $snapshot = $service->validateConfiguredTokenPermissions();

            $settings->forceFill([
                'meta_permissions_last_checked_at' => now(),
                'meta_permissions_status' => $snapshot['status'],
                'meta_permissions_snapshot' => $snapshot,
            ])->save();

            $this->audit(
                action: 'Validated Meta token permissions',
                module: 'Settings',
                meta: [
                    'status' => $snapshot['status'],
                    'missing_required_scopes' => $snapshot['missing_required_scopes'] ?? [],
                    'missing_recommended_scopes' => $snapshot['missing_recommended_scopes'] ?? [],
                ]
            );

            return response()->json([
                'message' => 'Meta token permissions validated.',
                'permissions' => $snapshot,
                'settings' => $this->transformAdminSettings($settings),
            ]);
        } catch (\Throwable $e) {
            $settings->forceFill([
                'meta_permissions_last_checked_at' => now(),
                'meta_permissions_status' => 'error',
                'meta_permissions_snapshot' => [
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ],
            ])->save();

            return response()->json([
                'message' => 'Meta permission validation failed: ' . $e->getMessage(),
                'settings' => $this->transformAdminSettings($settings),
            ], 422);
        }
    }

    public function fetchMetaPhoneNumbers()
    {
        $this->authorizeAdmin();

        try {
            $service = app(MetaWhatsAppService::class);
            return response()->json($service->getPhoneNumbers());
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Failed to fetch phone numbers from Meta: ' . $e->getMessage()], 422);
        }
    }

    public function submitMetaPhoneNumber(Request $request)
    {
        $this->authorizeAdmin();
        $data = $request->validate([
            'cc' => 'required|string',
            'phone_number' => 'required|string',
            'verified_name' => 'nullable|string',
        ]);

        try {
            $service = app(MetaWhatsAppService::class);
            $result = $service->addPhoneNumber($data['cc'], $data['phone_number'], $data['verified_name'] ?? null);
            \Illuminate\Support\Facades\Cache::forget('meta_whatsapp_senders'); // invalidate cache
            return response()->json(['message' => 'Phone number added to Meta.', 'data' => $result]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Failed to add phone number: ' . $e->getMessage()], 422);
        }
    }

    public function requestMetaPhoneVerification(Request $request)
    {
        $this->authorizeAdmin();
        $data = $request->validate([
            'phone_number_id' => 'required|string',
            'method' => 'required|string|in:SMS,VOICE',
        ]);

        try {
            $service = app(MetaWhatsAppService::class);
            $result = $service->requestVerificationCode($data['phone_number_id'], $data['method']);
            return response()->json(['message' => 'Verification code requested.', 'data' => $result]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Failed to request verification code: ' . $e->getMessage()], 422);
        }
    }

    public function verifyMetaPhoneNumber(Request $request)
    {
        $this->authorizeAdmin();
        $data = $request->validate([
            'phone_number_id' => 'required|string',
            'code' => 'required|string',
        ]);

        try {
            $service = app(MetaWhatsAppService::class);
            $result = $service->verifyCode($data['phone_number_id'], $data['code']);
            \Illuminate\Support\Facades\Cache::forget('meta_whatsapp_senders'); // invalidate cache
            return response()->json(['message' => 'Phone number verified.', 'data' => $result]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Failed to verify phone number: ' . $e->getMessage()], 422);
        }
    }

    public function registerMetaPhoneNumber(Request $request)
    {
        $this->authorizeAdmin();
        $data = $request->validate([
            'phone_number_id' => 'required|string',
            'pin' => 'required|string|size:6',
        ]);

        try {
            $service = app(MetaWhatsAppService::class);
            $result = $service->registerPhoneNumber($data['phone_number_id'], $data['pin']);
            \Illuminate\Support\Facades\Cache::forget('meta_whatsapp_senders'); // invalidate cache
            return response()->json(['message' => 'Phone number successfully registered on Cloud API.', 'data' => $result]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Failed to register phone number: ' . $e->getMessage()], 422);
        }
    }

    public function update(Request $request)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'app_name'               => ['sometimes', 'nullable', 'string', 'max:255'],
            'app_short_name'         => ['sometimes', 'nullable', 'string', 'max:50'],
            'app_tagline'            => ['sometimes', 'nullable', 'string', 'max:255'],
            'company_name'           => ['sometimes', 'nullable', 'string', 'max:255'],
            'support_email'          => ['sometimes', 'nullable', 'email', 'max:255'],
            'support_phone'          => ['sometimes', 'nullable', 'string', 'max:255'],
            'admin_ip_allowlist'     => ['sometimes', 'nullable', 'string'],
            'password_max_age_days'  => ['sometimes', 'nullable', 'integer', 'min:0', 'max:3650'],
            'enable_import_malware_scanning' => ['sometimes', 'boolean'],
            'malware_scanner_socket_path' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'malware_scanner_host' => ['sometimes', 'nullable', 'string', 'max:255'],
            'malware_scanner_port' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:65535'],
            'malware_scanner_timeout_seconds' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:120'],
            'app_logo'               => ['sometimes', 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'remove_app_logo'        => ['sometimes', 'boolean'],
            'twilio_sid'             => ['sometimes', 'nullable', 'string'],
            'twilio_auth_token'      => ['sometimes', 'nullable', 'string'],
            'twilio_msg_sid'         => ['sometimes', 'nullable', 'string'],
            'twilio_template_sid'    => ['sometimes', 'nullable', 'string'],
            'twilio_whatsapp_from'   => ['sometimes', 'nullable', 'string'],
            'twilio_status_callback' => ['sometimes', 'nullable', 'string'],
            'whatsapp_provider'      => ['sometimes', 'nullable', 'string', 'max:50'],
            'meta_app_id'            => ['sometimes', 'nullable', 'string'],
            'meta_app_secret'        => ['sometimes', 'nullable', 'string'],
            'meta_access_token'      => ['sometimes', 'nullable', 'string'],
            'meta_whatsapp_business_account_id' => ['sometimes', 'nullable', 'string'],
            'meta_whatsapp_phone_number_id' => ['sometimes', 'nullable', 'string'],
            'meta_whatsapp_display_phone_number' => ['sometimes', 'nullable', 'string'],
            'meta_webhook_verify_token' => ['sometimes', 'nullable', 'string'],
            'meta_environment'       => ['sometimes', 'nullable', 'string', 'in:development,staging,production'],
            'meta_token_last_rotated_at' => ['sometimes', 'nullable', 'date', 'after:2000-01-01'],
            'meta_token_expires_at'  => ['sometimes', 'nullable', 'date', 'after:2000-01-01'],
            'meta_token_rotation_notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'meta_daily_whatsapp_limit' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100000000'],
        ]);

        $settings = SystemSetting::firstOrCreate([]);
        $isMetaUpdate = array_key_exists('meta_access_token', $data) || array_key_exists('meta_environment', $data);

        if ($isMetaUpdate) {
            $metaEnvironment = $data['meta_environment'] ?? $settings->meta_environment ?? env('META_ENVIRONMENT', 'production');
            if (
                $metaEnvironment === 'production'
                && (
                    empty($data['meta_access_token'] ?? null)
                    || empty($data['meta_whatsapp_business_account_id'] ?? null)
                    || empty($data['meta_whatsapp_phone_number_id'] ?? null)
                    || empty($data['meta_token_expires_at'] ?? null)
                    || empty(trim((string) ($data['meta_token_rotation_notes'] ?? '')))
                )
            ) {
                return response()->json([
                    'message' => 'Production Meta configuration requires an access token, business account ID, phone number ID, token expiry date, and rotation notes.',
                ], 422);
            }
        }


        $previousMetaToken = $settings->meta_access_token;
        $logoRemoved = false;
        $logoUploaded = false;

        if (($data['remove_app_logo'] ?? false) && $settings->app_logo_path) {
            Storage::disk('public')->delete($settings->app_logo_path);
            $data['app_logo_path'] = null;
            $logoRemoved = true;
        }

        unset($data['remove_app_logo']);

        if ($request->hasFile('app_logo')) {
            if ($settings->app_logo_path) {
                Storage::disk('public')->delete($settings->app_logo_path);
            }
            $data['app_logo_path'] = $request->file('app_logo')->store('branding', 'public');
            $logoUploaded = true;
        }

        unset($data['app_logo']);

        if (array_key_exists('meta_access_token', $data) && !empty($data['meta_access_token']) && $data['meta_access_token'] !== $previousMetaToken) {
            $data['meta_token_last_rotated_at'] = $data['meta_token_last_rotated_at'] ?? now();
        }

        $settings->fill($data);
        $settings->save();

        if ($logoUploaded) {
            $this->audit(
                action: 'Uploaded application branding logo',
                module: 'Settings',
                meta: [
                    'path' => $settings->app_logo_path,
                    'filename' => $request->file('app_logo')?->getClientOriginalName(),
                ]
            );
        }

        if ($logoRemoved) {
            $this->audit(
                action: 'Removed application branding logo',
                module: 'Settings'
            );
        }

        if (array_key_exists('meta_access_token', $data) && !empty($data['meta_access_token']) && $data['meta_access_token'] !== $previousMetaToken) {
            $this->audit(
                action: 'Updated Meta access token',
                module: 'Settings',
                meta: [
                    'meta_environment' => $settings->meta_environment,
                    'token_last_rotated_at' => optional($settings->meta_token_last_rotated_at)->toDateTimeString(),
                    'token_expires_at' => optional($settings->meta_token_expires_at)->toDateTimeString(),
                ]
            );
        }

        return response()->json($this->transformAdminSettings($settings));
    }

    protected function transformSettings(?SystemSetting $settings): array
    {
        if (!$settings) {
            return [
                'app_name' => 'NexusCRM',
                'app_short_name' => 'NC',
                'app_tagline' => 'Mini CRM Console',
                'company_name' => null,
                'support_email' => null,
                'support_phone' => null,
                'admin_ip_allowlist' => env('ADMIN_IP_ALLOWLIST'),
                'password_max_age_days' => (int) env('PASSWORD_MAX_AGE_DAYS', 90),
                'enable_import_malware_scanning' => filter_var(env('ENABLE_IMPORT_MALWARE_SCANNING', false), FILTER_VALIDATE_BOOL),
                'malware_scanner_socket_path' => env('MALWARE_SCANNER_SOCKET_PATH'),
                'malware_scanner_host' => env('MALWARE_SCANNER_HOST', '127.0.0.1'),
                'malware_scanner_port' => (int) env('MALWARE_SCANNER_PORT', 3310),
                'malware_scanner_timeout_seconds' => (int) env('MALWARE_SCANNER_TIMEOUT_SECONDS', 15),
                'app_logo_path' => null,
                'app_logo_url' => null,
                'twilio_sid' => null,
                'twilio_auth_token' => null,
                'twilio_msg_sid' => null,
                'twilio_template_sid' => null,
                'twilio_whatsapp_from' => null,
                'twilio_status_callback' => null,
                'whatsapp_provider' => 'meta',
                'meta_app_id' => config('services.meta_whatsapp.app_id'),
                'meta_app_secret' => config('services.meta_whatsapp.app_secret'),
                'meta_access_token' => config('services.meta_whatsapp.access_token'),
                'meta_whatsapp_business_account_id' => config('services.meta_whatsapp.business_account_id'),
                'meta_whatsapp_phone_number_id' => config('services.meta_whatsapp.phone_number_id'),
                'meta_whatsapp_display_phone_number' => config('services.meta_whatsapp.display_phone_number'),
                'meta_webhook_verify_token' => config('services.meta_whatsapp.verify_token'),
                'meta_environment' => env('META_ENVIRONMENT', 'production'),
                'meta_token_last_rotated_at' => null,
                'meta_token_expires_at' => null,
                'meta_token_rotation_notes' => null,
                'meta_daily_whatsapp_limit' => null,
                'meta_permissions_last_checked_at' => null,
                'meta_permissions_status' => null,
                'meta_permissions_snapshot' => null,
            ];
        }

        return [
            'app_name' => $settings->app_name ?: 'NexusCRM',
            'app_short_name' => $settings->app_short_name ?: 'NC',
            'app_tagline' => $settings->app_tagline ?: 'Mini CRM Console',
            'company_name' => $settings->company_name,
            'support_email' => $settings->support_email,
            'support_phone' => $settings->support_phone,
            'admin_ip_allowlist' => $settings->admin_ip_allowlist ?: env('ADMIN_IP_ALLOWLIST'),
            'password_max_age_days' => $settings->password_max_age_days ?: (int) env('PASSWORD_MAX_AGE_DAYS', 90),
            'enable_import_malware_scanning' => (bool) $settings->enable_import_malware_scanning,
            'malware_scanner_socket_path' => $settings->malware_scanner_socket_path ?: env('MALWARE_SCANNER_SOCKET_PATH'),
            'malware_scanner_host' => $settings->malware_scanner_host ?: env('MALWARE_SCANNER_HOST', '127.0.0.1'),
            'malware_scanner_port' => $settings->malware_scanner_port ?: (int) env('MALWARE_SCANNER_PORT', 3310),
            'malware_scanner_timeout_seconds' => $settings->malware_scanner_timeout_seconds ?: (int) env('MALWARE_SCANNER_TIMEOUT_SECONDS', 15),
            'app_logo_path' => $settings->app_logo_path,
            'app_logo_url' => $settings->app_logo_path ? Storage::disk('public')->url($settings->app_logo_path) : null,
            'twilio_sid' => $settings->twilio_sid,
            'twilio_auth_token' => $settings->twilio_auth_token,
            'twilio_msg_sid' => $settings->twilio_msg_sid,
            'twilio_template_sid' => $settings->twilio_template_sid,
            'twilio_whatsapp_from' => $settings->twilio_whatsapp_from,
            'twilio_status_callback' => $settings->twilio_status_callback,
            'whatsapp_provider' => $settings->whatsapp_provider ?: 'meta',
            'meta_app_id' => $settings->meta_app_id ?: config('services.meta_whatsapp.app_id'),
            'meta_app_secret' => $settings->meta_app_secret ?: config('services.meta_whatsapp.app_secret'),
            'meta_access_token' => $settings->meta_access_token ?: config('services.meta_whatsapp.access_token'),
            'meta_whatsapp_business_account_id' => $settings->meta_whatsapp_business_account_id ?: config('services.meta_whatsapp.business_account_id'),
            'meta_whatsapp_phone_number_id' => $settings->meta_whatsapp_phone_number_id ?: config('services.meta_whatsapp.phone_number_id'),
            'meta_whatsapp_display_phone_number' => $settings->meta_whatsapp_display_phone_number ?: config('services.meta_whatsapp.display_phone_number'),
            'meta_webhook_verify_token' => $settings->meta_webhook_verify_token ?: config('services.meta_whatsapp.verify_token'),
            'meta_environment' => $settings->meta_environment ?: env('META_ENVIRONMENT', 'production'),
            'meta_token_last_rotated_at' => optional($settings->meta_token_last_rotated_at)->toDateTimeString(),
            'meta_token_expires_at' => optional($settings->meta_token_expires_at)->toDateTimeString(),
            'meta_token_rotation_notes' => $settings->meta_token_rotation_notes,
            'meta_daily_whatsapp_limit' => $settings->meta_daily_whatsapp_limit,
            'meta_permissions_last_checked_at' => optional($settings->meta_permissions_last_checked_at)->toDateTimeString(),
            'meta_permissions_status' => $settings->meta_permissions_status,
            'meta_permissions_snapshot' => $settings->meta_permissions_snapshot,
        ];
    }

    protected function transformBranding(?SystemSetting $settings): array
    {
        $all = $this->transformSettings($settings);

        return [
            'app_name' => $all['app_name'],
            'app_short_name' => $all['app_short_name'],
            'app_tagline' => $all['app_tagline'],
            'company_name' => $all['company_name'],
            'support_email' => $all['support_email'],
            'support_phone' => $all['support_phone'],
            'app_logo_url' => $all['app_logo_url'],
        ];
    }

    protected function transformAdminSettings(?SystemSetting $settings): array
    {
        return array_merge(
            $this->transformSettings($settings),
            [
                'meta_phone_profile' => $this->resolveMetaPhoneProfile($settings),
                'whatsapp_daily_limit_summary' => app(WhatsAppDailyLimitService::class)->summaryFor(Auth::user()),
            ]
        );
    }

    protected function resolveMetaPhoneProfile(?SystemSetting $settings): ?array
    {
        $accessToken = $settings?->meta_access_token ?: config('services.meta_whatsapp.access_token');
        $businessAccountId = $settings?->meta_whatsapp_business_account_id ?: config('services.meta_whatsapp.business_account_id');
        $phoneNumberId = $settings?->meta_whatsapp_phone_number_id ?: config('services.meta_whatsapp.phone_number_id');

        if (empty($accessToken) || empty($businessAccountId) || empty($phoneNumberId)) {
            return null;
        }

        try {
            return app(MetaWhatsAppService::class)->getPhoneNumberProfile();
        } catch (\Throwable $e) {
            return [
                'fetch_error' => $e->getMessage(),
                'fetched_at' => now()->toDateTimeString(),
            ];
        }
    }

    private function authorizeAdmin(): void
    {
        $user = Auth::user();
        if (!$user || !$user->canManageSystemSettings()) {
            abort(403, 'Only SUPER_ADMIN or ADMIN can manage system settings.');
        }
    }
}
