<?php

namespace App\Http\Controllers\Api;

use App\Contracts\WhatsAppServiceInterface;
use App\Http\Controllers\Controller;
use App\Jobs\SyncWhatsappTemplatesJob;
use App\Models\WhatsappTemplateCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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
        $user = Auth::user();
        $onlyApproved = filter_var($request->query('approved', '1'), FILTER_VALIDATE_BOOLEAN);

        $query = WhatsappTemplateCache::with('banks')->orderBy('friendly_name');

        if ($onlyApproved) {
            $query->where('status', 'approved');
        }

        // Access control:
        // Apart from Super Admin, all other role users can ONLY see templates of the banks they belong to
        if ($user && !$user->canAccessAllBanks()) {
            $accessibleBankIds = $user->accessibleBankIds() ?: $user->resolvedBankIds();
            if (empty($accessibleBankIds)) {
                return response()->json([]);
            }
            $query->whereHas('banks', fn ($q) => $q->whereIn('banks.id', $accessibleBankIds));
        }

        // Optional filtering by bank_id
        if ($request->filled('bank_id')) {
            $bankId = $request->query('bank_id');
            if ($bankId === 'unassigned') {
                if ($user && $user->canAccessAllBanks()) {
                    $query->whereDoesntHave('banks');
                } else {
                    return response()->json([]);
                }
            } else {
                $targetBankId = (int) $bankId;
                if ($user && !$user->canAccessAllBanks() && !$user->canAccessBankId($targetBankId)) {
                    return response()->json([]);
                }
                $query->whereHas('banks', fn ($q) => $q->where('banks.id', $targetBankId));
            }
        }

        $data = $query->get()->map(fn ($t) => $t->toApiArray())->values();

        // Never block the listing request on Meta. Queue a first sync when the cache is empty.
        if ($data->isEmpty() && !SyncWhatsappTemplatesJob::isActive() && (!$user || $user->canAccessAllBanks())) {
            SyncWhatsappTemplatesJob::markQueued(Auth::id());
            SyncWhatsappTemplatesJob::dispatch(Auth::id());
        }

        return response()->json($data);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorizeAdmin();
        $user = Auth::user();

        $fileName = 'waba_templates_' . now()->format('Ymd_His') . '.xls';

        return response()->streamDownload(function () use ($user) {
            $query = WhatsappTemplateCache::with('banks')
                ->orderBy('friendly_name');

            if ($user && !$user->canAccessAllBanks()) {
                $accessibleBankIds = $user->accessibleBankIds() ?: $user->resolvedBankIds();
                if (empty($accessibleBankIds)) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->whereHas('banks', fn ($q) => $q->whereIn('banks.id', $accessibleBankIds));
                }
            }

            $templates = $query->get();

            echo "\xEF\xBB\xBF";
            echo '<html><head><meta charset="UTF-8"></head><body>';
            echo '<table border="1">';
            echo '<thead><tr>';

            foreach ([
                'Template Name',
                'Bank(s)',
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
                $bankNames = $template->banks->pluck('name')->implode(', ') ?: 'Unassigned';
                echo '<tr>';
                foreach ([
                    $template->friendly_name,
                    $bankNames,
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

        if (!SyncWhatsappTemplatesJob::isActive()) {
            SyncWhatsappTemplatesJob::markQueued(Auth::id());
            SyncWhatsappTemplatesJob::dispatch(Auth::id());
        }

        return response()->json([
            'message' => 'Template sync has started in the background.',
            'status' => SyncWhatsappTemplatesJob::status(),
        ], 202);
    }

    public function syncStatus(): JsonResponse
    {
        $this->authorizeAdmin();

        return response()->json(SyncWhatsappTemplatesJob::status());
    }

    /**
     * Core sync logic: fetch from Meta and upsert into whatsapp_templates_cache.
     */
    private function syncFromMeta(bool $onlyApproved = false): array
    {
        $templates = $this->whatsApp->getWhatsAppTemplates($onlyApproved, 100);
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
        $user = Auth::user();

        // Try DB first
        $cached = WhatsappTemplateCache::with('banks')->where('sid', $id)->first();
        if ($cached) {
            if ($user && !$user->canAccessAllBanks()) {
                $accessibleBankIds = $user->accessibleBankIds() ?: $user->resolvedBankIds();
                $templateBankIds = $cached->banks->pluck('id')->all();
                if (empty($templateBankIds) || empty(array_intersect($templateBankIds, $accessibleBankIds))) {
                    abort(403, 'You do not have access to templates for this bank.');
                }
            }

            return response()->json([
                'template'  => $cached->toApiArray(),
                'approvals' => [],
            ]);
        }

        // Fallback to Meta API for non-cached
        $this->authorizeAdmin();
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
        $user = Auth::user();

        $data = $request->validate([
            'friendly_name' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'language' => ['required', 'string', 'max:10'],
            'category' => ['required', 'string', 'max:50'],
            'bank_ids' => ['sometimes', 'array', 'min:1'],
            'bank_ids.*' => ['integer', 'exists:banks,id'],
            'media_urls' => ['array'],
            'media_urls.*' => ['string'],
            'body_examples' => ['sometimes', 'array'],
            'body_examples.*' => ['required', 'string', 'max:255'],
            'buttons' => ['sometimes', 'array', 'max:10'],
            'buttons.*.type' => ['required', 'string'],
            'buttons.*.text' => ['required', 'string', 'max:25'],
            'buttons.*.url' => ['nullable', 'string', 'max:2000'],
            'buttons.*.phone_number' => ['nullable', 'string', 'max:50'],
        ]);

        $assignedBankIds = [];
        if (!empty($data['bank_ids'])) {
            $assignedBankIds = array_map('intval', $data['bank_ids']);
            if ($user && !$user->canAccessAllBanks()) {
                $accessibleBankIds = $user->accessibleBankIds() ?: $user->resolvedBankIds();
                $invalid = array_diff($assignedBankIds, $accessibleBankIds);
                if (!empty($invalid)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'bank_ids' => 'You can only assign templates to banks you have access to.',
                    ]);
                }
            }
        } else {
            if ($user && !$user->canAccessAllBanks()) {
                $assignedBankIds = $user->accessibleBankIds() ?: $user->resolvedBankIds();
            } elseif ($user && $user->bank_id) {
                $assignedBankIds = [(int) $user->bank_id];
            } else {
                $defaultBank = \App\Models\Bank::first();
                $assignedBankIds = $defaultBank ? [$defaultBank->id] : [];
            }
        }

        $this->validateBodyExamples($data['body'], $data['body_examples'] ?? []);

        $buttons = [];
        if (!empty($data['buttons']) && is_array($data['buttons'])) {
            foreach ($data['buttons'] as $btn) {
                $text = trim((string) ($btn['text'] ?? ''));
                if ($text === '') {
                    continue;
                }
                $type = strtoupper((string) ($btn['type'] ?? 'QUICK_REPLY'));
                if (!in_array($type, ['QUICK_REPLY', 'URL', 'PHONE_NUMBER'], true)) {
                    $type = 'QUICK_REPLY';
                }
                $btnItem = [
                    'type' => $type,
                    'text' => mb_substr($text, 0, 25),
                ];
                if ($type === 'URL') {
                    $url = trim((string) ($btn['url'] ?? ''));
                    if ($url === '' || !preg_match('#^https?://#i', $url)) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'buttons' => "The URL button '{$text}' must contain a valid web address starting with https:// or http://.",
                        ]);
                    }
                    $btnItem['url'] = $url;
                } elseif ($type === 'PHONE_NUMBER') {
                    $phone = trim((string) ($btn['phone_number'] ?? ''));
                    if ($phone === '') {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'buttons' => "The phone button '{$text}' must have a phone number with country code.",
                        ]);
                    }
                    $btnItem['phone_number'] = $phone;
                }
                $buttons[] = $btnItem;
            }
        }

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
                $data['body_examples'] ?? [],
                $buttons
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
                'buttons' => $created['buttons'] ?? $buttons,
                'raw_whatsapp' => array_merge($whatsapp, ['raw' => $created['raw'] ?? []]),
                'synced_at' => now(),
            ]
        );

        if (!empty($assignedBankIds)) {
            $record->banks()->sync($assignedBankIds);
        }

        return response()->json($record->fresh('banks')->toApiArray(), 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdmin();
        $user = Auth::user();

        $record = WhatsappTemplateCache::with('banks')->where('sid', $id)->orWhere('meta_id', $id)->firstOrFail();

        // Enforce that non-super-admins cannot update templates of other banks
        if ($user && !$user->canAccessAllBanks()) {
            $accessibleBankIds = $user->accessibleBankIds() ?: $user->resolvedBankIds();
            $templateBankIds = $record->banks->pluck('id')->all();
            if (!empty($templateBankIds) && empty(array_intersect($templateBankIds, $accessibleBankIds))) {
                abort(403, 'You do not have access to edit templates for this bank.');
            }
        }

        $data = $request->validate([
            'friendly_name' => ['sometimes', 'string', 'max:255'],
            'body' => ['sometimes', 'string'],
            'language' => ['sometimes', 'string', 'max:10'],
            'category' => ['sometimes', 'string', 'max:50'],
            'bank_ids' => ['sometimes', 'array', 'min:1'],
            'bank_ids.*' => ['integer', 'exists:banks,id'],
            'media_urls' => ['array'],
            'media_urls.*' => ['string'],
            'body_examples' => ['sometimes', 'array'],
            'body_examples.*' => ['required', 'string', 'max:255'],
            'buttons' => ['sometimes', 'array', 'max:10'],
            'buttons.*.type' => ['required', 'string'],
            'buttons.*.text' => ['required', 'string', 'max:25'],
            'buttons.*.url' => ['nullable', 'string', 'max:2000'],
            'buttons.*.phone_number' => ['nullable', 'string', 'max:50'],
        ]);

        if (isset($data['bank_ids'])) {
            $newBankIds = array_map('intval', $data['bank_ids']);
            if ($user && !$user->canAccessAllBanks()) {
                $accessibleBankIds = $user->accessibleBankIds() ?: $user->resolvedBankIds();
                $invalid = array_diff($newBankIds, $accessibleBankIds);
                if (!empty($invalid)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'bank_ids' => 'You can only assign templates to banks you have access to.',
                    ]);
                }
            }
            $record->banks()->sync($newBankIds);
        }

        $body = $data['body'] ?? $record->body_preview ?? '';
        $this->validateBodyExamples($body, $data['body_examples'] ?? []);

        $buttons = [];
        if (isset($data['buttons']) && is_array($data['buttons'])) {
            foreach ($data['buttons'] as $btn) {
                $text = trim((string) ($btn['text'] ?? ''));
                if ($text === '') {
                    continue;
                }
                $type = strtoupper((string) ($btn['type'] ?? 'QUICK_REPLY'));
                if (!in_array($type, ['QUICK_REPLY', 'URL', 'PHONE_NUMBER'], true)) {
                    $type = 'QUICK_REPLY';
                }
                $btnItem = [
                    'type' => $type,
                    'text' => mb_substr($text, 0, 25),
                ];
                if ($type === 'URL') {
                    $url = trim((string) ($btn['url'] ?? ''));
                    if ($url === '' || !preg_match('#^https?://#i', $url)) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'buttons' => "The URL button '{$text}' must contain a valid web address starting with https:// or http://.",
                        ]);
                    }
                    $btnItem['url'] = $url;
                } elseif ($type === 'PHONE_NUMBER') {
                    $phone = trim((string) ($btn['phone_number'] ?? ''));
                    if ($phone === '') {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'buttons' => "The phone button '{$text}' must have a phone number with country code.",
                        ]);
                    }
                    $btnItem['phone_number'] = $phone;
                }
                $buttons[] = $btnItem;
            }
        } else {
            $buttons = $record->buttons ?? [];
        }

        $contentChanged = (isset($data['body']) && $data['body'] !== $record->body_preview)
            || (isset($data['category']) && strtolower((string) $data['category']) !== strtolower((string) ($record->category ?? '')))
            || (isset($data['language']) && $data['language'] !== $record->language)
            || (isset($data['buttons']) && $buttons !== ($record->buttons ?? []));

        $payload = [
            'friendly_name' => $data['friendly_name'] ?? $record->friendly_name,
            'language' => $data['language'] ?? $record->language,
            'body' => $body,
            'category' => $data['category'] ?? $record->category,
            'body_examples' => $data['body_examples'] ?? [],
            'buttons' => $buttons,
        ];

        if ($contentChanged) {
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
                'buttons' => $buttons,
                'synced_at' => now(),
            ])->save();
        } else {
            if (isset($data['friendly_name'])) {
                $record->friendly_name = $data['friendly_name'];
            }
            $record->save();
        }

        return response()->json($record->fresh('banks')->toApiArray());
    }

    public function destroy(string $id): JsonResponse
    {
        $this->authorizeAdmin();
        $user = Auth::user();

        $record = WhatsappTemplateCache::with('banks')->where('sid', $id)->first();
        if ($record && $user && !$user->canAccessAllBanks()) {
            $accessibleBankIds = $user->accessibleBankIds() ?: $user->resolvedBankIds();
            $templateBankIds = $record->banks->pluck('id')->all();
            if (!empty($templateBankIds) && empty(array_intersect($templateBankIds, $accessibleBankIds))) {
                abort(403, 'You do not have access to delete templates for this bank.');
            }
        }

        $this->whatsApp->deleteWhatsAppTemplate($id);

        if ($record) {
            $record->banks()->detach();
            $record->delete();
        } else {
            WhatsappTemplateCache::where('sid', $id)->delete();
        }

        return response()->json([], 204);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $this->authorizeAdmin();
        $user = Auth::user();
        $accessibleBankIds = ($user && !$user->canAccessAllBanks())
            ? ($user->accessibleBankIds() ?: $user->resolvedBankIds())
            : null;

        $data = $request->validate([
            'template_ids'   => ['required', 'array'],
            'template_ids.*' => ['string'],
        ]);

        $deletedCount = 0;
        foreach ($data['template_ids'] as $id) {
            try {
                $record = WhatsappTemplateCache::with('banks')->where('sid', $id)->first();
                if ($record && $accessibleBankIds !== null) {
                    $templateBankIds = $record->banks->pluck('id')->all();
                    if (!empty($templateBankIds) && empty(array_intersect($templateBankIds, $accessibleBankIds))) {
                        continue;
                    }
                }

                $this->whatsApp->deleteWhatsAppTemplate($id);
                if ($record) {
                    $record->banks()->detach();
                    $record->delete();
                } else {
                    WhatsappTemplateCache::where('sid', $id)->delete();
                }
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

    /**
     * Check live status of a single template directly with Meta API,
     * update local DB cache, and return fresh template data.
     */
    public function checkStatus(string $id): JsonResponse
    {
        $this->authorizeAdmin();

        $cached = WhatsappTemplateCache::where('sid', $id)
            ->orWhere('friendly_name', $id)
            ->orWhere('meta_id', $id)
            ->first();

        $lookupKey = $cached?->meta_id ?: ($cached?->sid ?: $id);

        try {
            $live = $this->whatsApp->fetchLiveTemplateFromMeta($lookupKey);

            if (!$live && $cached && $cached->sid !== $lookupKey) {
                $live = $this->whatsApp->fetchLiveTemplateFromMeta($cached->sid);
            }

            if (!$live) {
                return response()->json([
                    'message' => "Template '{$id}' was not found on Meta.",
                    'template' => $cached?->toApiArray(),
                ], 404);
            }

            $whatsapp = $live['whatsapp'] ?? [];
            $now = now();

            $record = WhatsappTemplateCache::updateOrCreate(
                ['sid' => $live['sid']],
                [
                    'meta_id'       => $live['meta_id'] ?? null,
                    'friendly_name' => $live['friendly_name'] ?? $live['sid'],
                    'language'      => $live['language'] ?? null,
                    'category'      => $whatsapp['category'] ?? null,
                    'status'        => $whatsapp['status'] ?? null,
                    'body_preview'  => $live['preview'] ?? null,
                    'header_format' => $live['header_format'] ?? null,
                    'header_text'   => $live['header_text'] ?? null,
                    'footer_text'   => $live['footer_text'] ?? null,
                    'variables'     => $live['variables'] ?? [],
                    'media_urls'    => $live['media'] ?? [],
                    'buttons'       => $live['buttons'] ?? [],
                    'raw_whatsapp'  => array_merge($whatsapp, ['components' => $live['components'] ?? []]),
                    'synced_at'     => $now,
                ]
            );

            return response()->json([
                'message' => 'Template status updated from Meta.',
                'template' => $record->toApiArray(),
                'live_status' => $whatsapp['status'] ?? null,
                'rejected_reason' => $whatsapp['rejected_reason'] ?? null,
                'quality_score' => $whatsapp['quality_score'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to check live WhatsApp template status', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to check status with Meta: ' . $e->getMessage(),
                'template' => $cached?->toApiArray(),
            ], 500);
        }
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
