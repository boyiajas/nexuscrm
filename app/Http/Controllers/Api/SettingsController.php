<?php

namespace App\Http\Controllers\Api;

use App\Concerns\HasAuditLogging;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\SystemSetting;
use App\Models\WhatsappAccount;
use App\Models\Bank;
use App\Services\MetaWhatsAppService;
use App\Services\WhatsAppDailyLimitService;
use App\Services\WhatsAppNumberControlService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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

    public function subscribeWebhook()
    {
        $this->authorizeAdmin();

        try {
            $service = app(MetaWhatsAppService::class);
            $result = $service->subscribeWebhook();
            return response()->json([
                'message' => 'App successfully subscribed to active WABA webhooks.',
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Failed to subscribe webhook to WABA: ' . $e->getMessage()], 422);
        }
    }

    public function getWebhookSubscriptions()
    {
        $this->authorizeAdmin();

        try {
            $service = app(MetaWhatsAppService::class);
            $result = $service->getWebhookSubscriptions();
            return response()->json([
                'subscriptions' => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Failed to fetch webhook subscriptions: ' . $e->getMessage()], 422);
        }
    }

    public function fetchMetaPhoneNumbers()
    {
        $this->authorizeAdmin();

        try {
            $service = app(MetaWhatsAppService::class);
            $numbers = collect($service->getPhoneNumbers())->values();
            $profiles = WhatsappAccount::query()
                ->get(['id', 'name', 'phone_number_id', 'display_phone_number']);
            $profilesByPhoneId = $profiles
                ->filter(fn (WhatsappAccount $profile) => filled($profile->phone_number_id))
                ->keyBy(fn (WhatsappAccount $profile) => (string) $profile->phone_number_id);
            $profilesByDisplayNumber = $profiles
                ->filter(fn (WhatsappAccount $profile) => filled($profile->display_phone_number))
                ->keyBy(fn (WhatsappAccount $profile) => preg_replace('/\D+/', '', (string) $profile->display_phone_number));

            $numberControlService = app(WhatsAppNumberControlService::class);
            $numbers = $numbers->map(function (array $number) use ($profilesByPhoneId, $profilesByDisplayNumber, $numberControlService) {
                $profile = $profilesByPhoneId->get((string) ($number['id'] ?? ''));
                if (!$profile) {
                    $normalizedDisplayNumber = preg_replace('/\D+/', '', (string) ($number['display_phone_number'] ?? ''));
                    $profile = $profilesByDisplayNumber->get($normalizedDisplayNumber);
                }

                $controlStatus = $numberControlService->statusFor(
                    isset($number['id']) ? (string) $number['id'] : null,
                    $number['display_phone_number'] ?? null,
                    $number['quality_rating'] ?? null
                );

                return array_merge($number, [
                    'whatsapp_profile_id' => $profile?->id,
                    'whatsapp_profile_name' => $profile?->name,
                ], $controlStatus);
            });

            return response()->json($numbers->values());
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Failed to fetch phone numbers from Meta: ' . $e->getMessage()], 422);
        }
    }

    public function submitMetaPhoneNumber(Request $request)
    {
        $this->authorizeWabaNumbers();
        $data = $request->validate([
            'cc' => ['required', 'string', 'regex:/^\d{1,4}$/'],
            'phone_number' => ['required', 'string', 'regex:/^\d{4,15}$/'],
            'verified_name' => ['nullable', 'string', 'max:255'],
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
        $this->authorizeWabaNumbers();
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
        $this->authorizeWabaNumbers();
        $data = $request->validate([
            'phone_number_id' => 'required|string',
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
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
        $this->authorizeWabaNumbers();
        $data = $request->validate([
            'phone_number_id' => 'required|string',
            'pin' => ['required', 'string', 'regex:/^\d{6}$/'],
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

    public function updateMetaPhoneNumberPause(
        Request $request,
        string $phoneNumberId,
        WhatsAppNumberControlService $numberControlService
    ) {
        $this->authorizePauseWhatsappNumbers();

        $data = $request->validate([
            'paused' => ['required', 'boolean'],
            'display_phone_number' => ['nullable', 'string', 'max:50'],
            'quality_rating' => ['nullable', 'string', 'max:30'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $control = $numberControlService->setPaused(
            $phoneNumberId,
            $data['display_phone_number'] ?? null,
            $data['quality_rating'] ?? null,
            (bool) $data['paused'],
            Auth::user(),
            $data['reason'] ?? null
        );

        $this->audit(
            action: ($control->is_paused ? 'Paused' : 'Resumed') . ' WhatsApp number',
            module: 'Settings',
            meta: [
                'phone_number_id' => $control->phone_number_id,
                'display_phone_number' => $control->display_phone_number,
                'quality_rating' => $control->quality_rating,
                'is_paused' => $control->is_paused,
            ]
        );

        return response()->json([
            'message' => $control->is_paused
                ? 'WhatsApp number paused. Campaign batches can still be saved as drafts but cannot be sent.'
                : 'WhatsApp number resumed and is available for sending.',
            'number' => $numberControlService->statusFor(
                $control->phone_number_id,
                $control->display_phone_number,
                $control->quality_rating
            ),
        ]);
    }

    public function getMetaPhoneNumberProfile(string $phoneNumberId)
    {
        $this->authorizeWabaNumbers();

        try {
            $service = MetaWhatsAppService::forPhoneNumberId($phoneNumberId);

            $phoneData = [];
            try {
                $phoneData = $service->getPhoneNumber($phoneNumberId);
            } catch (\Throwable $e) {
                Log::warning('Failed to fetch individual phone number node from Meta', [
                    'phone_number_id' => $phoneNumberId,
                    'error' => $e->getMessage(),
                ]);
            }

            if (empty($phoneData['display_phone_number'])) {
                try {
                    $allNumbers = $service->getPhoneNumbers();
                    $found = collect($allNumbers)->first(fn ($n) => (string) ($n['id'] ?? '') === (string) $phoneNumberId);
                    if ($found) {
                        $phoneData = array_merge($found, array_filter($phoneData, fn ($v) => $v !== null && $v !== ''));
                    }
                } catch (\Throwable) {
                    // ignore fallback failure
                }
            }

            $businessProfile = [];
            try {
                $businessProfile = $service->getBusinessProfile($phoneNumberId);
            } catch (\Throwable $e) {
                Log::warning('Failed to fetch WhatsApp business profile from Meta', [
                    'phone_number_id' => $phoneNumberId,
                    'error' => $e->getMessage(),
                ]);
            }

            $crmAccount = WhatsappAccount::where('phone_number_id', $phoneNumberId)->first();

            return response()->json([
                'phone_number' => array_merge([
                    'id' => $phoneNumberId,
                    'display_phone_number' => $phoneData['display_phone_number'] ?? null,
                    'verified_name' => $phoneData['verified_name'] ?? null,
                    'name_status' => $phoneData['name_status'] ?? null,
                    'new_display_name' => $phoneData['new_display_name'] ?? null,
                    'new_name_status' => $phoneData['new_name_status'] ?? null,
                    'code_verification_status' => $phoneData['code_verification_status'] ?? null,
                    'quality_rating' => $phoneData['quality_rating'] ?? null,
                    'messaging_limit_tier' => $phoneData['messaging_limit_tier'] ?? null,
                    'platform_type' => $phoneData['platform_type'] ?? null,
                ], $phoneData),
                'business_profile' => [
                    'about' => $businessProfile['about'] ?? '',
                    'address' => $businessProfile['address'] ?? '',
                    'description' => $businessProfile['description'] ?? '',
                    'email' => $businessProfile['email'] ?? '',
                    'profile_picture_url' => $businessProfile['profile_picture_url'] ?? null,
                    'websites' => is_array($businessProfile['websites'] ?? null) ? array_values($businessProfile['websites']) : [],
                    'vertical' => $businessProfile['vertical'] ?? 'OTHER',
                ],
                'crm_account' => $crmAccount ? [
                    'id' => $crmAccount->id,
                    'name' => $crmAccount->name,
                    'bank_id' => $crmAccount->bank_id,
                ] : null,
                'vertical_options' => collect(MetaWhatsAppService::VALID_VERTICALS)->map(fn ($label, $val) => [
                    'value' => $val,
                    'label' => $label,
                ])->values(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Error loading WhatsApp number profile', [
                'phone_number_id' => $phoneNumberId,
                'error' => $e->getMessage(),
            ]);

            $crmAccount = WhatsappAccount::where('phone_number_id', $phoneNumberId)->first();
            return response()->json([
                'phone_number' => [
                    'id' => $phoneNumberId,
                    'display_phone_number' => $crmAccount?->display_phone_number ?: $phoneNumberId,
                    'verified_name' => $crmAccount?->name ?: '',
                    'name_status' => 'UNKNOWN',
                    'new_display_name' => null,
                    'new_name_status' => null,
                    'code_verification_status' => 'UNKNOWN',
                    'quality_rating' => 'UNKNOWN',
                    'messaging_limit_tier' => null,
                    'platform_type' => 'CLOUD_API',
                ],
                'business_profile' => [
                    'about' => '',
                    'address' => '',
                    'description' => '',
                    'email' => '',
                    'profile_picture_url' => null,
                    'websites' => [],
                    'vertical' => 'OTHER',
                ],
                'crm_account' => $crmAccount ? [
                    'id' => $crmAccount->id,
                    'name' => $crmAccount->name,
                    'bank_id' => $crmAccount->bank_id,
                ] : null,
                'vertical_options' => collect(MetaWhatsAppService::VALID_VERTICALS)->map(fn ($label, $val) => [
                    'value' => $val,
                    'label' => $label,
                ])->values(),
                'fetch_warning' => 'Meta API details unavailable: ' . $e->getMessage(),
            ]);
        }
    }

    public function updateMetaPhoneNumberProfile(Request $request, string $phoneNumberId)
    {
        $this->authorizeWabaNumbers();

        $data = $request->validate([
            'about' => ['nullable', 'string', 'max:139'],
            'address' => ['nullable', 'string', 'max:256'],
            'description' => ['nullable', 'string', 'max:512'],
            'email' => ['nullable', 'email', 'max:128'],
            'vertical' => ['nullable', 'string', 'in:' . implode(',', array_keys(MetaWhatsAppService::VALID_VERTICALS))],
            'websites' => ['nullable'],
            'website_1' => ['nullable', 'string', 'max:256'],
            'website_2' => ['nullable', 'string', 'max:256'],
            'new_display_name' => ['nullable', 'string', 'max:255'],
            'profile_picture' => ['nullable', 'file', 'mimes:jpeg,jpg,png', 'max:5120'],
        ]);

        try {
            $service = MetaWhatsAppService::forPhoneNumberId($phoneNumberId);
            $auditMeta = ['phone_number_id' => $phoneNumberId];
            $messages = [];

            // 1. Process display name change if provided
            $newDisplayName = trim((string) ($data['new_display_name'] ?? ''));
            if ($newDisplayName !== '') {
                try {
                    $service->updateDisplayName($phoneNumberId, $newDisplayName);
                    $auditMeta['submitted_display_name'] = $newDisplayName;
                    $messages[] = "Display name '{$newDisplayName}' submitted to Meta for review.";
                } catch (\Throwable $e) {
                    throw new \RuntimeException("Failed to submit display name: " . $e->getMessage());
                }
            }

            // 2. Process profile picture upload if provided
            $pictureHandle = null;
            if ($request->hasFile('profile_picture')) {
                try {
                    $pictureHandle = $service->uploadProfilePicture($phoneNumberId, $request->file('profile_picture'));
                    $auditMeta['profile_picture_uploaded'] = true;
                    $messages[] = "Profile picture uploaded to Meta.";
                } catch (\Throwable $e) {
                    throw new \RuntimeException("Failed to upload profile picture: " . $e->getMessage());
                }
            }

            // 3. Build websites array
            $normalizeUrl = function (?string $url) {
                if (!$url) return null;
                $url = trim($url);
                if ($url === '') return null;
                if (!preg_match('~^(?:f|ht)tps?://~i', $url)) {
                    $url = 'https://' . $url;
                }
                return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
            };

            $websites = [];
            if (!empty($data['website_1'])) {
                $u1 = $normalizeUrl($data['website_1']);
                if ($u1) $websites[] = $u1;
            }
            if (!empty($data['website_2'])) {
                $u2 = $normalizeUrl($data['website_2']);
                if ($u2) $websites[] = $u2;
            }
            if (empty($websites) && isset($data['websites'])) {
                $rawWebsites = is_array($data['websites']) ? $data['websites'] : json_decode((string) $data['websites'], true);
                if (is_array($rawWebsites)) {
                    foreach ($rawWebsites as $raw) {
                        $norm = $normalizeUrl((string) $raw);
                        if ($norm && count($websites) < 2) {
                            $websites[] = $norm;
                        }
                    }
                }
            }

            // 4. Update WhatsApp business profile
            $profileUpdate = [
                'address' => $data['address'] ?? '',
                'description' => $data['description'] ?? '',
                'email' => $data['email'] ?? '',
                'vertical' => $data['vertical'] ?? 'OTHER',
                'websites' => $websites,
            ];
            $about = trim((string) ($data['about'] ?? ''));
            if ($about !== '') {
                $profileUpdate['about'] = $about;
            }
            if ($pictureHandle) {
                $profileUpdate['profile_picture_handle'] = $pictureHandle;
            }

            $service->updateBusinessProfile($phoneNumberId, $profileUpdate);
            $auditMeta['profile_updated'] = true;
            $messages[] = "Business profile details updated on Meta.";

            // 5. Invalidate caches
            $service->clearPhoneNumbersCache();
            \Illuminate\Support\Facades\Cache::forget('meta_whatsapp_senders');

            // 6. Audit log
            $this->audit(
                action: 'Updated WhatsApp Business Profile & Display Name',
                module: 'Settings',
                meta: $auditMeta
            );

            return response()->json([
                'message' => implode(' ', $messages) ?: 'WhatsApp number profile updated successfully.',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function updateMetaPhoneNumberDisplayName(Request $request, string $phoneNumberId)
    {
        $this->authorizeWabaNumbers();

        $data = $request->validate([
            'new_display_name' => ['required', 'string', 'min:2', 'max:255'],
        ]);

        try {
            $service = MetaWhatsAppService::forPhoneNumberId($phoneNumberId);
            $service->updateDisplayName($phoneNumberId, $data['new_display_name']);
            $service->clearPhoneNumbersCache();

            $this->audit(
                action: 'Submitted WhatsApp display name to Meta',
                module: 'Settings',
                meta: [
                    'phone_number_id' => $phoneNumberId,
                    'new_display_name' => $data['new_display_name'],
                ]
            );

            return response()->json([
                'message' => "Display name '{$data['new_display_name']}' submitted to Meta for review. Approval status will update once reviewed.",
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Failed to submit display name: ' . $e->getMessage()], 422);
        }
    }

    public function updateMetaPhoneNumberProfilePicture(Request $request, string $phoneNumberId)
    {
        $this->authorizeWabaNumbers();

        $request->validate([
            'profile_picture' => ['required', 'file', 'mimes:jpeg,jpg,png', 'max:5120'],
        ]);

        try {
            $service = MetaWhatsAppService::forPhoneNumberId($phoneNumberId);
            $handle = $service->uploadProfilePicture($phoneNumberId, $request->file('profile_picture'));
            $service->updateBusinessProfile($phoneNumberId, [
                'profile_picture_handle' => $handle,
            ]);
            $service->clearPhoneNumbersCache();

            $this->audit(
                action: 'Uploaded WhatsApp profile picture to Meta',
                module: 'Settings',
                meta: [
                    'phone_number_id' => $phoneNumberId,
                ]
            );

            return response()->json([
                'message' => 'Profile picture successfully uploaded and updated on Meta.',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Failed to upload profile picture: ' . $e->getMessage()], 422);
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
            'live_chat_locked'       => ['sometimes', 'boolean'],
            'live_chat_locked_message' => ['sometimes', 'nullable', 'string', 'max:255'],
            'live_chat_locked_phone_numbers' => ['sometimes', 'nullable'],
            'disable_chat_for_opted_out_clients' => ['sometimes', 'boolean'],
            'opted_out_chat_message' => ['sometimes', 'nullable', 'string', 'max:255'],
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

        $requestedWabaId = $data['meta_whatsapp_business_account_id'] ?? $settings->meta_whatsapp_business_account_id;
        $requestedPhoneId = $data['meta_whatsapp_phone_number_id'] ?? $settings->meta_whatsapp_phone_number_id;

        if (!empty($requestedWabaId) && !empty($requestedPhoneId) && trim((string)$requestedWabaId) === trim((string)$requestedPhoneId)) {
            return response()->json([
                'message' => 'Phone Number ID cannot be identical to the WhatsApp Business Account (WABA) ID. Please enter the Phone Number ID from your Meta App Console.',
                'errors' => [
                    'meta_whatsapp_phone_number_id' => ['The Phone Number ID cannot be the same as the WABA ID.'],
                ],
            ], 422);
        }

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

        if (array_key_exists('live_chat_locked_phone_numbers', $data)) {
            if (is_string($data['live_chat_locked_phone_numbers'])) {
                $decoded = json_decode($data['live_chat_locked_phone_numbers'], true);
                $data['live_chat_locked_phone_numbers'] = is_array($decoded) ? $decoded : [];
            } elseif (!is_array($data['live_chat_locked_phone_numbers'])) {
                $data['live_chat_locked_phone_numbers'] = [];
            }
        }

        $settings->fill($data);
        $settings->save();

        if (array_key_exists('meta_whatsapp_business_account_id', $data) || array_key_exists('meta_whatsapp_phone_number_id', $data)) {
            try {
                app(\App\Services\MetaWhatsAppService::class)->clearPhoneNumbersCache();
            } catch (\Throwable $e) {
                // Ignore if Meta service is not fully initialized
            }
        }

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
                'app_name' => 'SR Solution',
                'app_short_name' => 'SR',
                'app_tagline' => 'WhatsApp CRM Console',
                'company_name' => null,
                'live_chat_locked' => false,
                'live_chat_locked_message' => 'Live chat is temporarily disabled.',
                'live_chat_locked_phone_numbers' => [],
                'disable_chat_for_opted_out_clients' => true,
                'opted_out_chat_message' => 'This client has opted out of WhatsApp communication. Messaging is disabled.',
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
            'app_name' => $settings->app_name ?: 'SR Solution',
            'app_short_name' => $settings->app_short_name ?: 'SR',
            'app_tagline' => $settings->app_tagline ?: 'WhatsApp CRM Console',
            'company_name' => $settings->company_name,
            'live_chat_locked' => (bool) $settings->live_chat_locked,
            'live_chat_locked_message' => $settings->live_chat_locked_message ?: 'Live chat is temporarily disabled.',
            'live_chat_locked_phone_numbers' => $settings->live_chat_locked_phone_numbers ?: [],
            'disable_chat_for_opted_out_clients' => $settings->disable_chat_for_opted_out_clients !== null ? (bool) $settings->disable_chat_for_opted_out_clients : true,
            'opted_out_chat_message' => $settings->opted_out_chat_message ?: 'This client has opted out of WhatsApp communication. Messaging is disabled.',
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
                'available_whatsapp_numbers' => $this->getAvailableSystemWhatsappNumbers(),
            ]
        );
    }

    public function getAvailableWhatsappNumbers()
    {
        $this->authorizeAdmin();

        return response()->json([
            'numbers' => $this->getAvailableSystemWhatsappNumbers(),
        ]);
    }

    public function getAvailableSystemWhatsappNumbers(): array
    {
        $numbersMap = [];

        // 1. WhatsApp Accounts configured in system
        try {
            $accounts = WhatsappAccount::with('bank:id,name,code')->get();
            foreach ($accounts as $acc) {
                $id = (string) ($acc->phone_number_id ?: $acc->id);
                $display = $acc->display_phone_number ?: $acc->name;
                $clean = preg_replace('/\D+/', '', (string) $display);
                $bankName = $acc->bank ? $acc->bank->name : null;
                $label = $display;
                if ($bankName) {
                    $label .= " ({$bankName} - {$acc->name})";
                } elseif ($acc->name && $acc->name !== $display) {
                    $label .= " ({$acc->name})";
                }

                $key = $acc->phone_number_id ? (string) $acc->phone_number_id : ($clean ?: (string) $acc->id);
                $numbersMap[$key] = [
                    'id' => (string) ($acc->phone_number_id ?: $acc->id),
                    'phone_number_id' => $acc->phone_number_id ? (string) $acc->phone_number_id : null,
                    'display_phone_number' => $display,
                    'label' => $label,
                    'name' => $acc->name,
                    'bank_name' => $bankName,
                    'source' => 'whatsapp_account',
                ];
            }
        } catch (\Throwable $e) {
            // Silently continue
        }

        // 2. Bank primary and secondary WhatsApp numbers
        try {
            $banks = Bank::query()
                ->whereNotNull('primary_whatsapp_number')
                ->orWhereNotNull('secondary_whatsapp_numbers')
                ->get();

            foreach ($banks as $bank) {
                if (!empty($bank->primary_whatsapp_number)) {
                    $clean = preg_replace('/\D+/', '', (string) $bank->primary_whatsapp_number);
                    $key = $clean ?: (string) $bank->primary_whatsapp_number;
                    if (!isset($numbersMap[$key])) {
                        $numbersMap[$key] = [
                            'id' => (string) $bank->primary_whatsapp_number,
                            'phone_number_id' => null,
                            'display_phone_number' => $bank->primary_whatsapp_number,
                            'label' => "{$bank->primary_whatsapp_number} ({$bank->name} - Primary)",
                            'name' => $bank->name,
                            'bank_name' => $bank->name,
                            'source' => 'bank_primary',
                        ];
                    }
                }

                if (!empty($bank->secondary_whatsapp_numbers) && is_array($bank->secondary_whatsapp_numbers)) {
                    foreach ($bank->secondary_whatsapp_numbers as $secNum) {
                        $secStr = is_string($secNum) ? $secNum : ($secNum['number'] ?? '');
                        if (!empty($secStr)) {
                            $clean = preg_replace('/\D+/', '', (string) $secStr);
                            $key = $clean ?: (string) $secStr;
                            if (!isset($numbersMap[$key])) {
                                $numbersMap[$key] = [
                                    'id' => (string) $secStr,
                                    'phone_number_id' => null,
                                    'display_phone_number' => (string) $secStr,
                                    'label' => "{$secStr} ({$bank->name} - Secondary)",
                                    'name' => $bank->name,
                                    'bank_name' => $bank->name,
                                    'source' => 'bank_secondary',
                                ];
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // Silently continue
        }

        // 3. Meta API Phone Numbers if accessible
        try {
            $service = app(MetaWhatsAppService::class);
            $metaNumbers = $service->getPhoneNumbers();
            foreach ($metaNumbers as $metaNum) {
                $phoneId = !empty($metaNum['id']) ? (string) $metaNum['id'] : null;
                $display = $metaNum['display_phone_number'] ?? $metaNum['verified_name'] ?? $phoneId;
                $verifiedName = $metaNum['verified_name'] ?? null;
                $clean = preg_replace('/\D+/', '', (string) $display);
                $key = $phoneId ?: ($clean ?: (string) $display);

                $label = (string) $display;
                if ($verifiedName && $verifiedName !== $display) {
                    $label .= " ({$verifiedName})";
                }

                if (isset($numbersMap[$key])) {
                    if ($phoneId && empty($numbersMap[$key]['phone_number_id'])) {
                        $numbersMap[$key]['phone_number_id'] = $phoneId;
                    }
                } else {
                    $numbersMap[$key] = [
                        'id' => (string) ($phoneId ?: $display),
                        'phone_number_id' => $phoneId,
                        'display_phone_number' => (string) $display,
                        'label' => $label,
                        'name' => $verifiedName ?: (string) $display,
                        'bank_name' => null,
                        'source' => 'meta',
                    ];
                }
            }
        } catch (\Throwable $e) {
            // Silently continue
        }

        // 4. Default SystemSetting Meta number
        try {
            $settings = SystemSetting::first();
            if ($settings && (!empty($settings->meta_whatsapp_phone_number_id) || !empty($settings->meta_whatsapp_display_phone_number))) {
                $phoneId = $settings->meta_whatsapp_phone_number_id ? (string) $settings->meta_whatsapp_phone_number_id : null;
                $display = $settings->meta_whatsapp_display_phone_number ?: $phoneId;
                $clean = preg_replace('/\D+/', '', (string) $display);
                $key = $phoneId ?: ($clean ?: (string) $display);

                if (!isset($numbersMap[$key])) {
                    $numbersMap[$key] = [
                        'id' => (string) ($phoneId ?: $display),
                        'phone_number_id' => $phoneId,
                        'display_phone_number' => (string) $display,
                        'label' => "{$display} (System Default Meta)",
                        'name' => 'System Default Meta',
                        'bank_name' => null,
                        'source' => 'system_settings',
                    ];
                }
            }
        } catch (\Throwable $e) {
            // Silently continue
        }

        // 5. Live WABA senders from WhatsApp service if available
        try {
            if (app()->bound(\App\Contracts\WhatsAppServiceInterface::class)) {
                $whatsApp = app(\App\Contracts\WhatsAppServiceInterface::class);
                $liveSenders = $whatsApp->listWhatsappSenders();
                /** @var \App\Services\BankWabaResolver $resolver */
                $resolver = app(\App\Services\BankWabaResolver::class);

                foreach ($liveSenders as $s) {
                    $pId = !empty($s['phone_number_id']) ? (string) $s['phone_number_id'] : null;
                    $num = !empty($s['number']) ? (string) $s['number'] : null;
                    $lbl = !empty($s['label']) ? (string) $s['label'] : ($pId ?: $num);
                    $clean = $num ? preg_replace('/\D+/', '', $num) : null;
                    $key = $pId ?: ($clean ?: $num);

                    if (!$key) continue;

                    $matchedBank = $resolver->resolveBankForSender($pId, $num, $lbl);
                    $bankName = $matchedBank?->name;

                    $label = $num ?: $lbl;
                    if ($bankName) {
                        $label .= " ({$bankName} - {$lbl})";
                    } elseif ($lbl && $lbl !== $num) {
                        $label .= " ({$lbl})";
                    }

                    if (isset($numbersMap[$key])) {
                        if ($pId && empty($numbersMap[$key]['phone_number_id'])) {
                            $numbersMap[$key]['phone_number_id'] = $pId;
                        }
                        if ($num && empty($numbersMap[$key]['display_phone_number'])) {
                            $numbersMap[$key]['display_phone_number'] = $num;
                        }
                        if ($bankName && empty($numbersMap[$key]['bank_name'])) {
                            $numbersMap[$key]['bank_name'] = $bankName;
                        }
                    } else {
                        $numbersMap[$key] = [
                            'id' => (string) ($pId ?: ($num ?: $key)),
                            'phone_number_id' => $pId,
                            'display_phone_number' => (string) ($num ?: $lbl),
                            'label' => $label,
                            'name' => $lbl,
                            'bank_name' => $bankName,
                            'source' => 'waba_live_sender',
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            // Silently continue
        }

        return array_values($numbersMap);
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
        if (!$user || !$user->canAccessAnySettings()) {
            abort(403, 'Unauthorized access to settings.');
        }
    }

    private function authorizeWabaNumbers(): void
    {
        $user = Auth::user();
        if (!$user || !$user->canAccessWabaNumbersSettings()) {
            abort(403, 'You are not allowed to manage WABA phone numbers.');
        }
    }

    private function authorizePauseWhatsappNumbers(): void
    {
        $user = Auth::user();
        if (!$user || !$user->canPauseWhatsappNumbers()) {
            abort(403, 'You are not allowed to pause or resume WhatsApp phone numbers.');
        }
    }
}
