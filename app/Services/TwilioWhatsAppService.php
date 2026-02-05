<?php

namespace App\Services;

use Illuminate\Support\Facades\Config;
use Twilio\Rest\Client;
use Illuminate\Support\Facades\Log;
use App\Models\SystemSetting;

class TwilioWhatsAppService
{
    private Client $client;
    private ?string $twilioSid       = null;
    private ?string $twilioToken     = null;
    private ?string $twilioFrom      = null;
    private ?string $msid            = null;
    private ?string $templateSid     = null;
    private ?string $statusCallback  = null;

    public function __construct()
    {
        $settings = SystemSetting::first();

        $this->twilioSid      = $settings?->twilio_sid      ?? (string) Config::get('services.twilio.sid');
        $this->twilioToken    = $settings?->twilio_auth_token ?? (string) Config::get('services.twilio.token');
        $this->twilioFrom     = $settings?->twilio_whatsapp_from ?? Config::get('services.twilio.from');
        $this->msid           = $settings?->twilio_msg_sid   ?? Config::get('services.twilio.msid');
        $this->templateSid    = $settings?->twilio_template_sid ?? Config::get('services.twilio.template_sid');
        $this->statusCallback = $settings?->twilio_status_callback ?? Config::get('services.twilio.status_callback');

        if (empty($this->twilioSid) || empty($this->twilioToken)) {
            throw new \RuntimeException('Twilio SID/token missing. Check services.twilio.sid and services.twilio.token.');
        }

        if (empty($this->msid) && empty($this->twilioFrom)) {
            throw new \RuntimeException('Provide either services.twilio.msid or services.twilio.from (WhatsApp number).');
        }

        $this->client = new Client($this->twilioSid, $this->twilioToken);
    }

    /**
     * List WhatsApp sender numbers (fallbacks to configured default if API lookup is not available).
     */
    public function listWhatsappSenders(): array
    {
        $senders = [];

        if (!empty($this->twilioFrom)) {
            $senders[] = [
                'number' => $this->twilioFrom,
                'label'  => 'Default',
                'default'=> true,
            ];
        }

        // Optionally add Messaging Service SID as a "sender" choice
        if (!empty($this->msid)) {
            $senders[] = [
                'number' => $this->msid,
                'label'  => 'Messaging Service SID',
                'default'=> empty($this->twilioFrom),
            ];
        }

        // Deduplicate by number
        $uniq = [];
        foreach ($senders as $s) {
            $uniq[$s['number']] = $s + ['default' => $s['default'] ?? false];
        }

        return array_values($uniq);
    }

    /**
     * Send WhatsApp Content Template.
     * - Subject maps to {{1}}
     * - Message maps to {{2}}
     * - If both empty, ContentVariables are omitted entirely.
     */
    public function sendTemplateFromSubjectMessage(
        string $toE164,
        ?string $overrideTemplateSid,
        string $subject = '',
        string $message = '',
        ?string $overrideFrom = null,
        ?string $overrideMsid = null
    ): array {
        $toE164 = self::normalizeZA($toE164);
        if (!$toE164) {
            throw new \InvalidArgumentException('Invalid recipient number provided.');
        }

        $templateSid = $overrideTemplateSid ?: $this->templateSid;
        if (empty($templateSid)) {
            throw new \RuntimeException('Missing ContentSid (template). Provide overrideTemplateSid or set services.twilio.template_sid.');
        }

        $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->twilioSid}/Messages.json";

        $data = [
            'To'         => "whatsapp:" . $toE164,
            'ContentSid' => $templateSid,
        ];

        // Always include variables 1 and 2 (empty strings are fine) to avoid 63028 parameter mismatches
        $data['ContentVariables'] = json_encode([
            '1' => $subject ?? '',
            '2' => $message ?? '',
        ], JSON_UNESCAPED_UNICODE);

        // Only set a status callback when a real URL is configured
        if (!empty($this->statusCallback) && !str_contains($this->statusCallback, 'your-app.example.com')) {
            $data['StatusCallback'] = $this->statusCallback;
        }

