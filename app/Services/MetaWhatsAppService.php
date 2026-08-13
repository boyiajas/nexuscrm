<?php

namespace App\Services;

use App\Contracts\WhatsAppServiceInterface;
use App\Models\SystemSetting;
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
    }

    public function getPhoneNumbers(): array
    {
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

        return $response['data'] ?? [];
    }

    public function addPhoneNumber(string $cc, string $phoneNumber, ?string $verifiedName = null): array
    {
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
        return $this->post("{$phoneNumberId}/verify_code", [
            'code' => $code,
        ]);
    }

    public function registerPhoneNumber(string $phoneNumberId, string $pin): array
    {
        return $this->post("{$phoneNumberId}/register", [
            'messaging_product' => 'whatsapp',
            'pin' => $pin,
        ]);
    }

    public function resolveSenderContext(?string $overrideFrom = null): array
    {
        if ($overrideFrom) {
            $senders = $this->listWhatsappSenders();
            $normalizedOverride = self::normalizePhoneNumber($overrideFrom);
            
            $sender = collect($senders)->first(function ($s) use ($normalizedOverride, $overrideFrom) {
                return self::normalizePhoneNumber($s['number']) === $normalizedOverride || $s['number'] === $overrideFrom;
            });
            
            if ($sender) {
                return [
                    'phone_number_id' => $sender['phone_number_id'],
                    'display_phone_number' => $sender['number'],
                ];
            }
        }

        return [
            'phone_number_id' => $this->phoneNumberId,
            'display_phone_number' => $this->displayPhoneNumber ?: $this->phoneNumberId,
        ];
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

        $senderContext = $this->resolveSenderContext($overrideFrom);
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
        $response = $this->post("{$this->phoneNumberId}/messages", [
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
        $templates = $this->getWhatsAppTemplates(false, 200);
        foreach ($templates as $template) {
            if (($template['sid'] ?? null) === $templateId) {
                return [
                    'id' => $template['sid'],
                    'name' => $template['friendly_name'],
                    'language' => $template['language'],
                    'status' => $template['whatsapp']['status'] ?? null,
                    'category' => $template['whatsapp']['category'] ?? null,
                    'preview' => $template['preview'] ?? null,
                    'variables' => $template['variables'] ?? [],
                    'media_urls' => $template['media'] ?? [],
                    'header_format' => $template['header_format'] ?? null,
                    'header_text' => $template['header_text'] ?? null,
                    'footer_text' => $template['footer_text'] ?? null,
                    'buttons' => $template['buttons'] ?? [],
                    'components' => $template['components'] ?? [],
                ];
            }
        }

        throw new \RuntimeException("Meta WhatsApp template [{$templateId}] not found.");
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

        return $this->post("{$destinationWabaId}/migrate_message_templates", [
            'source_waba_id' => $sourceWabaId,
            'template_ids' => array_values($templateIds),
        ]);
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
        $response = Http::withToken($this->accessToken)->timeout(15)->get($url, $query);
        return $this->decodeResponse($response->status(), $response->json() ?? [], $path);
    }

    protected function post(string $path, array $payload): array
    {
        $response = Http::withToken($this->accessToken)->timeout(15)->post("{$this->baseUrl}/{$path}", $payload);
        return $this->decodeResponse($response->status(), $response->json() ?? [], $path);
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
}
