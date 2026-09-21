<?php

namespace App\Services;

use App\Contracts\WhatsAppServiceInterface;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MetaWhatsAppService implements WhatsAppServiceInterface
{
    public const REQUIRED_TOKEN_SCOPES = [
        'whatsapp_business_management',
        'whatsapp_business_messaging',
    ];

    public const RECOMMENDED_TOKEN_SCOPES = [
        'business_management',
    ];

    private string $baseUrl = 'https://graph.facebook.com/v25.0';
    private ?string $appId = null;
    private ?string $accessToken = null;
    private ?string $businessAccountId = null;
    private ?string $phoneNumberId = null;
    private ?string $displayPhoneNumber = null;
    private ?string $verifyToken = null;
    private ?string $appSecret = null;

    public function __construct()
    {
        $settings = SystemSetting::first();

        $this->appId = $settings?->meta_app_id ?: Config::get('services.meta_whatsapp.app_id');
        $this->accessToken = $settings?->meta_access_token ?: Config::get('services.meta_whatsapp.access_token');
        $this->businessAccountId = $settings?->meta_whatsapp_business_account_id ?: Config::get('services.meta_whatsapp.business_account_id');
        $this->phoneNumberId = $settings?->meta_whatsapp_phone_number_id ?: Config::get('services.meta_whatsapp.phone_number_id');
        $this->displayPhoneNumber = $settings?->meta_whatsapp_display_phone_number ?: Config::get('services.meta_whatsapp.display_phone_number');
        $this->verifyToken = $settings?->meta_webhook_verify_token ?: Config::get('services.meta_whatsapp.verify_token');
        $this->appSecret = $settings?->meta_app_secret ?: Config::get('services.meta_whatsapp.app_secret');

        if (empty($this->accessToken) || empty($this->businessAccountId) || empty($this->phoneNumberId)) {
            throw new \RuntimeException('Meta WhatsApp credentials are incomplete. Configure access token, business account ID, and phone number ID.');
        }

        // Self-heal: If phoneNumberId was erroneously configured as the WABA ID,
        // attempt to auto-resolve to the first registered phone number ID under this WABA.
        if ((string) $this->phoneNumberId === (string) $this->businessAccountId) {
            $this->selfHealPhoneNumberIdFromWaba();
        }
    }

    protected function selfHealPhoneNumberIdFromWaba(): void
    {
        try {
            $waAccount = \App\Models\WhatsappAccount::where('waba_id', $this->businessAccountId)
                ->where('phone_number_id', '!=', $this->businessAccountId)
                ->first();

            if ($waAccount && !empty($waAccount->phone_number_id)) {
                $this->phoneNumberId = (string) $waAccount->phone_number_id;
                if (!empty($waAccount->display_phone_number)) {
                    $this->displayPhoneNumber = (string) $waAccount->display_phone_number;
                }
                return;
            }

            $numbers = $this->getPhoneNumbers();
            foreach ($numbers as $num) {
                if (!empty($num['id']) && (string) $num['id'] !== (string) $this->businessAccountId) {
                    $this->phoneNumberId = (string) $num['id'];
                    if (!empty($num['display_phone_number'])) {
                        $this->displayPhoneNumber = (string) $num['display_phone_number'];
                    }
                    Log::info('Self-healed Meta WhatsApp phone number ID from WABA account.', [
                        'waba_id' => $this->businessAccountId,
                        'resolved_phone_number_id' => $this->phoneNumberId,
                        'display_phone_number' => $this->displayPhoneNumber,
                    ]);
                    break;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Unable to self-heal phone number ID from WABA account: ' . $e->getMessage());
        }
    }

    public static function normalizePhoneNumber(string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', $raw);
        if (!$digits) {
            return null;
        }

        if (str_starts_with($raw, '+')) {
            return '+' . $digits;
        }

        if (str_starts_with($digits, '27') && strlen($digits) >= 11) {
            return '+' . substr($digits, 0, 11);
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '+27' . substr($digits, 1);
        }

        if (strlen($digits) === 9) {
            return '+27' . $digits;
        }

        if (strlen($digits) >= 10) {
            return '+' . $digits;
        }

        return null;
    }

    public function verifyToken(): ?string
    {
        return $this->verifyToken;
    }

    public function appSecret(): ?string
    {
        return $this->appSecret;
    }

    public function validateConfiguredTokenPermissions(): array
    {
        if (empty($this->appId) || empty($this->appSecret) || empty($this->accessToken)) {
            throw new \RuntimeException('Meta app ID, app secret, and access token are required to validate token permissions.');
        }

        $response = Http::timeout(15)->get("{$this->baseUrl}/debug_token", [
            'input_token' => $this->accessToken,
            'access_token' => "{$this->appId}|{$this->appSecret}",
        ]);

        $payload = $this->decodeResponse($response->status(), $response->json() ?? [], 'debug_token');
        $tokenData = $payload['data'] ?? [];

        $grantedScopes = collect($tokenData['scopes'] ?? [])
            ->filter(fn ($scope) => is_string($scope) && trim($scope) !== '')
            ->values()
            ->all();

        $missingRequired = array_values(array_diff(self::REQUIRED_TOKEN_SCOPES, $grantedScopes));
        $missingRecommended = array_values(array_diff(self::RECOMMENDED_TOKEN_SCOPES, $grantedScopes));

        $isValid = (bool) ($tokenData['is_valid'] ?? false);
        $appIdMatches = (string) ($tokenData['app_id'] ?? '') === (string) $this->appId;
        $expiresAt = !empty($tokenData['expires_at']) ? now()->setTimestamp((int) $tokenData['expires_at'])->toDateTimeString() : null;

        $status = 'healthy';
        if (!$isValid || !$appIdMatches || !empty($missingRequired)) {
            $status = 'error';
        } elseif (!empty($missingRecommended)) {
            $status = 'warning';
        }

        return [
            'status' => $status,
            'is_valid' => $isValid,
            'app_id_matches' => $appIdMatches,
            'configured_app_id' => $this->appId,
            'token_app_id' => $tokenData['app_id'] ?? null,
            'token_type' => $tokenData['type'] ?? null,
            'expires_at' => $expiresAt,
            'granted_scopes' => $grantedScopes,
            'required_scopes' => self::REQUIRED_TOKEN_SCOPES,
            'recommended_scopes' => self::RECOMMENDED_TOKEN_SCOPES,
            'missing_required_scopes' => $missingRequired,
            'missing_recommended_scopes' => $missingRecommended,
            'granular_scopes' => $tokenData['granular_scopes'] ?? [],
        ];
    }

    public function listWhatsappSenders(): array
    {
        $cacheKey = 'meta_whatsapp_senders_list_' . md5((string) $this->businessAccountId);
        return Cache::remember($cacheKey, 300, function () {
            try {
                $metaNumbers = $this->getPhoneNumbers();
                $numbers = array_map(function($num) {
                    return [
                        'number' => $num['display_phone_number'] ?? null,
                        'label' => $num['verified_name'] ?? 'Meta WhatsApp Number',
                        'default' => false,
                        'phone_number_id' => $num['id'],
                    ];
                }, $metaNumbers);

                foreach ($numbers as &$num) {
                    if ($num['phone_number_id'] == $this->phoneNumberId) {
                        $num['default'] = true;
                    }
                }

                return $numbers;
            } catch (\Throwable $e) {
                Log::warning('Failed to load dynamic Meta WhatsApp senders for webhook list, falling back to static config.', [
                    'error' => $e->getMessage()
                ]);
                return [[
                    'number' => $this->displayPhoneNumber ?: $this->phoneNumberId,
                    'label' => 'Meta WhatsApp Number',
                    'default' => true,
                    'phone_number_id' => $this->phoneNumberId,
                ]];
            }
        });
    }

    public function getPhoneNumbers(bool $forceRefresh = false): array
    {
        $cacheKey = 'meta_waba_phone_numbers_' . md5((string) $this->businessAccountId);
        $cooldownKey = 'meta_waba_rate_limit_cooldown_' . md5((string) $this->businessAccountId);
        $lastGoodKey = 'meta_waba_phone_numbers_last_good_' . md5((string) $this->businessAccountId);

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        // If currently in rate-limit cooldown and not forced, return cached fallback immediately without calling Meta
        if (!$forceRefresh && Cache::has($cooldownKey)) {
            $lastGood = Cache::get($lastGoodKey);
            if (!empty($lastGood)) {
                return $lastGood;
            }
            return $this->fallbackPhoneNumbersList();
        }

        $cached = Cache::get($cacheKey);
        if (!$forceRefresh && !empty($cached)) {
            return $cached;
        }

        try {
            $fields = [
                'id',
                'display_phone_number',
                'verified_name',
                'quality_rating',
                'code_verification_status',
                'name_status',
                'messaging_limit_tier',
                'platform_type',
                'throughput',
            ];

            $response = $this->get("{$this->businessAccountId}/phone_numbers", [
                'fields' => implode(',', $fields),
            ]);

            $numbers = $response['data'] ?? [];
            if (!empty($numbers)) {
                Cache::put($cacheKey, $numbers, 600);
                Cache::put($lastGoodKey, $numbers, 86400);
            }

            return $numbers;
        } catch (\Throwable $e) {
            $isRateLimit = str_contains($e->getMessage(), '80008')
                || str_contains(strtolower($e->getMessage()), 'too many calls')
                || str_contains($e->getMessage(), '429');

            if ($isRateLimit) {
                // Trip the circuit breaker for 180 seconds to give Meta's rolling rate limit time to cool down
                Cache::put($cooldownKey, true, 180);
                Log::warning('Meta WABA rate limit (#80008) active. Engaged 180s backoff cooldown to prevent hammering Meta.', [
                    'business_account_id' => $this->businessAccountId,
                ]);
            }

            $lastGood = Cache::get($lastGoodKey);
            if (!empty($lastGood)) {
                return $lastGood;
            }

            if (!$forceRefresh) {
                return $this->fallbackPhoneNumbersList();
            }

            throw $e;
        }
    }

    protected function fallbackPhoneNumbersList(): array
    {
        $phoneId = $this->phoneNumberId;
        $dispNumber = $this->displayPhoneNumber ?: '+27614776401';
        if (!empty($phoneId) && (string) $phoneId !== (string) $this->businessAccountId) {
            return [[
                'id' => $phoneId,
                'display_phone_number' => $dispNumber,
                'verified_name' => 'Strauss Daly CRM',
                'quality_rating' => 'GREEN',
                'code_verification_status' => 'VERIFIED',
                'name_status' => 'APPROVED',
            ]];
        }
        return [];
    }

    public function clearPhoneNumbersCache(): void
    {
        Cache::forget('meta_waba_phone_numbers_' . md5((string) $this->businessAccountId));
        Cache::forget('meta_whatsapp_senders_list_' . md5((string) $this->businessAccountId));
        Cache::forget('meta_waba_rate_limit_cooldown_' . md5((string) $this->businessAccountId));
    }

    public function addPhoneNumber(string $cc, string $phoneNumber, ?string $verifiedName = null): array
    {
        $this->clearPhoneNumbersCache();

        $payload = [
            'cc' => $cc,
            'phone_number' => $phoneNumber,
        ];
        if ($verifiedName) {
            $payload['verified_name'] = $verifiedName;
        }
        
        return $this->post("{$this->businessAccountId}/phone_numbers", $payload);
    }

    public function requestVerificationCode(string $phoneNumberId, string $method = 'SMS'): array
    {
        return $this->post("{$phoneNumberId}/request_code", [
            'code_method' => strtoupper($method),
            'language' => 'en',
        ]);
    }

    public function verifyCode(string $phoneNumberId, string $code): array
    {
        $this->clearPhoneNumbersCache();

        return $this->post("{$phoneNumberId}/verify_code", [
            'code' => $code,
        ]);
    }

    public function registerPhoneNumber(string $phoneNumberId, string $pin): array
    {
        $this->clearPhoneNumbersCache();

        return $this->post("{$phoneNumberId}/register", [
            'messaging_product' => 'whatsapp',
            'pin' => $pin,
        ]);
    }

    public function resolveSenderContext(?string $overrideFrom = null): array
    {
        if ($overrideFrom) {
            $overrideStr = trim((string) $overrideFrom);
            $normalizedOverride = self::normalizePhoneNumber($overrideStr);

            // Guard: If override matches the WABA ID, DO NOT use it as a phone_number_id.
            // Map it to the valid phone number under this WABA.
            if ($overrideStr === (string) $this->businessAccountId) {
                return [
                    'phone_number_id' => $this->phoneNumberId,
                    'display_phone_number' => $this->displayPhoneNumber ?: $this->phoneNumberId,
                ];
            }

            $senders = $this->listWhatsappSenders();
            
            $sender = collect($senders)->first(function ($s) use ($normalizedOverride, $overrideStr) {
                return self::normalizePhoneNumber($s['number'] ?? '') === $normalizedOverride 
                    || ($s['number'] ?? '') === $overrideStr
                    || (string) ($s['phone_number_id'] ?? '') === $overrideStr;
            });
            
            if ($sender && !empty($sender['phone_number_id']) && (string) $sender['phone_number_id'] !== (string) $this->businessAccountId) {
                return [
                    'phone_number_id' => $sender['phone_number_id'],
                    'display_phone_number' => $sender['number'] ?? $overrideStr,
                ];
            }

            try {
                // Check if overrideFrom matches a known WABA ID in WhatsappAccount
                $waByWaba = \App\Models\WhatsappAccount::where('waba_id', $overrideStr)
                    ->where('phone_number_id', '!=', $overrideStr)
                    ->first();
                if ($waByWaba && $waByWaba->phone_number_id) {
                    return [
                        'phone_number_id' => (string) $waByWaba->phone_number_id,
                        'display_phone_number' => $waByWaba->display_phone_number ?: $overrideStr,
                    ];
                }

                $waAccount = \App\Models\WhatsappAccount::where('phone_number_id', $overrideStr)
                    ->orWhere('display_phone_number', $overrideStr)
                    ->orWhere('display_phone_number', $normalizedOverride)
                    ->first();

                if (!$waAccount) {
                    $digits = preg_replace('/\D+/', '', $overrideStr);
                    if ($digits) {
                        $waAccount = \App\Models\WhatsappAccount::all()->first(function ($a) use ($digits) {
                            $aDigits = preg_replace('/\D+/', '', (string) $a->display_phone_number);
                            return $aDigits === $digits || ($digits && substr($aDigits, -9) === substr($digits, -9));
                        });
                    }
                }

                if ($waAccount && $waAccount->phone_number_id && (string) $waAccount->phone_number_id !== (string) $this->businessAccountId) {
                    return [
                        'phone_number_id' => (string) $waAccount->phone_number_id,
                        'display_phone_number' => $waAccount->display_phone_number ?: $overrideStr,
                    ];
                }
            } catch (\Throwable $e) {
                // Ignore DB lookup issues if model or table is missing
            }

            // Only treat as a direct phone_number_id if it does NOT match the WABA ID
            if (preg_match('/^\d{14,20}$/', $overrideStr) && $overrideStr !== (string) $this->businessAccountId) {
                return [
                    'phone_number_id' => $overrideStr,
                    'display_phone_number' => $overrideStr,
                ];
            }
        }

        return [
            'phone_number_id' => $this->phoneNumberId,
            'display_phone_number' => $this->displayPhoneNumber ?: $this->phoneNumberId,
        ];
    }

    /**
     * Resolves the active WhatsApp sender context using the strict hierarchy:
     * 1. Bank's primary_whatsapp_number or assigned WhatsApp account (Highest)
     * 2. Department's primary_whatsapp_number or assigned WhatsApp account (Fallback)
     * 3. System Settings Default Active WhatsApp Profile (System Default)
     */
    public function resolveSenderForClient(?\App\Models\Client $client = null, ?\App\Models\Bank $bank = null, ?\App\Models\Department $department = null): array
    {
        // 1. Resolve Bank context
        $activeBank = $bank;
        if (!$activeBank && $client && $client->bank_id) {
            $activeBank = $client->bank ?: \App\Models\Bank::find($client->bank_id);
        }

        if ($activeBank && !empty($activeBank->primary_whatsapp_number)) {
            $context = $this->resolveSenderContext($activeBank->primary_whatsapp_number);
            if (!empty($context['phone_number_id'])) {
                return $context;
            }
        }

        // 2. Resolve Department context
        $activeDept = $department;
        if (!$activeDept && $client) {
            $activeDept = $client->departments->first();
        }

        if ($activeDept && !empty($activeDept->primary_whatsapp_number)) {
            $context = $this->resolveSenderContext($activeDept->primary_whatsapp_number);
            if (!empty($context['phone_number_id'])) {
                return $context;
            }
        }

        // 3. Fallback to System Default Profile
        return $this->resolveSenderContext(null);
    }

    public function getPhoneNumberProfile(): array
    {
        $fields = [
            'display_phone_number',
            'verified_name',
            'quality_rating',
            'code_verification_status',
            'name_status',
            'messaging_limit_tier',
            'platform_type',
            'throughput',
        ];

        $response = $this->get($this->phoneNumberId, [
            'fields' => implode(',', $fields),
        ]);

        $listingMatch = null;
        if (empty($response['messaging_limit_tier']) || empty($response['throughput'])) {
            try {
                $listing = $this->get("{$this->businessAccountId}/phone_numbers", [
                    'fields' => implode(',', array_merge(['id'], $fields)),
                ]);

                $listingMatch = collect($listing['data'] ?? [])
                    ->first(fn (array $phone) => (string) ($phone['id'] ?? '') === (string) $this->phoneNumberId);
            } catch (\Throwable $e) {
                Log::warning('Meta WhatsApp phone_numbers fallback lookup failed', [
                    'business_account_id' => $this->businessAccountId,
                    'phone_number_id' => $this->phoneNumberId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $merged = array_merge($response, is_array($listingMatch) ? $listingMatch : []);

        return [
            'display_phone_number' => $merged['display_phone_number'] ?? $this->displayPhoneNumber,
            'verified_name' => $merged['verified_name'] ?? null,
            'quality_rating' => $merged['quality_rating'] ?? null,
            'code_verification_status' => $merged['code_verification_status'] ?? null,
            'name_status' => $merged['name_status'] ?? null,
            'messaging_limit_tier' => $merged['messaging_limit_tier'] ?? null,
            'platform_type' => $merged['platform_type'] ?? null,
            'throughput' => $merged['throughput'] ?? null,
            'fetched_at' => now()->toDateTimeString(),
        ];
    }

    public function sendTemplateFromSubjectMessage(
        string $toE164,
        ?string $overrideTemplateSid,
        string $subject = '',
        string $message = '',
        array $templateVariables = [],
        ?string $overrideFrom = null,
        ?string $overrideMsid = null
    ): array {
        $to = self::normalizePhoneNumber($toE164);
        if (!$to) {
            throw new \InvalidArgumentException('Invalid recipient number provided.');
        }

        $templateName = trim((string) $overrideTemplateSid);
        if ($templateName === '') {
            throw new \RuntimeException('Template name is required for Meta WhatsApp sends.');
        }

        $senderContext = $this->resolveSenderContext($overrideFrom);

        $template = $this->getTemplateDetails($templateName);
        $bodyComponent = $this->bodyComponent($template['components'] ?? []);
        $exampleBody = (string) ($bodyComponent['text'] ?? '');
        preg_match_all('/{{\d+}}/', $exampleBody, $matches);
        $placeholderCount = count($matches[0] ?? []);

        $headerParams = [];
        $bodyParams = [];

        if (!empty($templateVariables)) {
            $isAssociative = array_keys($templateVariables) !== range(0, count($templateVariables) - 1);
            if ($isAssociative) {
                foreach ($templateVariables as $key => $value) {
                    if (str_starts_with((string)$key, 'header_')) {
                        $headerParams[] = ['type' => 'text', 'text' => (string) $value];
                    } elseif (str_starts_with((string)$key, 'body_')) {
                        $bodyParams[] = ['type' => 'text', 'text' => (string) $value];
                    } else {
                        $bodyParams[] = ['type' => 'text', 'text' => (string) $value];
                    }
                }
            } else {
                foreach (array_values($templateVariables) as $value) {
                    $bodyParams[] = ['type' => 'text', 'text' => (string) $value];
                }
            }
        } elseif ($placeholderCount === 1) {
            $bodyParams[] = ['type' => 'text', 'text' => $subject !== '' ? $subject : $message];
        } elseif ($placeholderCount >= 2) {
            $bodyParams[] = ['type' => 'text', 'text' => $subject];
            $bodyParams[] = ['type' => 'text', 'text' => $message];

            for ($i = 2; $i < $placeholderCount; $i++) {
                $bodyParams[] = ['type' => 'text', 'text' => ''];
            }
        }

        $headerFormat = strtoupper((string) ($template['header_format'] ?? ''));
        $mediaUrl = $template['media_urls'][0] ?? null;

        if (in_array($headerFormat, ['IMAGE', 'DOCUMENT', 'VIDEO']) && $mediaUrl) {
            $mediaType = strtolower($headerFormat);

            try {
                $mediaId = $this->uploadMediaFromUrl($mediaUrl, $senderContext['phone_number_id'], $headerFormat);
                $headerParams[] = [
                    'type' => $mediaType,
                    $mediaType => [
                        'id' => $mediaId,
                    ],
                ];
            } catch (\Exception $e) {
                Log::error('Media upload to Meta failed — template cannot be sent without a valid media_id.', [
                    'url'           => $mediaUrl,
                    'phone_num_id'  => $senderContext['phone_number_id'],
                    'error_message' => $e->getMessage(),
                ]);
                throw new \RuntimeException('Media upload error: ' . $e->getMessage(), 0, $e);
            }
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => ltrim($to, '+'),
            'type' => 'template',
            'template' => [
                'name' => $template['name'],
                'language' => [
                    'code' => $template['language'] ?? 'en_US',
                ],
                'components' => [],
            ],
        ];

        if (!empty($headerParams)) {
            $payload['template']['components'][] = [
                'type' => 'header',
                'parameters' => $headerParams,
            ];
        }

        if (!empty($bodyParams)) {
            $payload['template']['components'][] = [
                'type' => 'body',
                'parameters' => $bodyParams,
            ];
        }

        if (empty($payload['template']['components'])) {
            unset($payload['template']['components']);
        }

        $response = $this->post("{$senderContext['phone_number_id']}/messages", $payload);
        $messageId = $response['messages'][0]['id'] ?? null;

        Log::info('Meta WhatsApp template sent', [
            'to' => $to,
            'template' => $templateName,
            'message_id' => $messageId,
        ]);

        return [
            'sid' => $messageId,
            'message_id' => $messageId,
            'status' => 'accepted',
            'phone_number_id' => $senderContext['phone_number_id'],
            'display_phone_number' => $senderContext['display_phone_number'],
            'raw' => $response,
        ];
    }

    public function sendPlainWhatsapp(string $toE164, string $body, ?string $overrideFrom = null, ?string $overrideMsid = null): array
    {
        $to = self::normalizePhoneNumber($toE164);
        if (!$to) {
            throw new \InvalidArgumentException('Invalid recipient number provided.');
        }

        if (trim($body) === '') {
            throw new \InvalidArgumentException('Message body cannot be empty.');
        }

        $senderContext = $this->resolveSenderContext($overrideFrom);
        $response = $this->post("{$senderContext['phone_number_id']}/messages", [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => ltrim($to, '+'),
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $body,
            ],
        ]);

        $messageId = $response['messages'][0]['id'] ?? null;

        return [
            'sid' => $messageId,
            'message_id' => $messageId,
            'status' => 'accepted',
            'phone_number_id' => $senderContext['phone_number_id'],
            'display_phone_number' => $senderContext['display_phone_number'],
            'raw' => $response,
        ];
    }

    public function sendTextMessage(string $to, string $message, ?string $overrideFrom = null): array
    {
        return $this->sendPlainWhatsapp($to, $message, $overrideFrom);
    }

    public function sendMediaWhatsapp(
        string $toE164,
        string $mediaType,
        string $mediaUrl,
        ?string $caption = null,
        ?string $filename = null,
        ?string $overrideFrom = null
    ): array {
        $to = self::normalizePhoneNumber($toE164);
        if (!$to) {
            throw new \InvalidArgumentException('Invalid recipient number provided.');
        }

        $senderContext = $this->resolveSenderContext($overrideFrom);
        $type = strtolower($mediaType);

        $mediaObject = [
            'link' => $mediaUrl,
        ];

        if ($caption !== null && trim($caption) !== '') {
            $mediaObject['caption'] = $caption;
        }

        if ($filename !== null && trim($filename) !== '' && $type === 'document') {
            $mediaObject['filename'] = $filename;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => ltrim($to, '+'),
            'type' => $type,
            $type => $mediaObject,
        ];

        $response = $this->post("{$senderContext['phone_number_id']}/messages", $payload);
        $messageId = $response['messages'][0]['id'] ?? null;

        return [
            'sid' => $messageId,
            'message_id' => $messageId,
            'status' => 'accepted',
            'phone_number_id' => $senderContext['phone_number_id'],
            'display_phone_number' => $senderContext['display_phone_number'],
            'raw' => $response,
        ];
    }

    public function downloadMedia(string $mediaId): ?array
    {
        try {
            $metaResponse = Http::withToken($this->accessToken)->get("{$this->baseUrl}/{$mediaId}");
            if (!$metaResponse->successful()) {
                Log::warning('Meta media metadata request failed', ['media_id' => $mediaId, 'status' => $metaResponse->status()]);
                return null;
            }

            $mediaUrl = $metaResponse->json('url');
            $mimeType = $metaResponse->json('mime_type');
            if (!$mediaUrl) {
                return null;
            }

            $fileResponse = Http::withToken($this->accessToken)->get($mediaUrl);
            if (!$fileResponse->successful()) {
                Log::warning('Meta media file download request failed', ['media_url' => $mediaUrl, 'status' => $fileResponse->status()]);
                return null;
            }

            return [
                'content' => $fileResponse->body(),
                'mime_type' => $mimeType ?: 'application/octet-stream',
            ];
        } catch (\Throwable $e) {
            Log::error('Failed downloading WhatsApp media by ID', [
                'media_id' => $mediaId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function getWhatsAppTemplates(bool $onlyApproved = true, int $pageSize = 50): array
    {
        $templates = [];
        $nextPath = "{$this->businessAccountId}/message_templates";
        $query = [
            'limit' => min(max($pageSize, 1), 100),
            'fields' => 'id,name,status,language,category,components',
        ];

        while ($nextPath) {
            $response = $this->get($nextPath, $query);
            $query = [];

            foreach (($response['data'] ?? []) as $template) {
                $mapped = $this->mapTemplate($template);
                $status = strtolower((string) ($mapped['whatsapp']['status'] ?? ''));
                if ($onlyApproved && $status !== 'approved') {
                    continue;
                }

                $templates[] = $mapped;
            }

            $nextPath = $response['paging']['next'] ?? null;
        }

        return $templates;
    }

    public function getTemplateDetails(string $templateId): array
    {
        // 1. Check local DB cache first (fast, zero network overhead)
        $cached = \App\Models\WhatsappTemplateCache::where('sid', $templateId)
            ->orWhere('friendly_name', $templateId)
            ->orWhere('meta_id', $templateId)
            ->first();

        if ($cached) {
            $apiArray = $cached->toApiArray();
            return [
                'id'            => $apiArray['sid'] ?? $apiArray['id'],
                'name'          => $apiArray['name'] ?? $apiArray['sid'],
                'language'      => $apiArray['language'] ?? 'en_US',
                'status'        => $apiArray['status'] ?? null,
                'category'      => $apiArray['category'] ?? null,
                'preview'       => $apiArray['body_preview'] ?? null,
                'variables'     => $apiArray['variables'] ?? [],
                'media_urls'    => $apiArray['media_urls'] ?? [],
                'header_format' => $apiArray['header_format'] ?? null,
                'header_text'   => $apiArray['header_text'] ?? null,
                'footer_text'   => $apiArray['footer_text'] ?? null,
                'buttons'       => $apiArray['buttons'] ?? [],
                'components'    => $apiArray['components'] ?? ($cached->raw_whatsapp['components'] ?? []),
            ];
        }

        // 2. Cache Meta API calls so rapid calls don't bombard Meta Graph API
        return \Illuminate\Support\Facades\Cache::remember('waba_template_details_' . md5($templateId), 3600, function () use ($templateId) {
            $templates = $this->getWhatsAppTemplates(false, 200);
            foreach ($templates as $template) {
                if (($template['sid'] ?? null) === $templateId || ($template['friendly_name'] ?? null) === $templateId) {
                    return [
                        'id'            => $template['sid'],
                        'name'          => $template['friendly_name'],
                        'language'      => $template['language'],
                        'status'        => $template['whatsapp']['status'] ?? null,
                        'category'      => $template['whatsapp']['category'] ?? null,
                        'preview'       => $template['preview'] ?? null,
                        'variables'     => $template['variables'] ?? [],
                        'media_urls'    => $template['media'] ?? [],
                        'header_format' => $template['header_format'] ?? null,
                        'header_text'   => $template['header_text'] ?? null,
                        'footer_text'   => $template['footer_text'] ?? null,
                        'buttons'       => $template['buttons'] ?? [],
                        'components'    => $template['components'] ?? [],
                    ];
                }
            }

            throw new \RuntimeException("Meta WhatsApp template [{$templateId}] not found.");
        });
    }

    public function getTemplateApprovalStatus(string $templateId): array
    {
        $template = $this->getTemplateDetails($templateId);

        return [
            'whatsapp' => [
                'status' => strtolower((string) ($template['status'] ?? 'unknown')),
                'category' => strtolower((string) ($template['category'] ?? '')),
            ],
        ];
    }

    public function createWhatsAppTemplate(string $friendlyName, string $body, string $language = 'en_US', string $category = 'UTILITY', array $mediaUrls = []): array
    {
        if (!empty($mediaUrls)) {
            throw new \RuntimeException('Creating media-header templates from the CRM is not implemented yet. Create text templates here, or use Meta WhatsApp Manager for media templates.');
        }

        $templateName = Str::of($friendlyName)
            ->lower()
            ->replaceMatches('/[^a-z0-9_]+/', '_')
            ->trim('_')
            ->value();

        if ($templateName === '') {
            throw new \RuntimeException('Template name is required.');
        }

        $normalizedLanguage = trim($language) !== '' ? trim($language) : 'en_US';
        $normalizedCategory = strtoupper(trim($category) !== '' ? trim($category) : 'UTILITY');

        $response = $this->post("{$this->businessAccountId}/message_templates", [
            'name' => $templateName,
            'language' => $normalizedLanguage,
            'category' => $normalizedCategory,
            'components' => [
                [
                    'type' => 'BODY',
                    'text' => $body,
                ],
            ],
        ]);

        return [
            'sid' => $templateName,
            'friendly_name' => $templateName,
            'language' => $normalizedLanguage,
            'preview' => $body,
            'variables' => [],
            'whatsapp' => [
                'status' => $response['status'] ?? 'PENDING',
                'category' => strtolower($normalizedCategory),
            ],
            'media' => [],
            'header_format' => null,
            'header_text' => null,
            'footer_text' => null,
            'buttons' => [],
            'raw' => $response,
        ];
    }

    public function updateWhatsAppTemplate(string $templateId, array $data): array
    {
        throw new \RuntimeException('Updating Meta templates from the app is not implemented yet. Manage templates in Meta WhatsApp Manager.');
    }

    public function deleteWhatsAppTemplate(string $templateId): bool
    {
        throw new \RuntimeException('Deleting Meta templates from the app is not implemented yet. Manage templates in Meta WhatsApp Manager.');
    }

    public function submitTemplateForApproval(string $templateId, string $category = 'UTILITY'): array
    {
        $template = $this->getTemplateDetails($templateId);

        return [
            'ok' => true,
            'message' => 'Meta handles review as part of template creation. Check WhatsApp Manager for the latest review status.',
            'whatsapp' => [
                'status' => strtolower((string) ($template['status'] ?? 'unknown')),
                'category' => strtolower((string) ($template['category'] ?? $category)),
            ],
        ];
    }

    public function migrateTemplates(string $destinationWabaId, array $templateIds): array
    {
        $sourceWabaId = $this->businessAccountId;
        if (empty($sourceWabaId)) {
            throw new \RuntimeException('Source WABA ID is missing in active Meta config.');
        }

        // Meta requires numeric integer template IDs
        $numericIds = array_values(array_map('intval', $templateIds));

        return $this->post("{$destinationWabaId}/migrate_message_templates", [
            'source_waba_id' => $sourceWabaId,
            'template_ids'   => $numericIds,
        ]);
    }

    /**
     * Subscribe the configured Meta App to the active WABA so that Meta sends
     * delivery / read / inbound message webhooks to our callback URL.
     * This must be called once whenever the active WABA changes.
     */
    public function subscribeWebhook(): array
    {
        if (empty($this->businessAccountId)) {
            throw new \RuntimeException('WhatsApp Business Account ID is not configured.');
        }

        return $this->post("{$this->businessAccountId}/subscribed_apps", []);
    }

    /**
     * Check which apps are currently subscribed to the active WABA.
     */
    public function getWebhookSubscriptions(): array
    {
        return $this->get("{$this->businessAccountId}/subscribed_apps");
    }

    protected function bodyComponent(array $components): array
    {
        return collect($components)
            ->first(fn (array $component) => strtoupper((string) ($component['type'] ?? '')) === 'BODY')
            ?? [];
    }

    protected function headerComponent(array $components): array
    {
        return collect($components)
            ->first(fn (array $component) => strtoupper((string) ($component['type'] ?? '')) === 'HEADER')
            ?? [];
    }

    protected function footerComponent(array $components): array
    {
        return collect($components)
            ->first(fn (array $component) => strtoupper((string) ($component['type'] ?? '')) === 'FOOTER')
            ?? [];
    }

    protected function buttonsComponent(array $components): array
    {
        return collect($components)
            ->first(fn (array $component) => strtoupper((string) ($component['type'] ?? '')) === 'BUTTONS')
            ?? [];
    }

    protected function mapTemplate(array $template): array
    {
        $components = $template['components'] ?? [];
        $body = $this->bodyComponent($components);
        $header = $this->headerComponent($components);
        $footer = $this->footerComponent($components);
        $buttons = $this->buttonsComponent($components);

        $variables = [];
        preg_match_all('/{{(\d+)}}/', (string) ($header['text'] ?? ''), $headerMatches);
        foreach ($headerMatches[1] ?? [] as $index) {
            $variables['header_' . $index] = 'Header Variable ' . $index;
        }

        preg_match_all('/{{(\d+)}}/', (string) ($body['text'] ?? ''), $bodyMatches);
        foreach ($bodyMatches[1] ?? [] as $index) {
            $variables['body_' . $index] = 'Body Variable ' . $index;
        }

        $headerFormat = strtoupper((string) ($header['format'] ?? ''));
        $mediaUrls = [];
        if (!empty($header['example']['header_handle']) && is_array($header['example']['header_handle'])) {
            $mediaUrls = array_values(array_filter($header['example']['header_handle'], 'is_string'));
        }

        return [
            'meta_id' => $template['id'] ?? null,
            'sid' => $template['name'],
            'friendly_name' => $template['name'],
            'language' => $template['language'] ?? null,
            'preview' => $body['text'] ?? null,
            'variables' => $variables,
            'whatsapp' => [
                'status' => $template['status'] ?? null,
                'category' => strtolower((string) ($template['category'] ?? '')),
            ],
            'media' => $mediaUrls,
            'header_format' => $headerFormat ?: null,
            'header_text' => $header['text'] ?? null,
            'footer_text' => $footer['text'] ?? null,
            'buttons' => $buttons['buttons'] ?? [],
            'components' => $components,
        ];
    }

    protected function get(string $path, array $query = []): array
    {
        $url = str_starts_with($path, 'http') ? $path : "{$this->baseUrl}/{$path}";
        $response = Http::withToken($this->accessToken)
            ->retry(3, 500, $this->httpRetryWhen(), throw: false)
            ->timeout(15)
            ->get($url, $query);
        return $this->decodeResponse($response->status(), $response->json() ?? [], $path);
    }

    protected function post(string $path, array $payload): array
    {
        $request = Http::withToken($this->accessToken)->timeout(15);

        // A timed-out or 5xx message POST may already have been accepted by
        // Meta. Retrying it can send the same WhatsApp message more than once.
        if (!str_ends_with($path, '/messages')) {
            $request = $request->retry(3, 500, $this->httpRetryWhen(), throw: false);
        }

        $response = $request->post("{$this->baseUrl}/{$path}", $payload);
        return $this->decodeResponse($response->status(), $response->json() ?? [], $path);
    }

    protected function httpRetryWhen(): callable
    {
        return function (\Throwable $exception) {
            if ($exception instanceof \Illuminate\Http\Client\RequestException && $exception->response) {
                return $exception->response->status() >= 500;
            }
            return true;
        };
    }

    protected function decodeResponse(int $status, array $payload, string $path): array
    {
        if ($status >= 400) {
            $message = $payload['error']['message'] ?? 'Unknown Meta API error';
            Log::error('Meta WhatsApp API error', [
                'path' => $path,
                'status' => $status,
                'payload' => $payload,
            ]);

            throw new \RuntimeException("Meta API error [{$status}]: {$message}");
        }

        return $payload;
    }

    public function uploadMediaFromUrl(string $url, string $senderPhoneNumberId, ?string $headerFormat = 'IMAGE'): string
    {
        $cacheKey = 'meta_media_id_' . md5($url);
        if (\Illuminate\Support\Facades\Cache::has($cacheKey)) {
            return \Illuminate\Support\Facades\Cache::get($cacheKey);
        }

        // Meta CDN URLs (lookaside.fbsbx.com, scontent) require the access token to download.
        // Try authenticated download first, fall back to unauthenticated for public URLs.
        $downloadResponse = \Illuminate\Support\Facades\Http::withToken($this->accessToken)->get($url);
        if (!$downloadResponse->successful()) {
            // Retry without token for non-Meta public URLs
            $downloadResponse = \Illuminate\Support\Facades\Http::get($url);
        }
        if (!$downloadResponse->successful()) {
            throw new \RuntimeException(
                "Failed to download media from URL (status {$downloadResponse->status()}): {$url}"
            );
        }
        $content = $downloadResponse->body();
        if (empty($content)) {
            throw new \RuntimeException("Downloaded media content is empty from URL: {$url}");
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'meta_media');
        file_put_contents($tempFile, $content);
        $detectedMime = mime_content_type($tempFile);
        unlink($tempFile);

        // Normalize MIME type for Meta API compliance
        $mimeType = $detectedMime ?: 'image/jpeg';
        if (strtoupper((string) $headerFormat) === 'IMAGE' && !in_array($mimeType, ['image/jpeg', 'image/png'])) {
            $mimeType = 'image/jpeg';
        }

        $extMap = [
            'image/jpeg'      => '.jpg',
            'image/png'       => '.png',
            'video/mp4'       => '.mp4',
            'application/pdf' => '.pdf',
        ];

        $filename = basename(parse_url($url, PHP_URL_PATH)) ?: 'media_file';
        $ext = $extMap[$mimeType] ?? '.jpg';
        if (!preg_match('/\.(jpg|jpeg|png|mp4|pdf)$/i', $filename)) {
            $filename .= $ext;
        }

        Log::info('Uploading media to Meta API.', [
            'url'             => $url,
            'phone_number_id' => $senderPhoneNumberId,
            'mime_type'       => $mimeType,
            'filename'        => $filename,
            'content_size'    => strlen($content),
        ]);

        $response = \Illuminate\Support\Facades\Http::withToken($this->accessToken)
            ->attach('file', $content, $filename, ['Content-Type' => $mimeType])
            ->post("{$this->baseUrl}/{$senderPhoneNumberId}/media", [
                'messaging_product' => 'whatsapp',
                'type'              => $mimeType,
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to upload media to Meta: ' . $response->body());
        }

        $mediaId = $response->json('id');
        if (!$mediaId) {
            throw new \RuntimeException('Meta media upload returned no media ID. Response: ' . $response->body());
        }

        \Illuminate\Support\Facades\Cache::put($cacheKey, $mediaId, now()->addDays(29));

        return $mediaId;
    }
}
