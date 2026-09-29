<?php

namespace App\Http\Controllers\Api;

use App\Contracts\WhatsAppServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\WhatsappTemplateCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WhatsAppTemplateController extends Controller
{
    public function __construct(private WhatsAppServiceInterface $whatsApp)
    {
    }

    /**
     * Return templates from local DB cache (fast – no Meta API call).
     */
    public function index(Request $request): JsonResponse
    {
        $onlyApproved = filter_var($request->query('approved', '1'), FILTER_VALIDATE_BOOLEAN);

        $query = WhatsappTemplateCache::orderBy('friendly_name');

        if ($onlyApproved) {
            $query->where('status', 'approved');
        }

        $data = $query->get()->map(fn ($t) => $t->toApiArray())->values();

        // If the cache is empty, do a one-time auto-sync so the first visit works
        if ($data->isEmpty()) {
            try {
                $synced = $this->syncFromMeta(false);
                $data = collect($synced)->values();
            } catch (\Throwable $e) {
                Log::warning('WhatsApp template auto-sync failed.', ['error' => $e->getMessage()]);
            }
        }

        return response()->json($data);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorizeAdmin();

        $fileName = 'waba_templates_' . now()->format('Ymd_His') . '.xls';

        return response()->streamDownload(function () {
            $templates = WhatsappTemplateCache::query()
                ->orderBy('friendly_name')
                ->get();

            echo "\xEF\xBB\xBF";
            echo '<html><head><meta charset="UTF-8"></head><body>';
            echo '<table border="1">';
            echo '<thead><tr>';

            foreach ([
                'Template Name',
                'SID',
                'Meta ID',
                'Language',
                'Category',
                'Status',
                'Header Format',
                'Header Text',
                'Body Preview',
                'Footer Text',
                'Variables',
                'Buttons',
                'Media URLs',
                'Synced At',
            ] as $heading) {
                echo '<th>' . $this->excelCell($heading) . '</th>';
            }

            echo '</tr></thead><tbody>';

            foreach ($templates as $template) {
                echo '<tr>';
                foreach ([
                    $template->friendly_name,
                    $template->sid,
                    $template->meta_id,
                    $template->language,
                    $template->category,
                    $template->status,
                    $template->header_format,
                    $template->header_text,
                    $template->body_preview,
                    $template->footer_text,
                    $this->exportJsonValue($template->variables),
                    $this->exportJsonValue($template->buttons),
                    $this->exportJsonValue($template->media_urls),
                    optional($template->synced_at)->toDateTimeString(),
                ] as $value) {
                    echo '<td>' . $this->excelCell($value) . '</td>';
                }
                echo '</tr>';
            }

            echo '</tbody></table></body></html>';
        }, $fileName, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    /**
     * Pull latest templates from Meta API and upsert into local DB cache.
     * Called only by the "Refresh" button on the Settings WABA Templates page.
     */
    public function sync(): JsonResponse
    {
        $this->authorizeAdmin();

        try {
            $results = $this->syncFromMeta(false);
            return response()->json([
                'message' => 'Templates synced successfully.',
                'count'   => count($results),
                'synced_at' => now()->toDateTimeString(),
            ]);
        } catch (\Throwable $e) {
            Log::error('WhatsApp template sync failed.', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Sync failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Core sync logic: fetch from Meta and upsert into whatsapp_templates_cache.
     */
    private function syncFromMeta(bool $onlyApproved = false): array
    {
        $templates = $this->whatsApp->getWhatsAppTemplates($onlyApproved);
        $now       = now();
        $results   = [];

        foreach ($templates as $t) {
            $whatsapp = $t['whatsapp'] ?? [];
            $record   = WhatsappTemplateCache::updateOrCreate(
                ['sid' => $t['sid']],
                [
                    'meta_id'       => $t['meta_id'] ?? null,
                    'friendly_name' => $t['friendly_name'] ?? $t['sid'],
                    'language'      => $t['language'] ?? null,
                    'category'      => $whatsapp['category'] ?? null,
                    'status'        => $whatsapp['status'] ?? null,
                    'body_preview'  => $t['preview'] ?? null,
                    'header_format' => $t['header_format'] ?? null,
                    'header_text'   => $t['header_text'] ?? null,
                    'footer_text'   => $t['footer_text'] ?? null,
                    'variables'     => $t['variables'] ?? [],
                    'media_urls'    => $t['media'] ?? [],
                    'buttons'       => $t['buttons'] ?? [],
                    'raw_whatsapp'  => array_merge($whatsapp, ['components' => $t['components'] ?? []]),
                    'synced_at'     => $now,
                ]
            );

            $results[] = $record->toApiArray();
        }

        return $results;
    }

    public function show(string $id): JsonResponse
    {
        // Try DB first
        $cached = WhatsappTemplateCache::where('sid', $id)->first();
        if ($cached) {
            return response()->json([
                'template'  => $cached->toApiArray(),
                'approvals' => [],
            ]);
        }

        // Fallback to Meta API for non-cached
        $details   = $this->whatsApp->getTemplateDetails($id);
        $approvals = $this->whatsApp->getTemplateApprovalStatus($id);

        return response()->json([
            'template'  => $details,
            'approvals' => $approvals,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'friendly_name' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'language' => ['required', 'string', 'max:10'],
            'category' => ['required', 'string', 'max:50'],
            'media_urls' => ['array'],
            'media_urls.*' => ['string'],
            'body_examples' => ['sometimes', 'array'],
            'body_examples.*' => ['required', 'string', 'max:255'],
        ]);

        $this->validateBodyExamples($data['body'], $data['body_examples'] ?? []);

        $normalizedName = Str::of($data['friendly_name'])
            ->lower()
            ->replaceMatches('/[^a-z0-9_]+/', '_')
            ->trim('_')
            ->value();

        if ($normalizedName === '') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'friendly_name' => 'Template name must contain valid lowercase alphanumeric characters or underscores.',
            ]);
        }

        $existing = WhatsappTemplateCache::where('sid', $normalizedName)
            ->orWhere('friendly_name', $normalizedName)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => "A template named '{$normalizedName}' already exists in your CRM (status: {$existing->status}). Please choose a unique name or edit the existing template.",
            ], 422);
        }

        try {
            $created = $this->whatsApp->createWhatsAppTemplate(
                $normalizedName,
                $data['body'],
                $data['language'],
                $data['category'],
                $data['media_urls'] ?? [],
                $data['body_examples'] ?? []
            );
        } catch (\Throwable $e) {
            if ($this->isAlreadyExistsError($e->getMessage())) {
                try {
                    $details = $this->whatsApp->getTemplateDetails($normalizedName);
                    if ($details) {
                        $this->syncFromMeta(false);

                        return response()->json([
                            'message' => "A template named '{$normalizedName}' already exists on Meta (status: " . ($details['status'] ?? 'unknown') . "). It has now been synced into your CRM templates list.",
                            'template' => $details,
                        ], 409);
                    }
                } catch (\Throwable $syncError) {
                    Log::warning('Failed to sync existing template after conflict', ['error' => $syncError->getMessage()]);
                }
            }

            throw $e;
        }

        $whatsapp = $created['whatsapp'] ?? [];
        $record = WhatsappTemplateCache::updateOrCreate(
            ['sid' => $created['sid']],
            [
                'meta_id' => $created['meta_id'] ?? null,
                'friendly_name' => $created['friendly_name'] ?? $created['sid'],
                'language' => $created['language'] ?? $data['language'],
                'category' => $whatsapp['category'] ?? strtolower($data['category']),
                'status' => $whatsapp['status'] ?? 'PENDING',
                'body_preview' => $created['preview'] ?? $data['body'],
                'variables' => $created['variables'] ?? [],
                'media_urls' => $created['media'] ?? [],
                'buttons' => $created['buttons'] ?? [],
                'raw_whatsapp' => array_merge($whatsapp, ['raw' => $created['raw'] ?? []]),
                'synced_at' => now(),
            ]
        );

        return response()->json($record->toApiArray(), 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'friendly_name' => ['sometimes', 'string', 'max:255'],
            'body' => ['sometimes', 'string'],
            'language' => ['sometimes', 'string', 'max:10'],
            'category' => ['sometimes', 'string', 'max:50'],
            'media_urls' => ['array'],
            'media_urls.*' => ['string'],
            'body_examples' => ['sometimes', 'array'],
            'body_examples.*' => ['required', 'string', 'max:255'],
        ]);

        $record = WhatsappTemplateCache::where('sid', $id)->orWhere('meta_id', $id)->firstOrFail();
        $body = $data['body'] ?? $record->body_preview ?? '';
        $this->validateBodyExamples($body, $data['body_examples'] ?? []);

        $payload = [
            'friendly_name' => $data['friendly_name'] ?? $record->friendly_name,
            'language' => $data['language'] ?? $record->language,
            'body' => $body,
            'category' => $data['category'] ?? $record->category,
            'body_examples' => $data['body_examples'] ?? [],
        ];

        $targetId = (string) ($record->meta_id ?: '');
        if ($targetId === '') {
            try {
                $details = $this->whatsApp->getTemplateDetails($record->sid);
                $targetId = (string) ($details['meta_id'] ?? '');
                if ($targetId !== '') {
                    $record->meta_id = $targetId;
                    $record->save();
                }
            } catch (\Throwable $lookupError) {
                Log::warning('Failed to resolve Meta ID for template update', ['error' => $lookupError->getMessage()]);
            }
        }

        $updated = $this->whatsApp->updateWhatsAppTemplate(
            $targetId !== '' ? $targetId : $id,
            $payload
        );

        $record->forceFill([
            'friendly_name' => $payload['friendly_name'],
            'language' => $payload['language'],
            'body_preview' => $payload['body'],
            'category' => strtolower((string) $payload['category']),
            'status' => $updated['status'] ?? 'PENDING',
            'synced_at' => now(),
        ])->save();

        return response()->json($record->fresh()->toApiArray());
    }

    public function destroy(string $id): JsonResponse
    {
        $this->authorizeAdmin();

        $this->whatsApp->deleteWhatsAppTemplate($id);

        // Also remove from local cache
        WhatsappTemplateCache::where('sid', $id)->delete();

        return response()->json([], 204);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'template_ids'   => ['required', 'array'],
            'template_ids.*' => ['string'],
        ]);

        $deletedCount = 0;
        foreach ($data['template_ids'] as $id) {
            try {
                $this->whatsApp->deleteWhatsAppTemplate($id);
                WhatsappTemplateCache::where('sid', $id)->delete();
                $deletedCount++;
            } catch (\Exception $e) {
                // continue deleting others
            }
        }

        return response()->json([
            'message'       => 'Templates deleted successfully.',
            'deleted_count' => $deletedCount,
        ]);
    }

    public function submitForApproval(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'category' => ['required', 'string', 'max:50'],
        ]);

        $result = $this->whatsApp->submitTemplateForApproval($id, $data['category']);

        return response()->json($result);
    }

    public function migrate(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'destination_waba_id' => ['required', 'string'],
            'template_ids'        => ['required', 'array'],
            'template_ids.*'      => ['string'],
        ]);

        // Meta migrate API requires numeric meta_id values, not template name strings.
        // Look them up from the local DB cache.
        $resolvedIds = WhatsappTemplateCache::whereIn('sid', $data['template_ids'])
            ->whereNotNull('meta_id')
            ->pluck('meta_id', 'sid');

        $metaIds = array_values(array_map(
            fn ($sid) => $resolvedIds[$sid] ?? $sid,
            $data['template_ids']
        ));

        $result = $this->whatsApp->migrateTemplates($data['destination_waba_id'], $metaIds);

        return response()->json([
            'message' => 'Templates migrated successfully.',
            'result'  => $result,
        ]);
    }

    private function validateBodyExamples(string $body, array $examples): void
    {
        preg_match_all('/{{(\d+)}}/', $body, $matches);
        $indexes = array_values(array_unique(array_map('intval', $matches[1] ?? [])));
        sort($indexes);

        if ($indexes === []) {
            return;
        }

        $expected = range(1, max($indexes));
        if ($indexes !== $expected || count($examples) !== count($expected)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'body_examples' => 'Provide one example value for every sequential body variable, starting at {{1}}.',
            ]);
        }
    }

    private function authorizeAdmin(): void
    {
        $user = Auth::user();
        if (!$user || (!$user->canManageSystemSettings() && !$user->canAccessWabaTemplatesSettings())) {
            abort(403, 'Unauthorized access to WhatsApp templates.');
        }
    }

    private function exportJsonValue(mixed $value): string
    {
        if (empty($value)) {
            return '';
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
    }

    private function isAlreadyExistsError(string $message): bool
    {
        return str_contains($message, 'already exists')
            || str_contains($message, '2388024')
            || str_contains($message, 'Content in this language already exists')
            || str_contains($message, 'There is already');
    }

    private function excelCell(mixed $value): string
    {
        $text = (string) ($value ?? '');
        if (preg_match('/^[=+\-@]/', ltrim($text)) === 1) {
            $text = "'" . $text;
        }

        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
