<?php

namespace App\Http\Controllers\Api;

use App\Concerns\EnforcesMetaPermissionHealth;
use App\Concerns\GuardsSensitiveExports;
use App\Concerns\HasAuditLogging;
use App\Contracts\WhatsAppServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignClient;
use App\Models\CampaignWhatsappMessage;
use App\Models\CampaignEmailRecipient;
use App\Models\CampaignSmsRecipient;
use App\Models\CampaignWhatsappRecipient;
use App\Models\ExportRequest;
use App\Models\WhatsAppFlow;
use App\Models\Client;
use App\Services\MetaWhatsAppService;
use App\Services\WhatsAppBatchService;
use App\Services\WhatsAppDailyLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CampaignController extends Controller
{
    use HasAuditLogging, GuardsSensitiveExports, EnforcesMetaPermissionHealth;

    protected WhatsAppServiceInterface $whatsApp;
    protected WhatsAppDailyLimitService $dailyLimitService;
    protected WhatsAppBatchService $batchService;

    public function __construct(
        WhatsAppServiceInterface $whatsApp,
        WhatsAppDailyLimitService $dailyLimitService,
        WhatsAppBatchService $batchService
    )
    {
        $this->whatsApp = $whatsApp;
        $this->dailyLimitService = $dailyLimitService;
        $this->batchService = $batchService;
    }
    /**
     * List campaigns (department + role scoped).
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->canViewCampaigns()) {
            abort(403, 'You are not allowed to access campaigns.');
        }
        $userDeptIds = $user?->resolvedDepartmentIds() ?? [];

        $query = Campaign::query()
            ->with(['departments', 'bank'])
            ->withCount('clients as total_recipients')
            ->orderByDesc('created_at');

        if ($user && !$user->canAccessAllBanks() && !empty($user->resolvedBankIds())) {
            $query->whereIn('bank_id', $user->resolvedBankIds());
        }

        // Department scoping (same logic as before)
        if ($user && !$user->canViewAllImportedClients()) {
            $query->where(function ($q) use ($userDeptIds) {
                $q->whereDoesntHave('departments');

                if (!empty($userDeptIds)) {
                    $q->orWhereHas('departments', function ($qq) use ($userDeptIds) {
                        $qq->whereIn('departments.id', $userDeptIds);
                    });
                }
            });
        }

        if ($status = $request->get('status')) {
            if ($status !== 'All') {
                $query->where('status', $status);
            }
        }

        $perPage = (int) $request->get('per_page', 20);

        return $query->paginate($perPage);
    }



    /**
     * Show single campaign with department check.
     */
    public function show(Campaign $campaign)
    {
        $this->authorizeView($campaign);

        return $campaign->load(['departments', 'bank']);
    }

      /**
     * List clients that can be added to this campaign.
     * - Scoped to departments linked to the campaign
     * - Excludes already attached clients
     */
    public function availableClients(Campaign $campaign)
    {
        $this->authorizeView($campaign);

        // Ensure we have departments loaded
        $campaign->loadMissing('departments');

        // Get IDs of departments this campaign belongs to
        $deptIds = $campaign->departments->pluck('id')->all();

        // Base query: only clients in those departments (many-to-many)
        $query = Client::query()
            ->with('departments:id,name')
            ->select(
                'clients.id', 'clients.name', 'clients.email', 'clients.phone', 
                'clients.cell_phone', 'clients.home_phone', 'clients.work_phone', 
                'clients.opt_in', 'clients.whatsapp_opted_in_at', 'clients.whatsapp_opted_out_at', 
                'clients.import_batch_number'
            );

        if ($user = Auth::user()) {
            if ($user->isPortfolioScoped() && !$user->canViewAllImportedClients()) {
                $query->where('clients.assigned_to_id', $user->id);
            }
        }

        if (!empty($deptIds) && ($user && !$user->canViewAllImportedClients())) {
            $query->whereHas('departments', function ($q) use ($deptIds) {
                $q->whereIn('departments.id', $deptIds);
            });
        }

        // Exclude clients already attached to this campaign
        $alreadyAttachedIds = $campaign->clients()->pluck('clients.id')->all();
        if (!empty($alreadyAttachedIds)) {
            $query->whereNotIn('clients.id', $alreadyAttachedIds);
        }

        // Apply search if provided
        if ($search = trim((string) request()->get('search', request()->get('q')))) {
            $query->where(function ($q) use ($search) {
                $q->where('clients.name', 'like', "%{$search}%")
                    ->orWhere('clients.email', 'like', "%{$search}%")
                    ->orWhere('clients.phone', 'like', "%{$search}%")
                    ->orWhere('clients.cell_phone', 'like', "%{$search}%")
                    ->orWhere('clients.id_number', 'like', "%{$search}%")
                    ->orWhere('clients.import_batch_number', 'like', "%{$search}%");
            });
        }

        // Apply batch filter if provided
        if ($batch = trim((string) request()->get('batch'))) {
            $query->where('clients.import_batch_number', $batch);
        }

        $perPage = (int) request()->get('per_page', 50);

        // Get the paginated results
        $paginator = $query
            ->orderBy('clients.name')
            ->paginate($perPage);

        $paginator->getCollection()->transform(function ($client) {
            return array_merge($client->toArray(), [
                'phone' => $this->resolveClientPhone($client),
                'departments' => $client->departments->map(function ($dept) {
                    return [
                        'id' => $dept->id,
                        'name' => $dept->name
                    ];
                }),
                'department_names' => $client->departments->pluck('name')->join(', '),
            ]);
        });

        return response()->json($paginator);
    }

    /**
     * List only distinct batches available for this campaign to avoid huge payloads
     */
    public function availableClientBatches(Campaign $campaign)
    {
        $this->authorizeView($campaign);
        $campaign->loadMissing('departments');
        $deptIds = $campaign->departments->pluck('id')->all();

        $query = Client::query();

        if ($user = Auth::user()) {
            if ($user->isPortfolioScoped() && !$user->canViewAllImportedClients()) {
                $query->where('clients.assigned_to_id', $user->id);
            }
        }

        if (!empty($deptIds) && ($user && !$user->canViewAllImportedClients())) {
            $query->whereHas('departments', function ($q) use ($deptIds) {
                $q->whereIn('departments.id', $deptIds);
            });
        }

        // Exclude clients already attached to this campaign? 
        // For batch list, we don't strictly need to exclude them if we just want a list of batches.
        // But doing so makes it accurate.
        $alreadyAttachedIds = $campaign->clients()->pluck('clients.id')->all();
        if (!empty($alreadyAttachedIds)) {
            $query->whereNotIn('clients.id', $alreadyAttachedIds);
        }

        $batches = $query
            ->whereNotNull('import_batch_number')
            ->where('import_batch_number', '!=', '')
            ->distinct()
            ->pluck('import_batch_number')
            ->sort()
            ->reverse()
            ->values();

        return response()->json($batches);
    }

    /**
     * Attach clients to campaign.
     *
     * Payload:
     *  - add_all: bool
     *  - client_ids: [] (required if add_all = false)
     */
    public function attachClients(Request $request, Campaign $campaign)
    {
        $this->authorizeManageCampaign($campaign);

        $validated = $request->validate([
            'add_all'    => ['required', 'boolean'],
            'client_ids' => ['array'],
            'client_ids.*' => ['integer', 'exists:clients,id'],
            'import_batch_number' => ['nullable', 'string', 'max:255'],
        ]);

        $addAll = (bool) $validated['add_all'];
        $clientIds = $validated['client_ids'] ?? [];
        $importBatchNumber = $validated['import_batch_number'] ?? null;

        // Ensure campaign departments are loaded
        $campaign->loadMissing('departments');

        $deptIds = $campaign->departments->pluck('id')->all();

        // Build base allowed clients query (department-scoped)
        $allowedClientsQuery = Client::query();

        if ($user = Auth::user()) {
            if ($user->isPortfolioScoped() && !$user->canViewAllImportedClients()) {
                $allowedClientsQuery->where('assigned_to_id', $user->id);
            }
        }

        if (!empty($deptIds) && ($user && !$user->canViewAllImportedClients())) {
            $allowedClientsQuery->whereHas('departments', function ($q) use ($deptIds) {
                $q->whereIn('departments.id', $deptIds);
            });
        }

        if ($importBatchNumber) {
            if ($importBatchNumber === 'manual') {
                $allowedClientsQuery->where(function ($q) {
                    $q->whereNull('import_batch_number')->orWhere('import_batch_number', '');
                });
            } else {
                $allowedClientsQuery->where('import_batch_number', $importBatchNumber);
            }
        }

        // Exclude already attached clients
        $alreadyAttachedIds = $campaign->clients()->pluck('clients.id')->all();
        if (!empty($alreadyAttachedIds)) {
            $allowedClientsQuery->whereNotIn('id', $alreadyAttachedIds);
        }

        if ($addAll) {
            // Add ALL allowed clients
            $clientIdsToAttach = $allowedClientsQuery->pluck('id')->all();
        } else {
            // Add only selected client_ids, but intersect with allowed ones
            if (empty($clientIds)) {
                return response()->json([
                    'message' => 'client_ids is required when add_all is false.',
                ], 422);
            }

            if ($user && ($user->canViewAllImportedClients() || $user->canViewAllImportedClients())) {
                $clientIdsToAttach = Client::whereIn('id', $clientIds)
                    ->whereNotIn('id', $alreadyAttachedIds)
                    ->pluck('id')
                    ->all();
            } else {
                $clientIdsToAttach = $allowedClientsQuery
                    ->whereIn('id', $clientIds)
                    ->pluck('id')
                    ->all();
            }
        }

        if (empty($clientIdsToAttach)) {
            return response()->json([
                'message' => 'No clients available to attach.',
            ], 200);
        }

        // Prepare pivot data for bulk insert
        $pivotData = [];
        $now = now();
        foreach ($clientIdsToAttach as $clientId) {
            $pivotData[$clientId] = [
                'whatsapp_status' => 'Pending',
                'email_status' => 'Pending',
                'sms_status' => 'Pending',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Attach clients without dropping existing ones
        $campaign->clients()->syncWithoutDetaching($pivotData);

        return response()->json([
            'message'      => 'Clients successfully added to campaign.',
            'attached_ids' => $clientIdsToAttach,
            'attached_count' => count($clientIdsToAttach),
        ], 200);
    }

    public function detachClient(Campaign $campaign, Client $client): JsonResponse
    {
        $this->authorizeManageCampaign($campaign);

        $attachedQuery = $campaign->clients()->where('clients.id', $client->id);

        if (Auth::user()?->isPortfolioScoped()) {
            $attachedQuery->where('clients.assigned_to_id', Auth::id());
        }

        if (!$attachedQuery->exists()) {
            return response()->json([
                'message' => 'Client is not attached to this campaign.',
            ], 404);
        }

        $campaign->clients()->detach($client->id);

        return response()->json([
            'message' => 'Client removed from campaign.',
        ]);
    }

    public function detachClients(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorizeManageCampaign($campaign);

        $validated = $request->validate([
            'client_ids' => ['nullable', 'array'],
            'client_ids.*' => ['integer', 'exists:clients,id'],
            'import_batch_number' => ['nullable', 'string', 'max:255'],
            'clients_mode' => ['nullable', 'string', 'in:selected,all,unsent'],
            'channel' => ['nullable', 'string', 'in:whatsapp,email,sms'],
        ]);

        $clientIds = $validated['client_ids'] ?? [];
        $importBatchNumber = $validated['import_batch_number'] ?? null;
        $clientsMode = $validated['clients_mode'] ?? 'selected';

        if ($clientsMode === 'selected' && empty($clientIds) && !$importBatchNumber) {
            return response()->json([
                'message' => 'Select client_ids or provide an import_batch_number when mode is selected.',
            ], 422);
        }

        $query = $campaign->clients()->select('clients.id');

        if (Auth::user()?->isPortfolioScoped()) {
            $query->where('clients.assigned_to_id', Auth::id());
        }

        if ($clientsMode === 'selected') {
            if (!empty($clientIds)) {
                $query->whereIn('clients.id', $clientIds);
            }
            if ($importBatchNumber) {
                $query->where('clients.import_batch_number', $importBatchNumber);
            }
        } elseif ($clientsMode === 'unsent') {
            $channel = $validated['channel'] ?? 'whatsapp';
            if ($channel === 'whatsapp') {
                $query->where(function ($q) {
                    $q->whereNull('campaign_clients.whatsapp_status')
                      ->orWhereIn('campaign_clients.whatsapp_status', ['Pending', 'Unsent', '']);
                });
            } elseif ($channel === 'email') {
                $query->where(function ($q) {
                    $q->whereNull('campaign_clients.email_status')
                      ->orWhereIn('campaign_clients.email_status', ['Pending', 'Unsent', '']);
                });
            } elseif ($channel === 'sms') {
                $query->where(function ($q) {
                    $q->whereNull('campaign_clients.sms_status')
                      ->orWhereIn('campaign_clients.sms_status', ['Pending', 'Unsent', '']);
                });
            }
        }

        $idsToDetach = $query->pluck('clients.id')->all();

        if (empty($idsToDetach)) {
            return response()->json([
                'message' => 'No matching clients found to remove from this campaign.',
            ], 404);
        }

        $campaign->clients()->detach($idsToDetach);

        return response()->json([
            'message' => 'Clients removed from campaign.',
            'detached_count' => count($idsToDetach),
            'detached_ids' => $idsToDetach,
        ]);
    }

    /**
     * Clients attached to this campaign.
     */
    public function clients(Request $request, Campaign $campaign)
    {
        $this->authorizeView($campaign);

        $allowedPerPage = [25, 50, 100, 200, 300, 500, 1000];
        $perPage = (int) $request->integer('per_page', 25);
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 25;
        }

        $baseQuery = $campaign->clients()
            ->with(['departments', 'assignedTo:id,name']);

        if (Auth::user()?->isPortfolioScoped() && !Auth::user()?->canViewAllImportedClients()) {
            $baseQuery->where('clients.assigned_to_id', Auth::id());
        }

        $batchOptions = (clone $baseQuery)
            ->whereNotNull('clients.import_batch_number')
            ->distinct()
            ->orderByDesc('clients.import_batch_number')
            ->pluck('clients.import_batch_number')
            ->values();

        if ($search = trim((string) $request->get('search', $request->get('q')))) {
            $baseQuery->where(function ($q) use ($search) {
                $q->where('clients.name', 'like', "%{$search}%")
                    ->orWhere('clients.email', 'like', "%{$search}%")
                    ->orWhere('clients.phone', 'like', "%{$search}%")
                    ->orWhere('clients.cell_phone', 'like', "%{$search}%")
                    ->orWhere('clients.home_phone', 'like', "%{$search}%")
                    ->orWhere('clients.work_phone', 'like', "%{$search}%")
                    ->orWhere('clients.import_batch_number', 'like', "%{$search}%");
            });
        }

        if ($importBatchNumber = trim((string) $request->get('import_batch_number'))) {
            $baseQuery->where('clients.import_batch_number', $importBatchNumber);
        }

        if ($accountType = trim((string) $request->get('account_type'))) {
            $baseQuery->where('clients.account_type', $accountType);
        }

        if ($clientType = trim((string) $request->get('type'))) {
            $baseQuery->where('clients.type', $clientType);
        }

        $channel = $request->get('channel', 'whatsapp');
        $statusFilter = $request->get('status', 'all');

        if ($statusFilter !== 'all') {
            $statusColumn = "{$channel}_status";
            if ($statusFilter === 'unsent') {
                $baseQuery->where(function ($q) use ($statusColumn) {
                    $q->whereNull("campaign_clients.{$statusColumn}")
                      ->orWhereIn("campaign_clients.{$statusColumn}", ['Pending', 'Unsent', '']);
                });
            } elseif ($statusFilter === 'sent') {
                $baseQuery->whereIn("campaign_clients.{$statusColumn}", ['Sent', 'Delivered', 'Read', 'Opened', 'Clicked']);
            } elseif ($statusFilter === 'failed') {
                if ($channel === 'whatsapp') {
                    $baseQuery->whereIn("campaign_clients.whatsapp_status", ['Failed', 'Bounced']);
                } else {
                    $baseQuery->whereIn("campaign_clients.{$statusColumn}", ['Failed', 'Bounced']);
                }
            }
        }

        // Calculate Stats
        $statsQuery = clone $baseQuery;
        // Reset limit/offset/order for stats
        $statsQuery->orders = [];
        $statsQuery->limit = null;
        $statsQuery->offset = null;
        
        // Optimize the stats query by using SQL aggregations instead of loading all rows into memory
        $stats = $statsQuery->toBase()->selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN campaign_clients.{$channel}_status IN ('Sent', 'Delivered', 'Read', 'Opened', 'Clicked') THEN 1 ELSE 0 END) as sent,
            SUM(CASE WHEN campaign_clients.{$channel}_status IN ('Failed', 'Bounced') THEN 1 ELSE 0 END) as failed
        ")->first();

        $total = (int) ($stats->total ?? 0);
        $sent = (int) ($stats->sent ?? 0);
        $failed = (int) ($stats->failed ?? 0);
        $unsent = $total - $sent - $failed;

        $clientStats = [
            'total' => $total,
            'sent' => $sent,
            'failed' => $failed,
            'unsent' => $unsent,
        ];

        // Removed ?all=1 support to prevent memory exhaustion

        $paginator = $baseQuery
            ->orderBy('clients.name')
            ->paginate($perPage);

        $paginator->getCollection()->transform(function ($client) {
            $pivot = $client->pivot;

            return array_merge($client->toArray(), [
                'phone' => $this->resolveClientPhone($client),
                'assigned_to_name' => $client->assignedTo?->name,
                'departments' => $client->departments->map(function ($dept) {
                    return ['id' => $dept->id, 'name' => $dept->name];
                }),
                'whatsapp_status' => $pivot?->whatsapp_status ?? 'Pending',
                'whatsapp_sent_at' => $pivot?->whatsapp_sent_at,
                'email_status' => $pivot?->email_status ?? 'Pending',
                'email_sent_at' => $pivot?->email_sent_at,
                'sms_status' => $pivot?->sms_status ?? 'Pending',
                'sms_sent_at' => $pivot?->sms_sent_at,
                'created_at' => $pivot?->created_at ?? $client->created_at,
            ]);
        });

        return response()->json(array_merge($paginator->toArray(), [
            'batch_options' => $batchOptions,
            'client_stats' => $clientStats,
        ]));
    }

    public function exportClients(Request $request, Campaign $campaign): StreamedResponse
    {
        $this->authorizeView($campaign);
        $user = Auth::user();
        $exportRequest = $this->authorizeSensitiveExport($request, ExportRequest::DATASET_CAMPAIGN_CLIENTS, 'campaign', $campaign->id);

        $query = $campaign->clients()->with(['departments', 'assignedTo:id,name']);
        if ($user?->isPortfolioScoped()) {
            $query->where('clients.assigned_to_id', $user->id);
        }

        $fileName = 'campaign_clients_' . $campaign->id . '_' . now()->format('Ymd_His') . '.csv';
        $bankScope = $campaign->bank?->name ?? optional($user->bank)->name ?? 'Campaign Bank';

        $this->audit(
            action: "Exported campaign clients for campaign #{$campaign->id}",
            module: 'Campaigns',
            meta: [
                'campaign_id' => $campaign->id,
                'campaign_name' => $campaign->name,
                'dataset' => 'clients',
                'filename' => $fileName,
                'bank_scope' => $bankScope,
                'portfolio_scoped' => (bool) $user?->isPortfolioScoped(),
                'export_request_id' => $exportRequest?->id,
            ]
        );

        $this->markSensitiveExportCompleted($exportRequest, $fileName);

        return response()->stream(function () use ($query, $user, $campaign, $bankScope) {
            $handle = fopen('php://output', 'w');
            $this->writeExportMetadataRows($handle, 'Campaign Clients', $user, $bankScope, $campaign);
            fputcsv($handle, ['Client Name', 'Email', 'Phone', 'Bank', 'Assigned Owner', 'Departments', 'WhatsApp Status', 'Email Status', 'SMS Status']);

            $query->chunk(200, function ($clients) use ($handle, $campaign) {
                foreach ($clients as $client) {
                    $pivot = CampaignClient::where('campaign_id', $campaign->id)
                        ->where('client_id', $client->id)
                        ->first();

                    fputcsv($handle, [
                        $client->name,
                        $client->email,
                        $this->resolveClientPhone($client),
                        $client->bank_name,
                        $client->assignedTo?->name,
                        $client->departments->pluck('name')->join(', '),
                        $pivot->whatsapp_status ?? 'Pending',
                        $pivot->email_status ?? 'Pending',
                        $pivot->sms_status ?? 'Pending',
                    ]);
                }
            });

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ]);
    }



    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->canCreateCampaigns()) {
            abort(403, 'You are not allowed to create campaigns.');
        }

        $data = $request->validate([
            'name'              => ['required', 'string', 'max:255'],
            'bank_id'           => ['nullable', 'integer', 'exists:banks,id'],
            'department_ids'    => ['nullable', 'array'],
            'department_ids.*'  => ['integer', 'exists:departments,id'],
            'channels'          => ['required', 'array', 'min:1'],
            'channels.*'        => ['in:WhatsApp,Email,SMS'],
            'status'            => ['required', 'in:Draft,Scheduled,Active,Paused,Completed'],
            'scheduled_at'      => ['nullable', 'date'],
            'template_body'     => ['nullable', 'string'],
            'whatsapp_from'     => ['nullable', 'string', 'max:255'],
        ]);

        $deptIds = $data['department_ids'] ?? [];
        unset($data['department_ids']);

        $data['bank_id'] = $this->resolveCampaignBankId(Auth::user(), $data['bank_id'] ?? null);

        // Default WhatsApp from based on department or system
        if (empty($data['whatsapp_from'])) {
            if (!empty($deptIds)) {
                $firstDept = \App\Models\Department::find($deptIds[0]);
                $firstNumber = $firstDept?->primary_whatsapp_number ?? null;
                if ($firstNumber) {
                    $data['whatsapp_from'] = $firstNumber;
                }
            }
            if (empty($data['whatsapp_from'])) {
                $settings = \App\Models\SystemSetting::first();
                $data['whatsapp_from'] = $settings?->meta_whatsapp_display_phone_number ?: $settings?->twilio_whatsapp_from;
            }
        }

        $campaign = Campaign::create($data);

        if (!empty($deptIds)) {
            $campaign->departments()->sync($deptIds);
        }

        return response()->json($campaign->load(['departments', 'bank']), 201);
    }

    public function update(Request $request, Campaign $campaign)
    {
        $this->authorizeManageCampaign($campaign);

        $data = $request->validate([
            'name'              => ['sometimes', 'string', 'max:255'],
            'bank_id'           => ['sometimes', 'nullable', 'integer', 'exists:banks,id'],
            'department_ids'    => ['sometimes', 'nullable', 'array'],
            'department_ids.*'  => ['integer', 'exists:departments,id'],
            'channels'          => ['sometimes', 'array'],
            'channels.*'        => ['in:WhatsApp,Email,SMS'],
            'status'            => ['sometimes', 'in:Draft,Scheduled,Active,Paused,Completed'],
            'scheduled_at'      => ['sometimes', 'nullable', 'date'],
            'template_body'     => ['sometimes', 'nullable', 'string'],
            'whatsapp_from'     => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $deptIds = null;
        if (array_key_exists('department_ids', $data)) {
            $deptIds = $data['department_ids'] ?? [];
            unset($data['department_ids']);
        }

        if (array_key_exists('whatsapp_from', $data) && empty($data['whatsapp_from'])) {
            if (!empty($deptIds)) {
                $firstDept = \App\Models\Department::find($deptIds[0]);
                $firstNumber = $firstDept?->primary_whatsapp_number ?? null;
                if ($firstNumber) {
                    $data['whatsapp_from'] = $firstNumber;
                }
            }
            if (empty($data['whatsapp_from'])) {
                $settings = \App\Models\SystemSetting::first();
                $data['whatsapp_from'] = $settings?->meta_whatsapp_display_phone_number ?: $settings?->twilio_whatsapp_from;
            }
        }

        if (array_key_exists('bank_id', $data) || !$campaign->bank_id) {
            $data['bank_id'] = $this->resolveCampaignBankId(Auth::user(), $data['bank_id'] ?? $campaign->bank_id);
        }

        $campaign->update($data);

        if (!is_null($deptIds)) {
            $campaign->departments()->sync($deptIds);
        }

        return $campaign->load(['departments', 'bank']);
    }


    /**
     * Delete campaign.
     */
    public function destroy(Campaign $campaign)
    {
        $user = Auth::user();
        if (!$user || !$user->canDeleteCampaigns()) {
            abort(403, 'You are not allowed to delete campaigns.');
        }
        $this->authorizeView($campaign);

        $campaign->delete();

        return response()->noContent();
    }

    /**
     * Trigger sending of campaign (enqueue WhatsApp / Email / ZoomConnect jobs).
     * Here we just stub the flow - you plug your jobs + logic in.
     */
    public function send(Campaign $campaign)
    {
        $this->authorizeManageCampaign($campaign);
        $draftWhatsappMessages = $campaign->whatsappMessages()
            ->where(function ($query) {
                $query->whereNull('sent_at')
                    ->orWhere('status', 'Draft');
            })
            ->get();

        if ($draftWhatsappMessages->isNotEmpty()) {
            $this->enforceMetaPermissionHealthForProduction('Campaign WhatsApp send');
        }

        $queuedBatchCount = 0;
        $queuedRecipientCount = 0;
        $now = now();

        foreach ($draftWhatsappMessages as $message) {
            if ($message->mode === 'template' && empty($message->template_sid)) {
                continue;
            }

            $message->update([
                'sent_at' => $message->sent_at ?: $now,
                'status' => 'Queued',
                'queued_at' => $message->queued_at ?: $now,
                'processing_started_at' => null,
                'completed_at' => null,
                'paused_at' => null,
                'pause_reason' => null,
                'last_processed_at' => null,
                'messages_per_second' => $message->messages_per_second ?: $this->batchService->enforcedMessagesPerSecond(),
            ]);

            $recipientIds = $message->recipients()->pluck('client_id');
            if ($recipientIds->isNotEmpty()) {
                CampaignClient::where('campaign_id', $campaign->id)
                    ->whereIn('client_id', $recipientIds)
                    ->update([
                        'whatsapp_status' => 'Pending',
                        'whatsapp_sent_at' => $now,
                        'updated_at' => $now,
                    ]);
            }

            $queuedRecipientCount += $this->batchService->queueAllRecipients($message->fresh());
            $queuedBatchCount++;
        }

        return response()->json([
            'message'  => $queuedBatchCount > 0
                ? 'Campaign WhatsApp batches queued successfully.'
                : 'No unsent WhatsApp batches were available to queue.',
            'campaign' => $campaign->id,
            'queued_whatsapp_batches' => $queuedBatchCount,
            'queued_whatsapp_recipients' => $queuedRecipientCount,
            'whatsapp_daily_limit' => $this->dailyLimitService->summaryFor(Auth::user()),
        ]);
    }

    /**
     * Basic stats for CampaignShow.vue "Overview cards".
     * Currently returns zeroed stub – replace with real aggregates from your tables.
     */
    public function stats(Campaign $campaign)
    {
        $this->authorizeView($campaign);

        $user = Auth::user();
        $portfolioScoped = $user?->isPortfolioScoped();

        if ($portfolioScoped) {
            $assignedClientIds = $campaign->clients()
                ->where('clients.assigned_to_id', $user->id)
                ->pluck('clients.id');

            $totalClients = $assignedClientIds->count();

            $whatsRecipientQuery = CampaignWhatsappRecipient::query()
                ->whereIn('whatsapp_message_id', $campaign->whatsappMessages()->select('id'))
                ->whereIn('client_id', $assignedClientIds);

            $whatsTotals = [
                'total'     => (clone $whatsRecipientQuery)->count(),
                'delivered' => (clone $whatsRecipientQuery)->whereRaw('LOWER(status) = ?', ['delivered'])->count(),
                'failed'    => (clone $whatsRecipientQuery)->whereRaw('LOWER(status) = ?', ['failed'])->count(),
                'pending'   => (clone $whatsRecipientQuery)->whereIn(\DB::raw('LOWER(status)'), ['pending', 'queued', 'processing', 'paused', 'scheduled'])->count(),
            ];

            $emailRecipientQuery = CampaignEmailRecipient::query()
                ->whereIn('campaign_email_message_id', $campaign->emailMessages()->select('id'))
                ->whereIn('client_id', $assignedClientIds);

            $emailTotals = [
                'total'     => (clone $emailRecipientQuery)->count(),
                'delivered' => (clone $emailRecipientQuery)->whereRaw('LOWER(status) = ?', ['delivered'])->count(),
                'bounced'   => (clone $emailRecipientQuery)->whereRaw('LOWER(status) = ?', ['bounced'])->count(),
                'opened'    => (clone $emailRecipientQuery)->whereNotNull('opened_at')->count(),
                'clicked'   => (clone $emailRecipientQuery)->whereNotNull('clicked_at')->count(),
            ];

            $smsRecipientQuery = CampaignSmsRecipient::query()
                ->whereIn('campaign_sms_message_id', $campaign->smsMessages()->select('id'))
                ->whereIn('client_id', $assignedClientIds);

            $smsTotals = [
                'total'     => (clone $smsRecipientQuery)->count(),
                'delivered' => (clone $smsRecipientQuery)->whereRaw('LOWER(status) = ?', ['delivered'])->count(),
                'failed'    => (clone $smsRecipientQuery)->whereRaw('LOWER(status) = ?', ['failed'])->count(),
                'pending'   => (clone $smsRecipientQuery)->whereIn(\DB::raw('LOWER(status)'), ['pending', 'queued', 'scheduled'])->count(),
            ];
        } else {
            $campaign->load([
                'clients:id',
                'whatsappMessages:id,campaign_id,total,delivered,failed,pending',
                'emailMessages:id,campaign_id,total,delivered,bounced,opened,clicked',
                'smsMessages:id,campaign_id,total,delivered,failed,pending',
            ]);

            $totalClients = $campaign->clients()->count();

            $whatsTotals = [
                'total'     => $campaign->whatsappMessages->sum('total'),
                'delivered' => $campaign->whatsappMessages->sum('delivered'),
                'failed'    => $campaign->whatsappMessages->sum('failed'),
                'pending'   => $campaign->whatsappMessages->sum('pending'),
            ];

            $emailTotals = [
                'total'     => $campaign->emailMessages->sum('total'),
                'delivered' => $campaign->emailMessages->sum('delivered'),
                'bounced'   => $campaign->emailMessages->sum('bounced'),
                'opened'    => $campaign->emailMessages->sum('opened'),
                'clicked'   => $campaign->emailMessages->sum('clicked'),
            ];

            $smsTotals = [
                'total'     => $campaign->smsMessages->sum('total'),
                'delivered' => $campaign->smsMessages->sum('delivered'),
                'failed'    => $campaign->smsMessages->sum('failed'),
                'pending'   => $campaign->smsMessages->sum('pending'),
            ];
        }

        return response()->json([
            'total_clients'  => $totalClients,
            'whatsapp_sent'  => $whatsTotals['total'],
            'email_sent'     => $emailTotals['total'],
            'sms_sent'       => $smsTotals['total'],

            // For the “Delivery Statistics” cards in CampaignShow
            'delivered'      => $whatsTotals['delivered'] + $emailTotals['delivered'] + $smsTotals['delivered'],
            'failed'         => $whatsTotals['failed']    + $emailTotals['bounced']   + $smsTotals['failed'],
            'pending'        => $whatsTotals['pending']   + $smsTotals['pending'],
            'whatsapp_daily_limit' => $this->dailyLimitService->summaryFor($user),
            'whatsapp_messages_per_second' => $this->batchService->enforcedMessagesPerSecond(),
            'active_whatsapp_batches' => $campaign->whatsappMessages()
                ->whereIn('status', ['Queued', 'Processing', 'Paused'])
                ->count(),
        ]);
    }

    /**
     * WhatsApp message batches for this campaign.
     */
    public function whatsappMessages(Campaign $campaign)
    {
        $this->authorizeView($campaign);

        $user = Auth::user();

        $messages = $campaign->whatsappMessages()
            ->orderByDesc('created_at')
            ->with('autoReplies')
            ->withCount([
                'recipients as yes_responses_count' => function ($q) {
                    $q->whereRaw('LOWER(last_response) = ?', ['yes']);
                },
                'recipients as no_responses_count' => function ($q) {
                    $q->whereRaw('LOWER(last_response) = ?', ['no']);
                },
                'recipients as replies_count' => function ($q) {
                    $q->whereNotNull('last_response');
                },
            ])
            ->get([
                'id',
                'mode',
                'whatsapp_flow_id',
                'flow_name',
                'flow_definition',
                'template_sid',
                'template_name',
                'name',
                'preview_body',
                'template_variables',
                'sent_at',
                'total',
                'delivered',
                'failed',
                'pending',
                'status',
                'queued_at',
                'processing_started_at',
                'completed_at',
                'paused_at',
                'pause_reason',
                'last_processed_at',
                'messages_per_second',
                'created_by_user_id',
                'provider_display_phone_number',
                'enable_live_chat',
                'enable_email_notification',
                'created_at',
            ]);

        $assignedClientIds = null;
        if ($user?->isPortfolioScoped() && !$user?->canViewAllImportedClients()) {
            $assignedClientIds = $campaign->clients()
                ->where('clients.assigned_to_id', $user->id)
                ->pluck('clients.id');
        }

        $mapped = $messages->map(function ($m) use ($assignedClientIds) {
            $total = $m->total;
            $delivered = $m->delivered;
            $failed = $m->failed;
            $pending = $m->pending;
            $queued = 0;
            $processing = 0;
            $paused = 0;
            $repliesCount = $m->replies_count ?? 0;
            $yesResponsesCount = $m->yes_responses_count ?? 0;
            $noResponsesCount = $m->no_responses_count ?? 0;

            if ($assignedClientIds !== null) {
                $recipientQuery = CampaignWhatsappRecipient::query()
                    ->where('whatsapp_message_id', $m->id)
                    ->whereIn('client_id', $assignedClientIds);

                $total = (clone $recipientQuery)->count();
                $delivered = (clone $recipientQuery)->where(function($q) {
                    $q->whereIn('status', ['Delivered', 'Delivered (Ecosystem Warning)'])
                      ->orWhereRaw('LOWER(status) = ?', ['delivered'])
                      ->orWhereIn('error_code', ['131049', '131026'])
                      ->orWhere('error_message', 'like', '%maintain healthy ecosystem engagement%');
                })->count();
                $failed = (clone $recipientQuery)->whereRaw('LOWER(status) = ?', ['failed'])
                    ->where(function($q) {
                        $q->whereNotIn('error_code', ['131049', '131026'])
                          ->orWhereNull('error_code');
                    })
                    ->where(function($q) {
                        $q->where('error_message', 'not like', '%maintain healthy ecosystem engagement%')
                          ->orWhereNull('error_message');
                    })->count();
                $queued = (clone $recipientQuery)->whereRaw("LOWER(status) = 'queued'")->count();
                $processing = (clone $recipientQuery)->whereRaw("LOWER(status) = 'processing'")->count();
                $paused = (clone $recipientQuery)->whereRaw("LOWER(status) = 'paused'")->count();
                $pending = (clone $recipientQuery)->whereRaw("LOWER(status) in ('pending','queued','processing','paused','scheduled','sent')")->count();
                $repliesCount = (clone $recipientQuery)->whereNotNull('last_response')->count();
                $yesResponsesCount = (clone $recipientQuery)->whereRaw('LOWER(last_response) = ?', ['yes'])->count();
                $noResponsesCount = (clone $recipientQuery)->whereRaw('LOWER(last_response) = ?', ['no'])->count();
            } else {
                $counts = $this->batchService->recipientCounts($m);
                $queued = $counts['queued'];
                $processing = $counts['processing'];
                $paused = $counts['paused'];
                $pending = $counts['pending'];
            }
            $status = $m->status ?: $this->batchService->resolveMessageStatus($m, [
                'total' => $total,
                'delivered' => $delivered,
                'failed' => $failed,
                'queued' => $queued,
                'processing' => $processing,
                'paused' => $paused,
                'provider_pending' => max($pending - $queued - $processing - $paused, 0),
                'suppressed' => 0,
                'pending' => $pending,
            ]);

            return [
                'id'            => $m->id,
                'mode'          => $m->mode ?? 'template',
                'whatsapp_flow_id' => $m->whatsapp_flow_id,
                'flow_name'     => $m->flow_name,
                'flow_definition' => $m->flow_definition,
                'template_sid'  => $m->template_sid,
                'template_name' => $m->template_name,
                'name'          => $m->name,
                'preview_body'  => $m->preview_body,
                'template_variables' => $m->template_variables ?? [],
                'sent_at'       => optional($m->sent_at)->toDateTimeString(),
                'total'         => $total,
                'delivered'     => $delivered,
                'failed'        => $failed,
                'pending'       => $pending,
                'queued'        => $queued,
                'processing'    => $processing,
                'paused'        => $paused,
                'created_at'    => optional($m->created_at)->toDateTimeString(),
                'queued_at'     => optional($m->queued_at)->toDateTimeString(),
                'processing_started_at' => optional($m->processing_started_at)->toDateTimeString(),
                'completed_at'  => optional($m->completed_at)->toDateTimeString(),
                'paused_at'     => optional($m->paused_at)->toDateTimeString(),
                'pause_reason'  => $m->pause_reason,
                'last_processed_at' => optional($m->last_processed_at)->toDateTimeString(),
                'messages_per_second' => (int) ($m->messages_per_second ?: $this->batchService->enforcedMessagesPerSecond()),
                'created_by_user_id' => $m->created_by_user_id,
                'reply_number'  => $m->provider_display_phone_number,
                'enable_live_chat' => (bool) $m->enable_live_chat,
                'enable_email_notification' => (bool) ($m->enable_email_notification ?? true),
                'yes_responses_count' => $yesResponsesCount,
                'no_responses_count' => $noResponsesCount,
                'replies_count' => $repliesCount,
                'status'        => $status,
                'can_pause'     => in_array($status, ['Queued', 'Processing'], true),
                'can_resume'    => $status === 'Paused',
                'can_retry_failed' => $failed > 0,
                'auto_replies'  => $m->autoReplies,
            ];
        });

        return response()->json($mapped);
    }

    public function exportWhatsappMessages(Request $request, Campaign $campaign): StreamedResponse
    {
        $this->authorizeView($campaign);
        $user = Auth::user();
        $exportRequest = $this->authorizeSensitiveExport($request, ExportRequest::DATASET_CAMPAIGN_WHATSAPP_MESSAGES, 'campaign', $campaign->id);
        $bankScope = $campaign->bank?->name ?? optional($user->bank)->name ?? 'Campaign Bank';
        $fileName = 'campaign_whatsapp_' . $campaign->id . '_' . now()->format('Ymd_His') . '.csv';

        $this->audit(
            action: "Exported campaign WhatsApp recipients for campaign #{$campaign->id}",
            module: 'Campaigns',
            meta: [
                'campaign_id' => $campaign->id,
                'campaign_name' => $campaign->name,
                'dataset' => 'whatsapp',
                'filename' => $fileName,
                'bank_scope' => $bankScope,
                'portfolio_scoped' => (bool) $user?->isPortfolioScoped(),
                'export_request_id' => $exportRequest?->id,
            ]
        );

        $this->markSensitiveExportCompleted($exportRequest, $fileName);

        return response()->stream(function () use ($campaign, $user, $bankScope) {
            $handle = fopen('php://output', 'w');
            $this->writeExportMetadataRows($handle, 'Campaign WhatsApp Recipients', $user, $bankScope, $campaign);
            fputcsv($handle, ['Batch ID', 'Template', 'Client Name', 'Phone', 'Bank', 'Assigned Owner', 'Status', 'Delivered At', 'Last Response', 'Last Response At']);

            $query = CampaignWhatsappRecipient::with(['client.assignedTo:id,name', 'message'])
                ->whereIn('whatsapp_message_id', $campaign->whatsappMessages()->select('id'));

            if ($user?->isPortfolioScoped()) {
                $query->whereHas('client', fn ($q) => $q->where('assigned_to_id', $user->id));
            }

            $query->orderByDesc('id')->chunk(200, function ($rows) use ($handle) {
                foreach ($rows as $row) {
                    fputcsv($handle, [
                        $row->whatsapp_message_id,
                        $row->message?->template_name ?? $row->message?->name,
                        $row->client?->name,
                        $row->phone ?: $row->client?->phone,
                        $row->client?->bank_name,
                        $row->client?->assignedTo?->name,
                        $row->status,
                        optional($row->delivered_at)->toDateTimeString(),
                        $row->last_response,
                        optional($row->last_response_at)->toDateTimeString(),
                    ]);
                }
            });

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ]);
    }

    /**
     * Update a WhatsApp batch (replace recipients and optionally send).
     */
    public function updateWhatsappMessage(Request $request, Campaign $campaign, $messageId)
    {
        $this->authorizeManageCampaign($campaign);

        /** @var \App\Models\CampaignWhatsappMessage $message */
        $message = $campaign->whatsappMessages()->where('id', $messageId)->firstOrFail();

        $data = $request->validate([
            'mode'             => ['required', 'in:template,flow'],
            'template_id'      => ['nullable', 'string'],
            'flow_id'          => ['nullable', 'integer', 'exists:whatsapp_flows,id'],
            'template_variables' => ['sometimes', 'array'],
            'clients_mode'     => ['required', 'in:all,selected,unsent'],
            'client_ids'       => ['array'],
            'client_ids.*'     => ['integer', 'exists:clients,id'],
            'send_now'         => ['sometimes', 'boolean'],
            'enable_live_chat' => ['sometimes', 'boolean'],
            'enable_email_notification' => ['sometimes', 'boolean'],
            'auto_replies'     => ['sometimes', 'array'],
            'auto_replies.*.trigger_keyword' => ['required', 'string'],
            'auto_replies.*.template_sid' => ['required', 'string'],
            'auto_replies.*.template_name' => ['nullable', 'string'],
            'auto_replies.*.template_variables' => ['nullable', 'array'],
        ]);

        $sendNow = $data['send_now'] ?? false;
        if ($sendNow) {
            $this->enforceMetaPermissionHealthForProduction('WhatsApp batch sending');
        }

        if ($data['clients_mode'] === 'selected' && empty($data['client_ids'])) {
            return response()->json(['message' => 'client_ids is required when clients_mode = selected'], 422);
        }

        $mode = $data['mode'] ?? 'template';
        if ($mode === 'template' && empty($data['template_id'])) {
            return response()->json(['message' => 'template_id is required for template mode'], 422);
        }
        if ($mode === 'flow' && empty($data['flow_id'])) {
            return response()->json(['message' => 'flow_id is required for flow mode'], 422);
        }

        // Build clients list based on selection
        $clientsQuery = $campaign->clients();
        if ($data['clients_mode'] === 'selected') {
            $clientsQuery->whereIn('clients.id', $data['client_ids']);
        } elseif ($data['clients_mode'] === 'unsent') {
            $clientsQuery->where(function ($q) {
                $q->whereNull('campaign_clients.whatsapp_status')
                  ->orWhereIn('campaign_clients.whatsapp_status', ['Pending', 'Unsent', '']);
            });
        }

        $clients = $clientsQuery->get([
            'clients.id',
            'clients.name',
            'clients.phone',
            'clients.email',
            'clients.id_number',
            'clients.account_number',
            'clients.bank_name',
            'clients.branch_code',
            'clients.whatsapp_opted_out_at',
            'clients.whatsapp_opt_out_reason',
            'clients.whatsapp_contact_basis',
            'clients.whatsapp_opted_in_at',
        ]);
        if ($clients->isEmpty()) {
            return response()->json(['message' => 'No clients found for this batch.'], 422);
        }

        $clients = $clients->filter(fn ($client) => $this->canSendWhatsappToClient($client))->values();
        if ($clients->isEmpty()) {
            return response()->json(['message' => 'All selected clients are blocked by WhatsApp compliance controls (opt-out or missing lawful basis).'], 422);
        }

        if ($sendNow) {
            $limitCheck = $this->dailyLimitService->validateSendAllowance(Auth::user(), $clients->count());
            if (!$limitCheck['allowed']) {
                return response()->json([
                    'message' => $limitCheck['message'],
                    'whatsapp_daily_limit' => $limitCheck['summary'],
                ], 422);
            }
        }

        // Refresh template/flow info
        $templateSid  = null;
        $friendlyName = null;
        $previewBody  = null;
        $flowId       = null;
        $flowName     = null;
        $flowDef      = null;
        $templateVariables = null;

        if ($mode === 'template') {
            $templateSid  = $data['template_id'];
            $template     = $this->whatsApp->getTemplateDetails($templateSid);
            $friendlyName = $template['name'] ?? $templateSid;
            $previewBody  = collect($template['components'] ?? [])
                ->firstWhere('type', 'BODY')['text'] ?? null;
            $templateVariables = $this->normalizeTemplateVariables(
                $data['template_variables'] ?? [],
                $template['variables'] ?? []
            );
        } else {
            $flow        = WhatsAppFlow::findOrFail($data['flow_id']);
            $flowId      = $flow->id;
            $flowName    = $flow->name;
            $flowDef     = $flow->flow_definition;
            $templateSid = $flow->template_sid;
            $friendlyName = $flowName;
            $previewBody = $flowDef && isset($flowDef[0]['message']) ? $flowDef[0]['message'] : 'Flow start';
        }

        $senderContext = $this->resolveWhatsappSenderContext($campaign->whatsapp_from);

        $total = $clients->count();
        $now   = now();

        // Reset recipients
        CampaignWhatsappRecipient::where('whatsapp_message_id', $message->id)->delete();

        $rows = [];
        foreach ($clients as $client) {
            $rows[] = [
                'whatsapp_message_id' => $message->id,
                'client_id'           => $client->id,
                'phone'               => $this->resolveClientPhone($client),
                'provider_phone_number_id' => $senderContext['phone_number_id'],
                'provider_display_phone_number' => $senderContext['display_phone_number'],
                'status'              => $sendNow ? 'Queued' : 'Draft',
                'queued_at'           => $sendNow ? $now : null,
                'processing_started_at' => null,
                'last_attempted_at'   => null,
                'attempts_count'      => 0,
                'created_at'          => $now,
                'updated_at'          => $now,
            ];
        }
        CampaignWhatsappRecipient::insert($rows);

        $message->update([
            'mode'             => $mode,
            'template_sid'     => $templateSid,
            'template_name'    => $friendlyName,
            'provider_phone_number_id' => $senderContext['phone_number_id'],
            'provider_display_phone_number' => $senderContext['display_phone_number'],
            'name'             => $friendlyName,
            'preview_body'     => $previewBody,
            'template_variables' => $templateVariables,
            'whatsapp_flow_id' => $flowId,
            'flow_name'        => $flowName,
            'flow_definition'  => $flowDef,
            'sent_at'          => $sendNow ? $now : null,
            'total'            => $total,
            'delivered'        => 0,
            'failed'           => 0,
            'pending'          => $sendNow ? $total : 0,
            'status'           => $sendNow ? 'Queued' : 'Draft',
            'queued_at'        => $sendNow ? $now : null,
            'processing_started_at' => null,
            'completed_at'     => null,
            'paused_at'        => null,
            'pause_reason'     => null,
            'last_processed_at'=> null,
            'messages_per_second' => $message->messages_per_second ?: $this->batchService->enforcedMessagesPerSecond(),
            'enable_live_chat' => $data['enable_live_chat'] ?? $message->enable_live_chat,
            'enable_email_notification' => $data['enable_email_notification'] ?? $message->enable_email_notification,
            'created_by_user_id' => $message->created_by_user_id ?: Auth::id(),
        ]);

        if (isset($data['auto_replies'])) {
            $message->autoReplies()->delete();
            foreach ($data['auto_replies'] as $reply) {
                $message->autoReplies()->create([
                    'trigger_keyword' => $reply['trigger_keyword'],
                    'template_sid' => $reply['template_sid'],
                    'template_name' => $reply['template_name'] ?? null,
                    'template_variables' => $reply['template_variables'] ?? null,
                ]);
            }
        }


        $queuedCount = 0;
        if ($sendNow) {
            // Mark campaign clients as pending
            CampaignClient::where('campaign_id', $campaign->id)
                ->whereIn('client_id', $clients->pluck('id'))
                ->update([
                    'whatsapp_status'  => 'Pending',
                    'whatsapp_sent_at' => $now,
                    'updated_at'       => $now,
                ]);
            $queuedCount = $this->batchService->queueAllRecipients($message->fresh());
        }

        return response()->json([
            'message' => 'Batch ' . ($sendNow ? 'queued' : 'updated') . ' successfully.',
            'id'      => $message->id,
            'queued_count' => $queuedCount,
            'whatsapp_daily_limit' => $this->dailyLimitService->summaryFor(Auth::user()),
        ]);
    }

    /**
     * Quick toggle for Live Chat status on a WhatsApp batch.
     */
    public function toggleLiveChat(Campaign $campaign, $messageId)
    {
        $this->authorizeManageCampaign($campaign);
        /** @var \App\Models\CampaignWhatsappMessage $message */
        $message = $campaign->whatsappMessages()->where('id', $messageId)->firstOrFail();
        
        $newStatus = !$message->enable_live_chat;
        $message->update([
            'enable_live_chat' => $newStatus,
        ]);

        return response()->json([
            'message' => 'Live chat status updated to ' . ($newStatus ? 'Enabled' : 'Disabled') . '.',
            'enable_live_chat' => $newStatus,
        ]);
    }

    /**
     * Quick toggle for Email Notification status on a WhatsApp batch.
     */
    public function toggleEmailNotification(Campaign $campaign, $messageId)
    {
        $this->authorizeManageCampaign($campaign);
        /** @var \App\Models\CampaignWhatsappMessage $message */
        $message = $campaign->whatsappMessages()->where('id', $messageId)->firstOrFail();
        
        $newStatus = !($message->enable_email_notification ?? true);
        $message->update([
            'enable_email_notification' => $newStatus,
        ]);

        return response()->json([
            'message' => 'Email notification status updated to ' . ($newStatus ? 'Enabled' : 'Disabled') . '.',
            'enable_email_notification' => $newStatus,
        ]);
    }

    /**
     * Send an existing draft batch.
     */
    public function sendDraftWhatsappMessage(Request $request, Campaign $campaign, $messageId)
    {
        $this->authorizeManageCampaign($campaign);
        $this->enforceMetaPermissionHealthForProduction('WhatsApp draft sending');

        /** @var \App\Models\CampaignWhatsappMessage $message */
        $message = $campaign->whatsappMessages()->where('id', $messageId)->firstOrFail();

        if ($message->sent_at) {
            return response()->json(['message' => 'Batch already sent.'], 422);
        }

        if (!$message->template_sid && $message->mode === 'template') {
            return response()->json(['message' => 'Template ID missing for this batch.'], 422);
        }

        $recipients = CampaignWhatsappRecipient::with('client')
            ->where('whatsapp_message_id', $message->id)
            ->get();

        $senderContext = $this->resolveWhatsappSenderContext(
            $message->provider_display_phone_number ?: $campaign->whatsapp_from
        );

        if ($recipients->isEmpty()) {
            return response()->json(['message' => 'No recipients found for this batch.'], 422);
        }

        $sendableRecipients = $recipients->filter(function ($recipient) {
            $client = $recipient->client;
            $phone = $recipient->phone ?: $client?->phone;
            return !empty($phone) && (!$client || $this->canSendWhatsappToClient($client));
        })->values();

        $limitCheck = $this->dailyLimitService->validateSendAllowance(Auth::user(), $sendableRecipients->count());
        if (!$limitCheck['allowed']) {
            return response()->json([
                'message' => $limitCheck['message'],
                'whatsapp_daily_limit' => $limitCheck['summary'],
            ], 422);
        }

        $now = now();

        // Update recipient statuses to queued
        CampaignWhatsappRecipient::where('whatsapp_message_id', $message->id)
            ->update([
                'status' => 'Queued',
                'queued_at' => $now,
                'processing_started_at' => null,
                'provider_phone_number_id' => $senderContext['phone_number_id'],
                'provider_display_phone_number' => $senderContext['display_phone_number'],
                'updated_at' => $now,
            ]);

        // Update message meta
        $message->update([
            'sent_at'   => $now,
            'pending'   => $recipients->count(),
            'delivered' => 0,
            'failed'    => 0,
            'status'    => 'Queued',
            'queued_at' => $now,
            'processing_started_at' => null,
            'completed_at' => null,
            'paused_at' => null,
            'pause_reason' => null,
            'last_processed_at' => null,
            'messages_per_second' => $message->messages_per_second ?: $this->batchService->enforcedMessagesPerSecond(),
            'created_by_user_id' => $message->created_by_user_id ?: Auth::id(),
            'provider_phone_number_id' => $senderContext['phone_number_id'],
            'provider_display_phone_number' => $senderContext['display_phone_number'],
        ]);

        // Update campaign client pivots
        CampaignClient::where('campaign_id', $campaign->id)
            ->whereIn('client_id', $recipients->pluck('client_id'))
            ->update([
                'whatsapp_status'  => 'Pending',
                'whatsapp_sent_at' => $now,
                'updated_at'       => $now,
            ]);

        $queuedCount = $this->batchService->queueAllRecipients($message->fresh());

        return response()->json([
            'message' => 'Batch queued successfully.',
            'queued_count' => $queuedCount,
            'whatsapp_daily_limit' => $this->dailyLimitService->summaryFor(Auth::user()),
        ]);
    }

    /**
     * Delete a WhatsApp batch.
     */
    public function deleteWhatsappMessage(Campaign $campaign, $messageId)
    {
        $this->authorizeManageCampaign($campaign);

        $message = $campaign->whatsappMessages()->where('id', $messageId)->firstOrFail();
        $message->delete();

        return response()->noContent();
    }

    public function pauseWhatsappMessage(Campaign $campaign, $messageId)
    {
        $this->authorizeManageCampaign($campaign);

        /** @var CampaignWhatsappMessage $message */
        $message = $campaign->whatsappMessages()->where('id', $messageId)->firstOrFail();

        if (in_array($message->status, ['Completed', 'Completed With Failures', 'Failed', 'Draft'], true)) {
            return response()->json(['message' => 'Only queued or processing WhatsApp batches can be paused.'], 422);
        }

        $reason = 'Paused manually by ' . (Auth::user()?->name ?? 'system') . ' on ' . now()->toDateTimeString() . '.';
        $this->batchService->pauseMessage($message, $reason);

        return response()->json([
            'message' => 'WhatsApp batch paused.',
            'batch' => $message->fresh(),
            'whatsapp_daily_limit' => $this->dailyLimitService->summaryFor(Auth::user()),
        ]);
    }

    public function resumeWhatsappMessage(Campaign $campaign, $messageId)
    {
        $this->authorizeManageCampaign($campaign);
        $this->enforceMetaPermissionHealthForProduction('WhatsApp batch resume');

        /** @var CampaignWhatsappMessage $message */
        $message = $campaign->whatsappMessages()->where('id', $messageId)->firstOrFail();

        if ($message->status !== 'Paused') {
            return response()->json(['message' => 'Only paused WhatsApp batches can be resumed.'], 422);
        }

        $result = $this->batchService->resumeMessage($message);

        return response()->json([
            'message' => 'WhatsApp batch resumed.',
            'queued_count' => $result['queued_count'],
            'batch' => $result['message'],
            'whatsapp_daily_limit' => $this->dailyLimitService->summaryFor(Auth::user()),
        ]);
    }

    public function retryFailedWhatsappRecipients(Campaign $campaign, $messageId)
    {
        $this->authorizeManageCampaign($campaign);
        $this->enforceMetaPermissionHealthForProduction('WhatsApp failed-recipient retry');

        /** @var CampaignWhatsappMessage $message */
        $message = $campaign->whatsappMessages()->where('id', $messageId)->firstOrFail();

        $failedCount = $message->recipients()->whereRaw('LOWER(status) = ?', ['failed'])->count();
        if ($failedCount === 0) {
            return response()->json(['message' => 'No failed recipients are available to retry for this batch.'], 422);
        }

        $result = $this->batchService->retryFailedRecipients($message);

        return response()->json([
            'message' => 'Failed recipients re-queued.',
            'queued_count' => $result['queued_count'],
            'batch' => $result['message'],
            'whatsapp_daily_limit' => $this->dailyLimitService->summaryFor(Auth::user()),
        ]);
    }


    /**
     * Emails sent for this campaign.
     */
    public function emails(Campaign $campaign)
    {
        $this->authorizeView($campaign);

        $user = Auth::user();

        $messages = $campaign->emailMessages()
            ->orderByDesc('sent_at')
            ->get([
                'id',
                'subject',
                'preview_body',
                'sent_at',
                'total',
                'delivered',
                'bounced',
                'opened',
                'clicked',
            ]);

        if ($user?->isPortfolioScoped()) {
            $assignedClientIds = $campaign->clients()
                ->where('clients.assigned_to_id', $user->id)
                ->pluck('clients.id');

            $messages = $messages->map(function ($message) use ($assignedClientIds) {
                $recipientQuery = CampaignEmailRecipient::query()
                    ->where('campaign_email_message_id', $message->id)
                    ->whereIn('client_id', $assignedClientIds);

                $message->total = (clone $recipientQuery)->count();
                $message->delivered = (clone $recipientQuery)->whereRaw('LOWER(status) = ?', ['delivered'])->count();
                $message->bounced = (clone $recipientQuery)->whereRaw('LOWER(status) = ?', ['bounced'])->count();
                $message->opened = (clone $recipientQuery)->whereNotNull('opened_at')->count();
                $message->clicked = (clone $recipientQuery)->whereNotNull('clicked_at')->count();

                return $message;
            });
        }

        return response()->json($messages);
    }

    public function exportEmails(Request $request, Campaign $campaign): StreamedResponse
    {
        $this->authorizeView($campaign);
        $user = Auth::user();
        $exportRequest = $this->authorizeSensitiveExport($request, ExportRequest::DATASET_CAMPAIGN_EMAILS, 'campaign', $campaign->id);
        $bankScope = $campaign->bank?->name ?? optional($user->bank)->name ?? 'Campaign Bank';
        $fileName = 'campaign_emails_' . $campaign->id . '_' . now()->format('Ymd_His') . '.csv';

        $this->audit(
            action: "Exported campaign email recipients for campaign #{$campaign->id}",
            module: 'Campaigns',
            meta: [
                'campaign_id' => $campaign->id,
                'campaign_name' => $campaign->name,
                'dataset' => 'email',
                'filename' => $fileName,
                'bank_scope' => $bankScope,
                'portfolio_scoped' => (bool) $user?->isPortfolioScoped(),
                'export_request_id' => $exportRequest?->id,
            ]
        );

        $this->markSensitiveExportCompleted($exportRequest, $fileName);

        return response()->stream(function () use ($campaign, $user, $bankScope) {
            $handle = fopen('php://output', 'w');
            $this->writeExportMetadataRows($handle, 'Campaign Email Recipients', $user, $bankScope, $campaign);
            fputcsv($handle, ['Batch ID', 'Subject', 'Client Name', 'Email', 'Phone', 'Bank', 'Assigned Owner', 'Status', 'Delivered At', 'Opened At', 'Clicked At']);

            $query = CampaignEmailRecipient::with(['client.assignedTo:id,name', 'message'])
                ->whereIn('campaign_email_message_id', $campaign->emailMessages()->select('id'));

            if ($user?->isPortfolioScoped()) {
                $query->whereHas('client', fn ($q) => $q->where('assigned_to_id', $user->id));
            }

            $query->orderByDesc('id')->chunk(200, function ($rows) use ($handle) {
                foreach ($rows as $row) {
                    fputcsv($handle, [
                        $row->campaign_email_message_id,
                        $row->message?->subject,
                        $row->client?->name,
                        $row->email ?: $row->client?->email,
                        $row->client?->phone,
                        $row->client?->bank_name,
                        $row->client?->assignedTo?->name,
                        $row->status,
                        optional($row->delivered_at)->toDateTimeString(),
                        optional($row->opened_at)->toDateTimeString(),
                        optional($row->clicked_at)->toDateTimeString(),
                    ]);
                }
            });

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ]);
    }

    public function emailRecipients(Campaign $campaign, $emailId)
    {
        $this->authorizeView($campaign);

        $user = Auth::user();

        $message = $campaign->emailMessages()
            ->where('id', $emailId)
            ->firstOrFail();

        $recipientsQuery = CampaignEmailRecipient::with('client.assignedTo:id,name')
            ->where('campaign_email_message_id', $message->id);

        if ($user?->isPortfolioScoped()) {
            $recipientsQuery->whereHas('client', function ($q) use ($user) {
                $q->where('assigned_to_id', $user->id);
            });
        }

        $recipients = $recipientsQuery
            ->get()
            ->map(function ($r) {
                return [
                    'id'           => $r->id,
                    'client_name'  => $r->client?->name,
                    'email'        => $r->email ?: $r->client?->email,
                    'phone'        => $r->client?->phone,
                    'bank_name'    => $r->client?->bank_name,
                    'assigned_to_name' => $r->client?->assignedTo?->name,
                    'status'       => $r->status,
                    'delivered_at' => optional($r->delivered_at)->toDateTimeString(),
                ];
            });

        $summary = [
            'total'    => $recipients->count(),
            'delivered'=> $recipients->where('status', 'Delivered')->count(),
            'bounced'  => $recipients->where('status', 'Bounced')->count(),
            'opened'   => $recipients->filter(fn ($r) => !empty($r['opened_at']))->count(),
            'clicked'  => $recipients->filter(fn ($r) => !empty($r['clicked_at']))->count(),
        ];

        return response()->json([
            'message'   => [
                'id'      => $message->id,
                'subject' => $message->subject,
                'sent_at' => optional($message->sent_at)->toDateTimeString(),
            ],
            'summary'   => $summary,
            'recipients'=> $recipients,
        ]);
    }


    /**
     * SMS messages for this campaign.
     */
    public function smsMessages(Campaign $campaign)
    {
        $this->authorizeView($campaign);

        $user = Auth::user();

        $messages = $campaign->smsMessages()
            ->orderByDesc('sent_at')
            ->get([
                'id',
                'text',
                'sent_at',
                'total',
                'delivered',
                'failed',
                'pending',
            ]);

        if ($user?->isPortfolioScoped()) {
            $assignedClientIds = $campaign->clients()
                ->where('clients.assigned_to_id', $user->id)
                ->pluck('clients.id');

            $messages = $messages->map(function ($message) use ($assignedClientIds) {
                $recipientQuery = CampaignSmsRecipient::query()
                    ->where('campaign_sms_message_id', $message->id)
                    ->whereIn('client_id', $assignedClientIds);

                $message->total = (clone $recipientQuery)->count();
                $message->delivered = (clone $recipientQuery)->whereRaw('LOWER(status) = ?', ['delivered'])->count();
                $message->failed = (clone $recipientQuery)->whereRaw('LOWER(status) = ?', ['failed'])->count();
                $message->pending = (clone $recipientQuery)->whereRaw("LOWER(status) in ('pending','queued','scheduled')")->count();

                return $message;
            });
        }

        return response()->json($messages);
    }

    public function exportSmsMessages(Request $request, Campaign $campaign): StreamedResponse
    {
        $this->authorizeView($campaign);
        $user = Auth::user();
        $exportRequest = $this->authorizeSensitiveExport($request, ExportRequest::DATASET_CAMPAIGN_SMS_MESSAGES, 'campaign', $campaign->id);
        $bankScope = $campaign->bank?->name ?? optional($user->bank)->name ?? 'Campaign Bank';
        $fileName = 'campaign_sms_' . $campaign->id . '_' . now()->format('Ymd_His') . '.csv';

        $this->audit(
            action: "Exported campaign SMS recipients for campaign #{$campaign->id}",
            module: 'Campaigns',
            meta: [
                'campaign_id' => $campaign->id,
                'campaign_name' => $campaign->name,
                'dataset' => 'sms',
                'filename' => $fileName,
                'bank_scope' => $bankScope,
                'portfolio_scoped' => (bool) $user?->isPortfolioScoped(),
                'export_request_id' => $exportRequest?->id,
            ]
        );

        $this->markSensitiveExportCompleted($exportRequest, $fileName);

        return response()->stream(function () use ($campaign, $user, $bankScope) {
            $handle = fopen('php://output', 'w');
            $this->writeExportMetadataRows($handle, 'Campaign SMS Recipients', $user, $bankScope, $campaign);
            fputcsv($handle, ['Batch ID', 'Client Name', 'Phone', 'Bank', 'Assigned Owner', 'Status', 'Delivered At']);

            $query = CampaignSmsRecipient::with(['client.assignedTo:id,name', 'message'])
                ->whereIn('campaign_sms_message_id', $campaign->smsMessages()->select('id'));

            if ($user?->isPortfolioScoped()) {
                $query->whereHas('client', fn ($q) => $q->where('assigned_to_id', $user->id));
            }

            $query->orderByDesc('id')->chunk(200, function ($rows) use ($handle) {
                foreach ($rows as $row) {
                    fputcsv($handle, [
                        $row->campaign_sms_message_id,
                        $row->client?->name,
                        $row->phone ?: $row->client?->phone,
                        $row->client?->bank_name,
                        $row->client?->assignedTo?->name,
                        $row->status,
                        optional($row->delivered_at)->toDateTimeString(),
                    ]);
                }
            });

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ]);
    }

    protected function writeExportMetadataRows($handle, string $dataset, $user, string $bankScope, Campaign $campaign): void
    {
        fputcsv($handle, ['Export Type', $dataset]);
        fputcsv($handle, ['Campaign ID', $campaign->id]);
        fputcsv($handle, ['Campaign Name', $campaign->name]);
        fputcsv($handle, ['Exported By', $user?->name]);
        fputcsv($handle, ['Exported At', now()->toDateTimeString()]);
        fputcsv($handle, ['Bank Scope', $bankScope]);
        fputcsv($handle, ['User Role', $user?->role]);
        fputcsv($handle, ['Portfolio Scoped', $user?->isPortfolioScoped() ? 'Yes' : 'No']);
        fputcsv($handle, []);
    }

    public function smsRecipients(Campaign $campaign, $smsId)
    {
        $this->authorizeView($campaign);

        $user = Auth::user();

        $message = $campaign->smsMessages()
            ->where('id', $smsId)
            ->firstOrFail();

        $recipientsQuery = CampaignSmsRecipient::with('client.assignedTo:id,name')
            ->where('campaign_sms_message_id', $message->id);

        if ($user?->isPortfolioScoped()) {
            $recipientsQuery->whereHas('client', function ($q) use ($user) {
                $q->where('assigned_to_id', $user->id);
            });
        }

        $recipients = $recipientsQuery
            ->get()
            ->map(function ($r) {
                return [
                    'id'           => $r->id,
                    'client_name'  => $r->client?->name,
                    'email'        => $r->client?->email,
                    'phone'        => $r->phone ?: $r->client?->phone,
                    'bank_name'    => $r->client?->bank_name,
                    'assigned_to_name' => $r->client?->assignedTo?->name,
                    'status'       => $r->status,
                    'delivered_at' => optional($r->delivered_at)->toDateTimeString(),
                ];
            });

        $summary = [
            'total'     => $recipients->count(),
            'delivered' => $recipients->where('status', 'Delivered')->count(),
            'failed'    => $recipients->where('status', 'Failed')->count(),
            'pending'   => $recipients->where('status', 'Pending')->count(),
        ];

        return response()->json([
            'message'   => [
                'id'      => $message->id,
                'sent_at' => optional($message->sent_at)->toDateTimeString(),
            ],
            'summary'   => $summary,
            'recipients'=> $recipients,
        ]);
    }

    /**
     * Recipients for one WhatsApp send row (for mini dashboard modal).
     */
    public function whatsappRecipients(Campaign $campaign, $messageId)
    {
        $this->authorizeView($campaign);

        $user = Auth::user();

        /** @var \App\Models\CampaignWhatsappMessage $message */
        $message = $campaign->whatsappMessages()
            ->where('id', $messageId)
            ->firstOrFail();

        // Load recipients + client + client departments
        $recipientModelsQuery = CampaignWhatsappRecipient::with(['client.departments', 'client.assignedTo:id,name'])
            ->where('whatsapp_message_id', $message->id);

        if ($user?->isPortfolioScoped()) {
            $recipientModelsQuery->whereHas('client', function ($q) use ($user) {
                $q->where('assigned_to_id', $user->id);
            });
        }

        $recipientModels = $recipientModelsQuery->get();

        // Map to shape expected by the Vue modal
        $recipients = $recipientModels->map(function ($r) use ($message, $campaign) {
            $client          = $r->client;
            $departments     = $client && $client->relationLoaded('departments')
                ? $client->departments
                : collect();
            $replyMeta = $this->extractWhatsappReplyMeta($r);

            $resolvedVars = app(\App\Services\WhatsAppBatchService::class)->resolveTemplateVariableValues(
                $message->template_variables ?? [],
                $client,
                $campaign
            );
            $previewBody = $message->preview_body ?? '';
            foreach ($resolvedVars as $key => $val) {
                $num = preg_replace('/[^0-9]/', '', $key);
                if ($num) {
                    $previewBody = str_replace('{{' . $num . '}}', $val, $previewBody);
                }
            }

            $isEcosystemWarning = in_array((string)$r->error_code, ['131049', '131026'], true)
                || str_contains(strtolower((string)$r->error_message), 'maintain healthy ecosystem engagement');

            $status = ($isEcosystemWarning || strcasecmp($r->status, 'Delivered (Ecosystem Warning)') === 0) ? 'Delivered' : $r->status;

            return [
                'id'               => $r->id,
                'client_id'        => $client?->id,
                'resolved_preview_body' => $previewBody,
                'client_name'      => $client?->name,
                'email'            => $client?->email,
                'phone'            => $r->phone ?: ($client?->phone ?? null),
                'bank_name'        => $client?->bank_name,
                'assigned_to_name' => $client?->assignedTo?->name,
                'department_names' => $departments->pluck('name')->join(', ') ?: null,
                'status'           => $status,
                'attempts_count'   => $r->attempts_count ?? 0,
                'queued_at'        => optional($r->queued_at)->toDateTimeString(),
                'processing_started_at' => optional($r->processing_started_at)->toDateTimeString(),
                'last_attempted_at' => optional($r->last_attempted_at)->toDateTimeString(),
                'error_code'       => $r->error_code,
                'error_message'    => $r->error_message,
                'delivered_at'     => optional($r->delivered_at ?: ($isEcosystemWarning ? $r->updated_at : null))->toDateTimeString(),
                'last_response'    => $r->last_response,
                'last_response_at' => optional($r->last_response_at)->toDateTimeString(),
                'reply_type'       => $replyMeta['type'],
                'reply_label'      => $replyMeta['label'],
                'reply_key'        => $replyMeta['key'],
                'reply_source'     => $replyMeta['source'],
                'current_flow_step_id' => $r->current_flow_step_id,
            ];
        });

        // Summary for the stat cards (total / delivered / failed / pending)
        $totalRecipients = $message->total ?? $recipients->count();

        $delivered = $recipients->filter(function ($r) {
            return strcasecmp($r['status'], 'Delivered') === 0;
        })->count();

        $failed = $recipients->filter(function ($r) {
            return strcasecmp($r['status'], 'Failed') === 0;
        })->count();

        $pending = $recipients->filter(function ($r) {
            return in_array(strtolower($r['status']), ['pending', 'queued', 'processing', 'paused', 'scheduled', 'sent'], true);
        })->count();
        $queued = $recipients->where('status', 'Queued')->count();
        $processing = $recipients->where('status', 'Processing')->count();
        $paused = $recipients->where('status', 'Paused')->count();

        $summary = [
            'total'      => $totalRecipients,
            'delivered'  => $delivered,
            'failed'     => $failed,
            'pending'    => $pending,
            'queued'     => $queued,
            'processing' => $processing,
            'paused'     => $paused,
            'replies'    => $recipients->filter(fn ($r) => !empty($r['last_response']))->count(),
            'yes_count'  => $recipients->filter(fn ($r) => strcasecmp((string)$r['reply_key'], 'yes') === 0 || strcasecmp((string)$r['last_response'], 'yes') === 0)->count(),
            'no_count'   => $recipients->filter(fn ($r) => strcasecmp((string)$r['reply_key'], 'no') === 0 || strcasecmp((string)$r['last_response'], 'no') === 0)->count(),
            'delivery_rate' => $totalRecipients > 0 ? round(($delivered / $totalRecipients) * 100) : 0,
        ];

        // Agents block (for now empty – fill from your own aggregation if you track agents)
        $agents = []; // e.g. [['agent_id' => 1, 'agent_name' => 'John', 'count' => 5], ...]

        // Meta overrides for the modal header
        $status = $message->status
            ?? ($message->sent_at ? 'Sent' : 'Draft');

        $meta = [
            'id'            => $message->id,
            'mode'          => $message->mode ?? 'template',
            'flow_name'     => $message->flow_name,
            'template_name' => $message->template_name ?? $message->name,
            'subject'       => null, // WhatsApp has no subject, keep for consistency with Email
            'status'        => $status,
            'can_send'      => !$message->sent_at, // enable "Send Now" if not yet sent
            'reply_number'  => $message->provider_display_phone_number,
            'scheduled_at'  => optional($message->scheduled_at)->toDateTimeString(),
            'enable_live_chat' => (bool) $message->enable_live_chat,
            'enable_email_notification' => (bool) ($message->enable_email_notification ?? true),
            'track_responses'  => (bool) $message->track_responses,
            'template_variables' => $message->template_variables ?? [],
            'queued_at'     => optional($message->queued_at)->toDateTimeString(),
            'processing_started_at' => optional($message->processing_started_at)->toDateTimeString(),
            'completed_at'  => optional($message->completed_at)->toDateTimeString(),
            'paused_at'     => optional($message->paused_at)->toDateTimeString(),
            'pause_reason'  => $message->pause_reason,
            'messages_per_second' => (int) ($message->messages_per_second ?: $this->batchService->enforcedMessagesPerSecond()),
            'can_pause'     => in_array($status, ['Queued', 'Processing'], true),
            'can_resume'    => $status === 'Paused',
            'can_retry_failed' => $failed > 0,
        ];

        return response()->json([
            // kept for backward compatibility if you still use it somewhere
            'message'   => [
                'id'            => $message->id,
                'template_name' => $message->template_name,
                'name'          => $message->name,
                'sent_at'       => optional($message->sent_at)->toDateTimeString(),
            ],
            'summary'    => $summary,
            'recipients' => $recipients,
            'agents'     => $agents,
            'meta'       => $meta,
        ]);
    }



    /**
     * Queue a WhatsApp batch for this campaign.
     *
     * POST /api/campaigns/{campaign}/whatsapp-messages
     *
     * Payload:
     *  - template_id: string (Meta template name)
     *  - clients_mode: 'all' | 'selected'
     *  - client_ids: [] (required when clients_mode = 'selected')
     *  - track_responses: bool (optional)
     *  - enable_live_chat: bool (optional)
     */
    public function sendWhatsappMessage(Request $request, Campaign $campaign)
    {
        $this->authorizeManageCampaign($campaign);

        $data = $request->validate([
            'mode'             => ['required', 'in:template,flow'],
            'template_id'      => ['nullable', 'string'],
            'flow_id'          => ['nullable', 'integer', 'exists:whatsapp_flows,id'],
            'template_variables' => ['sometimes', 'array'],
            'clients_mode'     => ['required', 'in:all,selected,unsent'],
            'client_ids'       => ['array'],
            'client_ids.*'     => ['integer', 'exists:clients,id'],
            'track_responses'  => ['sometimes', 'boolean'],
            'enable_live_chat' => ['sometimes', 'boolean'],
            'enable_email_notification' => ['sometimes', 'boolean'],
            'send_now'         => ['sometimes', 'boolean'],
            'auto_replies'     => ['sometimes', 'array'],
            'auto_replies.*.trigger_keyword' => ['required', 'string'],
            'auto_replies.*.template_sid' => ['required', 'string'],
            'auto_replies.*.template_name' => ['nullable', 'string'],
            'auto_replies.*.template_variables' => ['nullable', 'array'],
        ]);

        $sendNow = $data['send_now'] ?? true;
        if ($sendNow) {
            $this->enforceMetaPermissionHealthForProduction('WhatsApp batch sending');
        }

        if ($data['clients_mode'] === 'selected' && empty($data['client_ids'])) {
            return response()->json([
                'message' => 'client_ids is required when clients_mode = selected',
            ], 422);
        }

        $mode = $data['mode'] ?? 'template';
        if ($mode === 'template' && empty($data['template_id'])) {
            return response()->json(['message' => 'template_id is required for template mode'], 422);
        }
        if ($mode === 'flow' && empty($data['flow_id'])) {
            return response()->json(['message' => 'flow_id is required for flow mode'], 422);
        }

        // Determine which clients this batch is for
        $clientsQuery = $campaign->clients(); // many-to-many relation

        if ($data['clients_mode'] === 'selected') {
            $ids = $data['client_ids'] ?? [];
            $clientsQuery->whereIn('clients.id', $ids);
        } elseif ($data['clients_mode'] === 'unsent') {
            $clientsQuery->where(function ($q) {
                $q->whereNull('campaign_clients.whatsapp_status')
                  ->orWhereIn('campaign_clients.whatsapp_status', ['Pending', 'Unsent', '']);
            });
        }

        $clients = $clientsQuery->get([
            'clients.id',
            'clients.name',
            'clients.phone',
            'clients.email',
            'clients.id_number',
            'clients.account_number',
            'clients.bank_name',
            'clients.branch_code',
            'clients.whatsapp_opted_out_at',
            'clients.whatsapp_opt_out_reason',
            'clients.whatsapp_contact_basis',
            'clients.whatsapp_opted_in_at',
        ]);

        if ($clients->isEmpty()) {
            return response()->json([
                'message' => 'No clients found for this batch.',
            ], 422);
        }

        $clients = $clients->filter(fn ($client) => $this->canSendWhatsappToClient($client))->values();

        if ($clients->isEmpty()) {
            return response()->json([
                'message' => 'All selected clients are blocked by WhatsApp compliance controls (opt-out or missing lawful basis).',
            ], 422);
        }

        $templateSid  = null;
        $friendlyName = null;
        $previewBody  = null;
        $flowId       = null;
        $flowName     = null;
        $flowDef      = null;
        $flowTemplateSid = null;
        $templateVariables = null;

        if ($mode === 'template') {
            $templateSid   = $data['template_id'];
            $template      = $this->whatsApp->getTemplateDetails($templateSid);
            $friendlyName  = $template['name'] ?? $templateSid;
            $previewBody   = collect($template['components'] ?? [])
                ->firstWhere('type', 'BODY')['text'] ?? null;
            $templateVariables = $this->normalizeTemplateVariables(
                $data['template_variables'] ?? [],
                $template['variables'] ?? []
            );
        } else {
            $flow = WhatsAppFlow::findOrFail($data['flow_id']);
            $flowId   = $flow->id;
            $flowName = $flow->name;
            $flowDef  = $flow->flow_definition;
            $flowTemplateSid = $flow->template_sid;
            $friendlyName = $flowName;
            $templateSid  = $flowTemplateSid;
            $previewBody  = $flowDef && isset($flowDef[0]['message']) ? $flowDef[0]['message'] : 'Flow start';
        }

        $senderContext = $this->resolveWhatsappSenderContext($campaign->whatsapp_from);

        // Create parent WhatsApp "batch" row via relationship
        $total = $clients->count();
        $now   = now();

        $isScheduled = !$sendNow && !empty($data['scheduled_at']);
        $status = $sendNow ? 'Queued' : ($isScheduled ? 'Scheduled' : 'Draft');
        
        $message = $campaign->whatsappMessages()->create([
            'created_by_user_id' => Auth::id(),
            'mode'              => $mode,
            'template_sid'      => $templateSid,
            'template_name'     => $friendlyName,
            'provider_phone_number_id' => $senderContext['phone_number_id'],
            'provider_display_phone_number' => $senderContext['display_phone_number'],
            'name'              => $friendlyName,
            'preview_body'      => $previewBody,
            'template_variables'=> $templateVariables,
            'whatsapp_flow_id'  => $flowId,
            'flow_name'         => $flowName,
            'flow_definition'   => $flowDef,
            'sent_at'           => $sendNow ? $now : null,
            'total'             => $total,
            'delivered'         => 0,
            'failed'            => 0,
            'pending'           => ($sendNow || $isScheduled) ? $total : 0,
            'status'            => $status,
            'scheduled_at'      => $isScheduled ? $data['scheduled_at'] : null,
            'queued_at'         => $sendNow ? $now : null,
            'processing_started_at' => null,
            'completed_at'      => null,
            'paused_at'         => null,
            'pause_reason'      => null,
            'last_processed_at' => null,
            'messages_per_second' => $this->batchService->enforcedMessagesPerSecond(),
            'track_responses'   => $data['track_responses']  ?? false,
            'enable_live_chat'  => $data['enable_live_chat'] ?? false,
            'enable_email_notification' => $data['enable_email_notification'] ?? true,
        ]);

        if ($sendNow) {
            $limitCheck = $this->dailyLimitService->validateSendAllowance(Auth::user(), $clients->count());
            if (!$limitCheck['allowed']) {
                $message->delete();
                return response()->json([
                    'message' => $limitCheck['message'],
                    'whatsapp_daily_limit' => $limitCheck['summary'],
                ], 422);
            }
        }

        if (isset($data['auto_replies'])) {
            foreach ($data['auto_replies'] as $reply) {
                $message->autoReplies()->create([
                    'trigger_keyword' => $reply['trigger_keyword'],
                    'template_sid' => $reply['template_sid'],
                    'template_name' => $reply['template_name'] ?? null,
                    'template_variables' => $reply['template_variables'] ?? null,
                ]);
            }
        }

        // Create recipients for this batch
        $rows = [];
        foreach ($clients as $client) {
            $rows[] = [
                'whatsapp_message_id' => $message->id,
                'client_id'                    => $client->id,
                'phone'                        => $this->resolveClientPhone($client),
                'provider_phone_number_id'     => $senderContext['phone_number_id'],
                'provider_display_phone_number'=> $senderContext['display_phone_number'],
                'status'                       => $status,
                'queued_at'                    => $sendNow ? $now : null,
                'processing_started_at'        => null,
                'last_attempted_at'            => null,
                'attempts_count'               => 0,
                'created_at'                   => $now,
                'updated_at'                   => $now,
            ];
        }

        CampaignWhatsappRecipient::insert($rows);

        if ($sendNow) {
            // Update pivot status for these clients (optional, but matches the rest of your code)
            CampaignClient::where('campaign_id', $campaign->id)
                ->whereIn('client_id', $clients->pluck('id'))
                ->update([
                    'whatsapp_status'   => 'Pending',
                    'whatsapp_sent_at'  => $now,
                    'updated_at'        => $now,
                ]);
        }
        
        $queuedCount = 0;
        if ($sendNow) {
            $queuedCount = $this->batchService->queueAllRecipients($message->fresh());
        }

       

        return response()->json([
            'message' => $sendNow
            ? 'WhatsApp batch queued successfully.'
            : 'WhatsApp batch saved successfully (not yet sent).',
            'batch'   => [
                'id'         => $message->id,
                'template'   => $friendlyName,
                'total'      => $total,
                'sent_at'    => $now->toDateTimeString(),
            ],
            'queued_count' => $queuedCount,
            'whatsapp_daily_limit' => $this->dailyLimitService->summaryFor(Auth::user()),
        ], 201);
    }


    /*
     |--------------------------------------------------------------------------
     | Simple authorization helpers
     |--------------------------------------------------------------------------
     */

    protected function authorizeManage(): void
    {
        $user = Auth::user();

        if (!$user || !$user->canViewCampaigns()) {
            abort(403, 'You are not allowed to manage campaigns.');
        }
    }

    protected function authorizeView(Campaign $campaign): void
    {
        $user = Auth::user();
        $userDeptIds = $user?->resolvedDepartmentIds() ?? [];

        if (!$user) {
            abort(401);
        }

        if (!$user->canViewCampaigns()) {
            abort(403, 'You are not allowed to access campaigns.');
        }

        // System admins can view all
        if ($user->canAccessAllBanks()) {
            return;
        }

        if (!empty($user->resolvedBankIds()) && !in_array((int) $campaign->bank_id, $user->resolvedBankIds(), true)) {
            abort(403, 'You do not have permission to act on this campaign.');
        }

        $campaign->loadMissing('departments');
        $campaignDeptIds = $campaign->departments->pluck('id')->all();

        // Global campaigns (no linked departments) remain visible to non-super-admin users.
        if (empty($campaignDeptIds)) {
            return;
        }

        if (empty(array_intersect($userDeptIds, $campaignDeptIds))) {
            abort(403, 'You are not allowed to view this campaign.');
        }

    }

    protected function authorizeManageCampaign(Campaign $campaign): void
    {
        $user = Auth::user();
        if (!$user || !$user->canEditCampaigns()) {
            abort(403, 'You are not allowed to edit campaigns.');
        }
        $this->authorizeView($campaign);
    }

    protected function resolveCampaignBankId($user, $requestedBankId): int
    {
        if (!$user) {
            abort(401);
        }

        if ($user->canAccessAllBanks()) {
            if (!$requestedBankId) {
                abort(422, 'A bank is required for this campaign.');
            }

            return (int) $requestedBankId;
        }

        $ids = $user->resolvedBankIds();
        if (empty($ids)) {
            abort(422, 'Your user account is not assigned to a bank.');
        }

        if ($requestedBankId && in_array((int) $requestedBankId, $ids, true)) {
            return (int) $requestedBankId;
        }

        return $ids[0];
    }

    protected function resolveWhatsappSenderContext(?string $overrideFrom = null): array
    {
        if (method_exists($this->whatsApp, 'resolveSenderContext')) {
            return $this->whatsApp->resolveSenderContext($overrideFrom);
        }

        $senders = $this->whatsApp->listWhatsappSenders();
        $sender = collect($senders)->firstWhere('number', $overrideFrom) ?? collect($senders)->firstWhere('default', true) ?? ($senders[0] ?? []);

        return [
            'phone_number_id' => $sender['phone_number_id'] ?? null,
            'display_phone_number' => $sender['number'] ?? $overrideFrom,
        ];
    }

    protected function mapTwilioStatus(?string $status): string
    {
        return match (strtolower((string) $status)) {
            'delivered', 'read' => 'Delivered',
            'failed', 'undelivered' => 'Failed',
            default => 'Pending',
        };
    }

    protected function normalizePhone(?string $raw): ?string
    {
        return $this->batchService->normalizePhone($raw);
    }

    protected function resolveClientPhone($client): ?string
    {
        return $this->batchService->resolveClientPhone($client);
    }

    protected function isWhatsappSuppressedClient($client): bool
    {
        return $this->batchService->isWhatsappSuppressedClient($client);
    }

    protected function clientHasWhatsappLawfulBasis($client): bool
    {
        return $this->batchService->clientHasWhatsappLawfulBasis($client);
    }

    protected function canSendWhatsappToClient($client): bool
    {
        return $this->batchService->canSendWhatsappToClient($client);
    }

    protected function whatsappComplianceBlockedStatus($client): string
    {
        return $this->batchService->whatsappComplianceBlockedStatus($client);
    }

    protected function normalizeTemplateVariables(array $input, array $expectedVariables): ?array
    {
        if (empty($expectedVariables)) {
            return null;
        }

        $normalized = [];
        $missing = []; \Log::info("DEBUG TEMPLATE VARIABLES", ["input" => $input, "expected" => $expectedVariables]);

        foreach ($this->sortedTemplateVariableKeys($expectedVariables) as $key) {
            $entry = $input[$key] ?? null;
            $source = is_array($entry) ? trim((string) ($entry['source'] ?? '')) : trim((string) $entry);
            $customValue = is_array($entry) ? trim((string) ($entry['custom_value'] ?? '')) : '';

            if ($source === '') {
                $missing[] = '{{' . $key . '}}';
                continue;
            }

            if ($source === 'custom' && $customValue === '') {
                $missing[] = '{{' . $key . '}}';
                continue;
            }

            $normalized[(string) $key] = [
                'source' => $source,
                'custom_value' => $source === 'custom' ? $customValue : null,
            ];
        }

        if (!empty($missing)) {
            throw ValidationException::withMessages([
                'template_variables' => 'Please map all template variables before saving this WhatsApp batch: ' . implode(', ', $missing),
            ]);
        }

        return $normalized;
    }

    protected function sortedTemplateVariableKeys(array $variables): array
    {
        $keys = array_map('strval', array_keys($variables));
        usort($keys, fn (string $a, string $b) => (int) $a <=> (int) $b);
        return $keys;
    }

    protected function resolveTemplateVariableValues(array $templateVariables, $client, Campaign $campaign): array
    {
        return $this->batchService->resolveTemplateVariableValues($templateVariables, $client, $campaign);
    }

    protected function resolveTemplateVariableValue(?string $source, ?string $customValue, $client, Campaign $campaign): string
    {
        return match ($source) {
            'client.name' => (string) ($client?->name ?? ''),
            'client.phone' => (string) ($this->resolveClientPhone($client) ?? ''),
            'client.email' => (string) ($client?->email ?? ''),
            'client.id_number' => (string) ($client?->id_number ?? ''),
            'client.account_number' => (string) ($client?->account_number ?? ''),
            'client.bank_name' => (string) ($client?->bank_name ?? $campaign->bank?->name ?? ''),
            'client.branch_code' => (string) ($client?->branch_code ?? ''),
            'campaign.name' => (string) ($campaign->name ?? ''),
            'campaign.status' => (string) ($campaign->status ?? ''),
            'custom' => (string) ($customValue ?? ''),
            default => '',
        };
    }

    protected function refreshWhatsappMessageCounts(?CampaignWhatsappMessage $message): void
    {
        if (!$message) {
            return;
        }

        $this->batchService->syncMessageProgress($message);
    }

    protected function extractWhatsappReplyMeta(CampaignWhatsappRecipient $recipient): array
    {
        $payload = $recipient->provider_status_payload ?: $recipient->status_payload ?: [];
        $message = $this->extractInboundWhatsappPayloadMessage(is_array($payload) ? $payload : []);

        $textBody = trim((string) data_get($message, 'text.body', ''));
        $buttonText = trim((string) data_get($message, 'button.text', ''));
        $buttonPayload = trim((string) data_get($message, 'button.payload', ''));
        $interactiveType = strtolower(trim((string) data_get($message, 'interactive.type', '')));
        $interactiveButtonTitle = trim((string) data_get($message, 'interactive.button_reply.title', ''));
        $interactiveButtonId = trim((string) data_get($message, 'interactive.button_reply.id', ''));
        $interactiveListTitle = trim((string) data_get($message, 'interactive.list_reply.title', ''));
        $interactiveListId = trim((string) data_get($message, 'interactive.list_reply.id', ''));
        $normalizedResponse = trim((string) ($recipient->last_response ?? ''));

        $keywords = array_values(array_filter([
            $interactiveButtonTitle,
            $interactiveButtonId,
            $buttonText,
            $buttonPayload,
            $interactiveListTitle,
            $interactiveListId,
            $textBody,
            $normalizedResponse,
        ], fn ($value) => trim((string) $value) !== ''));

        $replyType = null;
        $replyLabel = null;
        $replyKey = null;
        $replySource = null;

        if ($interactiveType === 'button_reply') {
            $replyType = 'Quick Reply';
            $replyLabel = $interactiveButtonTitle ?: $normalizedResponse;
            $replyKey = $interactiveButtonId ?: null;
            $replySource = 'interactive.button_reply';
        } elseif ($interactiveType === 'list_reply') {
            $replyType = 'List Reply';
            $replyLabel = $interactiveListTitle ?: $normalizedResponse;
            $replyKey = $interactiveListId ?: null;
            $replySource = 'interactive.list_reply';
        } elseif ($buttonText !== '' || $buttonPayload !== '') {
            $replyType = 'Quick Reply';
            $replyLabel = $buttonText ?: $normalizedResponse;
            $replyKey = $buttonPayload ?: null;
            $replySource = 'button';
        } elseif ($textBody !== '' || $normalizedResponse !== '') {
            $replyType = 'Text Reply';
            $replyLabel = $textBody ?: $normalizedResponse;
            $replySource = 'text';
        }

        if ($this->isOptOutMessage($normalizedResponse !== '' ? $normalizedResponse : ($replyLabel ?? ''), $keywords)) {
            $replyType = 'Opt Out';
            $replyLabel = $replyLabel ?: ($normalizedResponse !== '' ? $normalizedResponse : 'Opt Out');
            $replySource = $replySource ?: 'opt_out';
        } elseif (in_array(strtolower($normalizedResponse), ['yes', 'no'], true) && $replyType === 'Text Reply') {
            $replyType = 'Yes/No Reply';
        }

        return [
            'type' => $replyType,
            'label' => $replyLabel ?: ($normalizedResponse !== '' ? $normalizedResponse : null),
            'key' => $replyKey ?: null,
            'source' => $replySource,
        ];
    }

    protected function extractInboundWhatsappPayloadMessage(array $payload): ?array
    {
        foreach (($payload['entry'] ?? []) as $entry) {
            foreach (($entry['changes'] ?? []) as $change) {
                $value = $change['value'] ?? [];
                foreach (($value['messages'] ?? []) as $message) {
                    if (is_array($message)) {
                        return $message;
                    }
                }
            }
        }

        return null;
    }

    protected function isOptOutMessage(string $body, array $keywords = []): bool
    {
        $phrases = array_filter(array_map(
            fn ($value) => strtolower(trim((string) $value)),
            array_merge([$body], $keywords)
        ));

        $optOutTriggers = [
            'stop',
            'unsubscribe',
            'opt out',
            'optout',
            'cancel',
            'end',
            'quit',
        ];

        foreach ($phrases as $phrase) {
            if (in_array($phrase, $optOutTriggers, true)) {
                return true;
            }
        }

        return false;
    }

     /**
     * List WhatsApp templates for dropdowns.
     *
     * GET /api/whatsapp-templates?approved=1
     */
    public function listWhatsappTemplates(Request $request): JsonResponse
    {
        $onlyApproved = filter_var($request->query('approved', '1'), FILTER_VALIDATE_BOOLEAN);

        $templates = $this->whatsApp->getWhatsAppTemplates($onlyApproved);

        // Map to the shape used in CampaignShow.vue:
        // id, name, language, category, body_preview, variables, whatsapp
        $data = array_map(function (array $t) {
            $whatsapp = $t['whatsapp'] ?? [];
            return [
                'id'           => $t['sid'],
                'name'         => $t['friendly_name'] ?? $t['sid'],
                'language'     => $t['language'] ?? null,
                'category'     => $whatsapp['category'] ?? null,
                'body_preview' => $t['preview'] ?? null,
                'variables'    => $t['variables'] ?? [],
                'whatsapp'     => $whatsapp,
                'media_urls'   => $t['media'] ?? [],
            ];
        }, $templates);

        return response()->json($data);
    }

    /**
     * Full template + approval details for preview page.
     *
     * GET /api/whatsapp-templates/{id}
     */
    public function showWhatsappTemplate(string $id): JsonResponse
    {
        $details   = $this->whatsApp->getTemplateDetails($id);
        $approvals = $this->whatsApp->getTemplateApprovalStatus($id);

        return response()->json([
            'template'  => $details,
            'approvals' => $approvals,
        ]);
    }
}