        $msid = $overrideMsid ?: $this->msid;
        $from = $overrideFrom ?: $this->twilioFrom;

        // If an explicit from is provided, prefer it over MessagingServiceSid to avoid 21703
        if (!empty($from)) {
            $data['From'] = 'whatsapp:' . $from;
            unset($data['MessagingServiceSid']);
        } elseif (!empty($msid)) {
            $data['MessagingServiceSid'] = $msid;
        }

        $auth = $this->twilioSid . ':' . $this->twilioToken;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_USERPWD, $auth);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $error    = curl_error($ch);
        $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            Log::error("Twilio WhatsApp cURL error", ['to' => $toE164, 'error' => $error]);
            throw new \RuntimeException("cURL error: " . $error);
        }

        $decoded = json_decode($response, true) ?? [];
        if ($status >= 400) {
            Log::error("Twilio WhatsApp send failed", [
                'to'       => $toE164,
                'status'   => $status,
                'response' => $decoded,
                'template' => $templateSid,
                'vars'     => $data['ContentVariables'] ?? 'none',
            ]);
            $msg = $decoded['message'] ?? $decoded['error_message'] ?? 'Unknown error';
            throw new \RuntimeException("Twilio API error [{$status}]: {$msg}");
        }

        Log::info("Twilio WhatsApp sent successfully", [
            'to'       => $toE164,
            'sid'      => $decoded['sid'] ?? null,
            'status'   => $decoded['status'] ?? null,
            'template' => $templateSid,
            'vars'     => $data['ContentVariables'] ?? 'none',
        ]);

        return $decoded;
    }

    /** Legacy shim (still supported) */
    public function sendStaussdalyTemplate(string $toE164, array $contentVariables = []): array
    {
        $subject = (string)($contentVariables['1'] ?? '');
        $message = (string)($contentVariables['2'] ?? '');
        return $this->sendTemplateFromSubjectMessage($toE164, $this->templateSid, $subject, $message);
    }


     /**
     * Low-level helper to perform GET requests against Twilio Content API.
     */
    protected function twilioGet(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPGET, true);
        curl_setopt($ch, CURLOPT_USERPWD, $this->twilioSid . ':' . $this->twilioToken);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $error    = curl_error($ch);
        $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            Log::error('Twilio Content API cURL error', ['url' => $url, 'error' => $error]);
            throw new \RuntimeException('Twilio Content API cURL error: ' . $error);
        }

        $decoded = json_decode($response, true) ?? [];

        if ($status >= 400) {
            Log::error('Twilio Content API error', [
                'url'      => $url,
                'status'   => $status,
                'response' => $decoded,
            ]);
            $msg = $decoded['message'] ?? $decoded['error_message'] ?? 'Unknown Content API error';
            throw new \RuntimeException("Twilio Content API error [{$status}]: {$msg}");
        }

        return $decoded;
    }

    /**
     * Get WhatsApp-capable templates to show in a dropdown.
     * Returns raw Twilio-ish structure; controller will map it for the Vue app.
     */
    public function getWhatsAppTemplates(bool $onlyApproved = true, int $pageSize = 50): array
    {
        $templates   = [];
        $nextPageUrl = "https://content.twilio.com/v1/Content?PageSize={$pageSize}";

        while ($nextPageUrl) {
            $payload     = $this->twilioGet($nextPageUrl);
            $contents    = $payload['contents'] ?? [];
            $meta        = $payload['meta'] ?? [];
            $nextPageUrl = $meta['next_page_url'] ?? null;

            foreach ($contents as $content) {
                $sid          = $content['sid'] ?? null;
                $friendlyName = $content['friendly_name'] ?? '';
                $language     = $content['language'] ?? '';
                $variables    = $content['variables'] ?? [];
                $types        = $content['types'] ?? [];

                if (!$sid) {
                    continue;
                }

                // Optional: get WhatsApp approval state
                $whatsAppApproval = $this->getTemplateApprovalStatus($sid)['whatsapp'] ?? null;
                if ($onlyApproved) {
                    $status = strtolower((string)($whatsAppApproval['status'] ?? ''));
                    if ($status !== 'approved') {
                        continue;
                    }
                }

                // Build a human preview text
                $preview = null;
                if (!empty($types['twilio/text']['body'])) {
                    $preview = $types['twilio/text']['body'];
                } elseif (!empty($types['twilio/quick-reply']['body'])) {
                    $preview = $types['twilio/quick-reply']['body'];
                } elseif (!empty($types['twilio/card']['title'])) {
                    $preview = $types['twilio/card']['title'];
                }

                // 🔹 NEW: media URLs for header image / card image, etc.
                $mediaUrls = [];
                if (!empty($types['twilio/media']['media']) && is_array($types['twilio/media']['media'])) {
                    $mediaUrls = $types['twilio/media']['media'];
                } elseif (!empty($types['twilio/card']['media']) && is_array($types['twilio/card']['media'])) {
                    $mediaUrls = $types['twilio/card']['media'];
                }

                $templates[] = [
                    'sid'           => $sid,
                    'friendly_name' => $friendlyName,
                    'language'      => $language,
                    'preview'       => $preview,
                    'variables'     => $variables,
                    'whatsapp'      => $whatsAppApproval,
                    'media'         => $mediaUrls,
                ];
            }
        }

        return $templates;
    }

    /**
     * Details for a single template (for full preview).
     */
    public function getTemplateDetails(string $contentSid): array
    {
        $url = "https://content.twilio.com/v1/Content/{$contentSid}";
        return $this->twilioGet($url);
    }

    /**
     * WhatsApp approval info for a template.
     */
    public function getTemplateApprovalStatus(string $contentSid): array
    {
        $url = "https://content.twilio.com/v1/Content/{$contentSid}/ApprovalRequests";
        return $this->twilioGet($url);
    }

    /** Normalize South African numbers */
    public static function normalizeZA(string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', $raw);
        if (!$digits) return null;

        if (str_starts_with($digits, '27') && strlen($digits) === 11) {
            return '+' . $digits;
        }
        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '+27' . substr($digits, 1);
        }
        if (str_starts_with($digits, '27') && strlen($digits) > 11) {
            return '+' . substr($digits, 0, 11);
        }
        if (str_starts_with($raw, '+')) {
            return $raw;
        }
        if (strlen($digits) === 9) {
            return '+27' . $digits;
        }
        return null;
    }

    /**
     * Send a plain WhatsApp text (non-template) message.
     * Twilio enforces the 24-hour session rule; this should only be used for live chat replies.
     */
    public function sendPlainWhatsapp(string $toE164, string $body, ?string $overrideFrom = null, ?string $overrideMsid = null): array
    {
        $toE164 = self::normalizeZA($toE164);
        if (!$toE164) {
            throw new \InvalidArgumentException('Invalid recipient number provided.');
        }

        if (empty($body)) {
            throw new \InvalidArgumentException('Message body cannot be empty.');
        }

        $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->twilioSid}/Messages.json";

        $data = [
            'To'   => "whatsapp:" . $toE164,
            'Body' => $body,
        ];

        $msid = $overrideMsid ?: $this->msid;
        $from = $overrideFrom ?: $this->twilioFrom;

        if (!empty($from)) {
            $data['From'] = 'whatsapp:' . $from;
        } elseif (!empty($msid)) {
            $data['MessagingServiceSid'] = $msid;
        } else {
            throw new \RuntimeException('No WhatsApp sender configured. Provide from number or Messaging Service SID.');
        }

        $auth = $this->twilioSid . ':' . $this->twilioToken;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_USERPWD, $auth);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $error    = curl_error($ch);
        $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            Log::error("Twilio WhatsApp chat send cURL error", ['to' => $toE164, 'error' => $error]);
            throw new \RuntimeException("cURL error: " . $error);
        }

        $decoded = json_decode($response, true) ?? [];
        if ($status >= 400) {
            Log::error("Twilio WhatsApp chat send failed", [
                'to'       => $toE164,
                'status'   => $status,
                'response' => $decoded,
            ]);
            $msg = $decoded['message'] ?? $decoded['error_message'] ?? 'Unknown error';
            throw new \RuntimeException("Twilio API error [{$status}]: {$msg}");
        }

        Log::info("Twilio WhatsApp chat sent successfully", [
            'to'     => $toE164,
            'sid'    => $decoded['sid'] ?? null,
            'status' => $decoded['status'] ?? null,
        ]);

        return $decoded;
    }

    /**
     * Create a new WhatsApp-capable Content template in Twilio.
     */
    public function createWhatsAppTemplate(string $friendlyName, string $body, string $language = 'en', string $category = 'utility', array $mediaUrls = []): array
    {
        $payload = [
            'friendly_name' => $friendlyName,
            'language'      => $language,
            'types'         => [
                'twilio/text' => ['body' => $body],
            ],
            'whatsapp'      => [
                'category' => strtolower($category),
            ],
        ];

        if (!empty($mediaUrls)) {
            $payload['types']['twilio/media'] = ['media' => array_values($mediaUrls)];
        }

        return $this->twilioPost('https://content.twilio.com/v1/Content', $payload);
    }

    /**
     * Update an existing template.
     */
    public function updateWhatsAppTemplate(string $sid, array $data): array
    {
        $types = $data['types'] ?? [
            'twilio/text' => ['body' => $data['body'] ?? ''],
        ];

        if (!empty($data['media'])) {
            $types['twilio/media'] = ['media' => array_values($data['media'])];
        }

        $payload = array_filter([
            'friendly_name' => $data['friendly_name'] ?? null,
            'language'      => $data['language'] ?? null,
            'types'         => $types,
            'whatsapp'      => isset($data['category']) ? ['category' => strtolower($data['category'])] : null,
        ], fn ($v) => !is_null($v));

        return $this->twilioPost("https://content.twilio.com/v1/Content/{$sid}", $payload, 'POST');
    }

    /**
     * Delete a template.
     */
    public function deleteWhatsAppTemplate(string $sid): bool
    {
        $this->twilioDelete("https://content.twilio.com/v1/Content/{$sid}");
        return true;
    }

    /**
     * Submit a template for WhatsApp approval.
     */
    public function submitTemplateForApproval(string $sid, string $category = 'utility'): array
    {
        $payload = [
            'channel'  => 'whatsapp',
            'whatsapp' => [
                'category' => strtolower($category),
            ],
        ];

        return $this->twilioPost("https://content.twilio.com/v1/Content/{$sid}/ApprovalRequests", $payload);
    }

    /**
     * Helper: POST/PUT with JSON body.
     */
    protected function twilioPost(string $url, array $payload, string $method = 'POST'): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $this->twilioSid . ':' . $this->twilioToken);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

        $response = curl_exec($ch);
        $error    = curl_error($ch);
        $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            Log::error('Twilio Content API cURL error', ['url' => $url, 'error' => $error]);
            throw new \RuntimeException('Twilio Content API cURL error: ' . $error);
        }

        $decoded = json_decode($response, true) ?? [];

        if ($status >= 400) {
            Log::error('Twilio Content API error', [
                'url'      => $url,
                'status'   => $status,
                'response' => $decoded,
            ]);
            $msg = $decoded['message'] ?? $decoded['error_message'] ?? 'Unknown Content API error';
            throw new \RuntimeException("Twilio Content API error [{$status}]: {$msg}");
        }

        return $decoded;
    }

    /**
     * Helper: DELETE request.
     */
    protected function twilioDelete(string $url): void
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_USERPWD, $this->twilioSid . ':' . $this->twilioToken);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $error    = curl_error($ch);
        $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            Log::error('Twilio Content API cURL error', ['url' => $url, 'error' => $error]);
            throw new \RuntimeException('Twilio Content API cURL error: ' . $error);
        }

        if ($status >= 400) {
            $decoded = json_decode($response, true) ?? [];
            Log::error('Twilio Content API error', [
                'url'      => $url,
                'status'   => $status,
                'response' => $decoded,
            ]);
            $msg = $decoded['message'] ?? $decoded['error_message'] ?? 'Unknown Content API error';
            throw new \RuntimeException("Twilio Content API error [{$status}]: {$msg}");
        }
    }
}
