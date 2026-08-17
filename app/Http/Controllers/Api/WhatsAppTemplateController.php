<?php

namespace App\Http\Controllers\Api;

use App\Contracts\WhatsAppServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\WhatsappTemplateCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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
                    'raw_whatsapp'  => $whatsapp,
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
            'body'          => ['required', 'string'],
            'language'      => ['required', 'string', 'max:10'],
            'category'      => ['required', 'string', 'max:50'],
            'media_urls'    => ['array'],
            'media_urls.*'  => ['string'],
        ]);

        $created = $this->whatsApp->createWhatsAppTemplate(
            $data['friendly_name'],
            $data['body'],
            $data['language'],
            $data['category'],
            $data['media_urls'] ?? []
        );

        return response()->json($created, 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'friendly_name' => ['sometimes', 'string', 'max:255'],
            'body'          => ['sometimes', 'string'],
            'language'      => ['sometimes', 'string', 'max:10'],
            'category'      => ['sometimes', 'string', 'max:50'],
            'media_urls'    => ['array'],
            'media_urls.*'  => ['string'],
        ]);

        $payload = [
            'friendly_name' => $data['friendly_name'] ?? null,
            'language'      => $data['language'] ?? null,
            'body'          => $data['body'] ?? null,
            'category'      => $data['category'] ?? null,
            'media'         => $data['media_urls'] ?? null,
        ];

        $updated = $this->whatsApp->updateWhatsAppTemplate($id, $payload);

        return response()->json($updated);
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

        $result = $this->whatsApp->migrateTemplates($data['destination_waba_id'], $data['template_ids']);

        return response()->json([
            'message' => 'Templates migrated successfully.',
            'result'  => $result,
        ]);
    }

    private function authorizeAdmin(): void
    {
        $user = Auth::user();
        if (!$user || (!$user->canManageSystemSettings() && !$user->canAccessWabaTemplatesSettings())) {
            abort(403, 'Unauthorized access to WhatsApp templates.');
        }
    }
}
