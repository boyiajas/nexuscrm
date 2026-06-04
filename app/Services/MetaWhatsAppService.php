<?php

namespace App\Services;

use App\Contracts\WhatsAppServiceInterface;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaWhatsAppService implements WhatsAppServiceInterface
{
    private string $baseUrl = 'https://graph.facebook.com/v25.0';
    private ?string $accessToken = null;
    private ?string $businessAccountId = null;
    private ?string $phoneNumberId = null;
    private ?string $displayPhoneNumber = null;
    private ?string $verifyToken = null;
    private ?string $appSecret = null;

    public function __construct()
    {
        $settings = SystemSetting::first();

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

    public function listWhatsappSenders(): array
    {
        return [[
            'number' => $this->displayPhoneNumber ?: $this->phoneNumberId,
            'label' => 'Meta WhatsApp Number',
            'default' => true,
            'phone_number_id' => $this->phoneNumberId,
        ]];
    }

    public function sendTemplateFromSubjectMessage(
        string $toE164,
        ?string $overrideTemplateSid,
        string $subject = '',
        string $message = '',
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

        $parameters = [];
        if ($placeholderCount === 1) {
            $parameters[] = ['type' => 'text', 'text' => $subject !== '' ? $subject : $message];
        } elseif ($placeholderCount >= 2) {
            $parameters[] = ['type' => 'text', 'text' => $subject];
            $parameters[] = ['type' => 'text', 'text' => $message];

            for ($i = 2; $i < $placeholderCount; $i++) {
                $parameters[] = ['type' => 'text', 'text' => ''];
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
            ],
        ];

        if (!empty($parameters)) {
            $payload['template']['components'] = [[
                'type' => 'body',
                'parameters' => $parameters,
            ]];
        }

        $response = $this->post("{$this->phoneNumberId}/messages", $payload);
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
            'raw' => $response,
        ];
    }

    public function getWhatsAppTemplates(bool $onlyApproved = true, int $pageSize = 50): array
    {
        $templates = [];
        $nextPath = "{$this->businessAccountId}/message_templates";
        $query = [
            'limit' => min(max($pageSize, 1), 100),
            'fields' => 'name,status,language,category,components',
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
        throw new \RuntimeException('Creating Meta templates from the app is not implemented yet. Create approved templates in Meta WhatsApp Manager first.');
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
        throw new \RuntimeException('Meta template submission is handled in WhatsApp Manager. The app only consumes approved templates for now.');
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

        preg_match_all('/{{(\d+)}}/', (string) ($body['text'] ?? ''), $matches);
        $variables = [];
        foreach ($matches[1] ?? [] as $index) {
            $variables[(string) $index] = 'Variable ' . $index;
        }

        $headerFormat = strtoupper((string) ($header['format'] ?? ''));
        $mediaUrls = [];
        if (!empty($header['example']['header_handle']) && is_array($header['example']['header_handle'])) {
            $mediaUrls = array_values(array_filter($header['example']['header_handle'], 'is_string'));
        }

        return [
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
        $response = Http::withToken($this->accessToken)->get($url, $query);
        return $this->decodeResponse($response->status(), $response->json() ?? [], $path);
    }

    protected function post(string $path, array $payload): array
    {
        $response = Http::withToken($this->accessToken)->post("{$this->baseUrl}/{$path}", $payload);
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
