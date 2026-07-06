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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CampaignController extends Controller
{
    use HasAuditLogging, GuardsSensitiveExports, EnforcesMetaPermissionHealth;

    protected WhatsAppServiceInterface $whatsApp;

    public function __construct(WhatsAppServiceInterface $whatsApp)
    {
        $this->whatsApp = $whatsApp;
    }
    /**
     * List campaigns (department + role scoped).
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $userDeptIds = $user?->resolvedDepartmentIds() ?? [];

        $query = Campaign::query()
            ->with(['departments', 'bank'])
            ->withCount('clients as total_recipients')
            ->orderByDesc('created_at');

        if ($user && !$user->canAccessAllBanks() && $user->resolvedBankId()) {
            $query->where('bank_id', $user->resolvedBankId());
        }

        // Department scoping (same logic as before)
        if ($user && !$user->canManageSystemSettings()) {
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
            ->with('departments')
            ->select('clients.*');

        if ($campaign->bank_id) {
            $query->where('clients.bank_id', $campaign->bank_id);
        }

        if ($user = Auth::user()) {
            if ($user->isPortfolioScoped()) {
                $query->where('clients.assigned_to_id', $user->id);
            }
        }

        if (!empty($deptIds)) {
            $query->whereHas('departments', function ($q) use ($deptIds) {
                $q->whereIn('departments.id', $deptIds);
            });
        }

        // Exclude clients already attached to this campaign
        $alreadyAttachedIds = $campaign->clients()->pluck('clients.id')->all();
        if (!empty($alreadyAttachedIds)) {
            $query->whereNotIn('clients.id', $alreadyAttachedIds);
        }

        // Get the results
        $clients = $query
            ->orderBy('clients.name')
            ->take(500)
            ->get()
            ->map(function ($client) {
                return [
                    'id' => $client->id,
                    'name' => $client->name,
                    'email' => $client->email,
                    'phone' => $this->normalizePhone($client->phone),
                    'departments' => $client->departments->map(function ($dept) {
                        return [
                            'id' => $dept->id,
                            'name' => $dept->name
                        ];
                    }),
                    'department_names' => $client->departments->pluck('name')->join(', '),
                ];
            });

        return response()->json($clients);
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
        ]);

        $addAll    = (bool) $validated['add_all'];
        $clientIds = $validated['client_ids'] ?? [];

        // Ensure campaign departments are loaded
        $campaign->loadMissing('departments');

        $deptIds = $campaign->departments->pluck('id')->all();

        // Build base allowed clients query (department-scoped)
        $allowedClientsQuery = Client::query();

        if ($campaign->bank_id) {
            $allowedClientsQuery->where('bank_id', $campaign->bank_id);
        }

        if ($user = Auth::user()) {
            if ($user->isPortfolioScoped()) {
                $allowedClientsQuery->where('assigned_to_id', $user->id);
            }
        }

        if (!empty($deptIds)) {
            $allowedClientsQuery->whereHas('departments', function ($q) use ($deptIds) {
                $q->whereIn('departments.id', $deptIds);
            });
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
            // Add only selected client_ids, but intersect with allowed (dept-scoped) ones
            if (empty($clientIds)) {
                return response()->json([
                    'message' => 'client_ids is required when add_all is false.',
                ], 422);
            }

            $clientIdsToAttach = $allowedClientsQuery
                ->whereIn('id', $clientIds)
                ->pluck('id')
                ->all();
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

    /**
     * Clients attached to this campaign.
     */
    public function clients(Campaign $campaign)
    {
        $this->authorizeView($campaign);

        return $campaign->clients()
            ->with(['departments', 'assignedTo:id,name'])
            ->when(Auth::user()?->isPortfolioScoped(), function ($q) {
                $q->where('clients.assigned_to_id', Auth::id());
            })
            ->paginate(50)
            ->through(function ($client) use ($campaign) {
                $pivot = CampaignClient::where('campaign_id', $campaign->id)
                    ->where('client_id', $client->id)
                    ->first();
                
                return [
                    'id' => $client->id,
                    'name' => $client->name,
                    'email' => $client->email,
                    'phone' => $this->normalizePhone($client->phone),
                    'bank_name' => $client->bank_name,
                    'assigned_to_name' => $client->assignedTo?->name,
                    'departments' => $client->departments->map(function ($dept) {
                        return ['id' => $dept->id, 'name' => $dept->name];
                    }),
                    'whatsapp_status' => $pivot->whatsapp_status ?? 'Pending',
                    'whatsapp_sent_at' => $pivot->whatsapp_sent_at,
                    'email_status' => $pivot->email_status ?? 'Pending',
                    'email_sent_at' => $pivot->email_sent_at,
                    'sms_status' => $pivot->sms_status ?? 'Pending',
                    'sms_sent_at' => $pivot->sms_sent_at,
                    'created_at' => $pivot->created_at ?? $client->created_at,
                ];
            });
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
                        $this->normalizePhone($client->phone),
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
        $this->authorizeManage();

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
                $firstNumber = $firstDept?->whatsapp_numbers[0] ?? null;
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
                $firstNumber = $firstDept?->whatsapp_numbers[0] ?? null;
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
        $this->authorizeManageCampaign($campaign);

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

        // Example structure (pseudo-code):
        //
        // dispatch(new SendCampaignJob(
        //     campaign: $campaign,
        //     channels: $campaign->channels,
        // ));
        //
        // Inside the job you would:
        //  - Resolve campaign clients (department-scoped)
        //  - For WhatsApp: call the configured WhatsApp provider with an approved template
        //  - For Email: push to your mailer
        //  - For SMS: call ZoomConnect API
        //  - Store each send result into campaign_* tables for reporting
        //  - Update campaign stats + audit trail

        // For now we just return a simple JSON stub
        return response()->json([
            'message'  => 'Send job queued (stub). Implement SendCampaignJob with WhatsApp/Email/ZoomConnect.',
            'campaign' => $campaign->id,
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
                'pending'   => (clone $whatsRecipientQuery)->whereIn(\DB::raw('LOWER(status)'), ['pending', 'queued', 'scheduled'])->count(),
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

            $totalClients = $campaign->clients->count();

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
                'sent_at',
                'total',
                'delivered',
                'failed',
                'pending',
                'enable_live_chat',
                'created_at',
            ]);

        $assignedClientIds = null;
        if ($user?->isPortfolioScoped()) {
            $assignedClientIds = $campaign->clients()
                ->where('clients.assigned_to_id', $user->id)
                ->pluck('clients.id');
        }

        $mapped = $messages->map(function ($m) use ($assignedClientIds) {
            $total = $m->total;
            $delivered = $m->delivered;
            $failed = $m->failed;
            $pending = $m->pending;
            $repliesCount = $m->replies_count ?? 0;
            $yesResponsesCount = $m->yes_responses_count ?? 0;
            $noResponsesCount = $m->no_responses_count ?? 0;

            if ($assignedClientIds !== null) {
                $recipientQuery = CampaignWhatsappRecipient::query()
                    ->where('whatsapp_message_id', $m->id)
                    ->whereIn('client_id', $assignedClientIds);

                $total = (clone $recipientQuery)->count();
                $delivered = (clone $recipientQuery)->whereRaw('LOWER(status) = ?', ['delivered'])->count();
                $failed = (clone $recipientQuery)->whereRaw('LOWER(status) = ?', ['failed'])->count();
                $pending = (clone $recipientQuery)->whereRaw("LOWER(status) in ('pending','queued','scheduled')")->count();
                $repliesCount = (clone $recipientQuery)->whereNotNull('last_response')->count();
                $yesResponsesCount = (clone $recipientQuery)->whereRaw('LOWER(last_response) = ?', ['yes'])->count();
                $noResponsesCount = (clone $recipientQuery)->whereRaw('LOWER(last_response) = ?', ['no'])->count();
            }

            $status = 'Draft';
            if ($m->sent_at) {
                if ($pending > 0) {
                    $status = 'Pending';
                } elseif ($failed > 0 && $delivered === 0) {
                    $status = 'Failed';
                } elseif ($delivered > 0 && $pending === 0) {
                    $status = 'Delivered';
                } else {
                    $status = 'Sent';
                }
            }

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
                'sent_at'       => optional($m->sent_at)->toDateTimeString(),
                'total'         => $total,
                'delivered'     => $delivered,
                'failed'        => $failed,
                'pending'       => $pending,
                'created_at'    => optional($m->created_at)->toDateTimeString(),
                'enable_live_chat' => (bool) $m->enable_live_chat,
                'yes_responses_count' => $yesResponsesCount,
                'no_responses_count' => $noResponsesCount,
                'replies_count' => $repliesCount,
                'status'        => $status,
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
            'clients_mode'     => ['required', 'in:all,selected'],
            'client_ids'       => ['array'],
            'client_ids.*'     => ['integer', 'exists:clients,id'],
            'send_now'         => ['sometimes', 'boolean'],
            'enable_live_chat' => ['sometimes', 'boolean'],
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
        }

        $clients = $clientsQuery->get([
            'clients.id',
            'clients.name',
            'clients.phone',
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

        // Refresh template/flow info
        $templateSid  = null;
        $friendlyName = null;
        $previewBody  = null;
        $flowId       = null;
        $flowName     = null;
        $flowDef      = null;

        if ($mode === 'template') {
            $templateSid  = $data['template_id'];
            $template     = $this->whatsApp->getTemplateDetails($templateSid);
            $friendlyName = $template['name'] ?? $templateSid;
            $previewBody  = collect($template['components'] ?? [])
                ->firstWhere('type', 'BODY')['text'] ?? null;
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
                'phone'               => $this->normalizePhone($client->phone),
                'provider_phone_number_id' => $senderContext['phone_number_id'],
                'provider_display_phone_number' => $senderContext['display_phone_number'],
                'status'              => $sendNow ? 'pending' : 'draft',
                'created_at'          => $now,
                'updated_at'          => $now,
            ];
        }
        CampaignWhatsappRecipient::insert($rows);

        // Update message meta
        $message->update([
            'mode'             => $mode,
            'template_sid'     => $templateSid,
            'template_name'    => $friendlyName,
            'provider_phone_number_id' => $senderContext['phone_number_id'],
            'provider_display_phone_number' => $senderContext['display_phone_number'],
            'name'             => $friendlyName,
            'preview_body'     => $previewBody,
            'whatsapp_flow_id' => $flowId,
            'flow_name'        => $flowName,
            'flow_definition'  => $flowDef,
            'sent_at'          => $sendNow ? $now : null,
            'total'            => $total,
            'delivered'        => 0,
            'failed'           => 0,
            'pending'          => $sendNow ? $total : 0,
            'enable_live_chat' => $data['enable_live_chat'] ?? $message->enable_live_chat,
        ]);

        if ($sendNow && $templateSid) {
            // Mark campaign clients as pending
            CampaignClient::where('campaign_id', $campaign->id)
                ->whereIn('client_id', $clients->pluck('id'))
                ->update([
                    'whatsapp_status'  => 'Pending',
                    'whatsapp_sent_at' => $now,
                    'updated_at'       => $now,
                ]);

            // Send messages
            foreach ($clients as $client) {
                if (!$client->phone) {
                    continue;
                }
                if (!$this->canSendWhatsappToClient($client)) {
                    CampaignWhatsappRecipient::where('whatsapp_message_id', $message->id)
                        ->where('client_id', $client->id)
                        ->update(['status' => $this->whatsappComplianceBlockedStatus($client)]);
                    continue;
                }
                try {
                    $subject = $client->name ?? '';
                    $bodyVar = $mode === 'flow'
                        ? ($flowDef[0]['message'] ?? '')
                        : '';
                    $twResponse = $this->whatsApp->sendTemplateFromSubjectMessage(
                        $client->phone,
                        $templateSid,
                        $subject,
                        $bodyVar,
                        $campaign->whatsapp_from
                    );
                    $mappedStatus = $this->mapTwilioStatus($twResponse['status'] ?? 'queued');

                    CampaignWhatsappRecipient::where('whatsapp_message_id', $message->id)
                        ->where('client_id', $client->id)
                        ->update([
                            'message_sid'  => $twResponse['sid'] ?? null,
                            'provider_message_id' => $twResponse['message_id'] ?? ($twResponse['sid'] ?? null),
                            'provider_phone_number_id' => $twResponse['phone_number_id'] ?? $senderContext['phone_number_id'],
                            'provider_display_phone_number' => $twResponse['display_phone_number'] ?? $senderContext['display_phone_number'],
                            'status'       => $mappedStatus,
                            'delivered_at' => $mappedStatus === 'Delivered' ? now() : null,
                        ]);
                } catch (\Throwable $e) {
                    \Log::error('Failed to send WhatsApp (update draft)', [
                        'campaign_id' => $campaign->id,
                        'client_id'   => $client->id,
                        'error'       => $e->getMessage(),
                    ]);
                }
            }

            $this->refreshWhatsappMessageCounts($message);
        }

        return response()->json([
            'message' => 'Batch ' . ($sendNow ? 'sent' : 'updated') . ' successfully.',
            'id'      => $message->id,
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

        $now = now();

        // Update recipient statuses to pending
        CampaignWhatsappRecipient::where('whatsapp_message_id', $message->id)
            ->update([
                'status' => 'pending',
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

        // Send via the configured WhatsApp provider
        if ($message->template_sid) {
            Log::info('Campaign draft WhatsApp send started', [
                'campaign_id' => $campaign->id,
                'message_id' => $message->id,
                'template_sid' => $message->template_sid,
                'mode' => $message->mode,
                'recipient_count' => $recipients->count(),
                'campaign_whatsapp_from' => $campaign->whatsapp_from,
            ]);

            foreach ($recipients as $recipient) {
                $client = $recipient->client;
                $phone  = $recipient->phone ?: $client?->phone;
                if (!$phone) {
                    Log::warning('Campaign draft WhatsApp send skipped: no phone', [
                        'campaign_id' => $campaign->id,
                        'message_id' => $message->id,
                        'client_id' => $client?->id,
                        'recipient_id' => $recipient->id,
                    ]);
                    continue;
                }
                if ($client && !$this->canSendWhatsappToClient($client)) {
                    Log::info('Campaign draft WhatsApp send skipped: compliance blocked client', [
                        'campaign_id' => $campaign->id,
                        'message_id' => $message->id,
                        'client_id' => $client->id,
                        'recipient_id' => $recipient->id,
                        'block_status' => $this->whatsappComplianceBlockedStatus($client),
                    ]);
                    $recipient->status = $this->whatsappComplianceBlockedStatus($client);
                    $recipient->save();
                    continue;
                }
                try {
                    Log::info('Campaign draft WhatsApp recipient send attempt', [
                        'campaign_id' => $campaign->id,
                        'message_id' => $message->id,
                        'client_id' => $client?->id,
                        'recipient_id' => $recipient->id,
                        'phone' => $phone,
                        'campaign_whatsapp_from' => $campaign->whatsapp_from,
                    ]);

                    $subject = $client?->name ?? '';
                    $bodyVar = $message->mode === 'flow'
                        ? ($message->flow_definition[0]['message'] ?? '')
                        : '';
                    $twResponse = $this->whatsApp->sendTemplateFromSubjectMessage(
                        $phone,
                        $message->template_sid,
                        $subject,
                        $bodyVar,
                        $campaign->whatsapp_from
                    );

                    $mappedStatus = $this->mapTwilioStatus($twResponse['status'] ?? 'queued');
                    $recipient->message_sid = $twResponse['sid'] ?? $recipient->message_sid;
                    $recipient->provider_message_id = $twResponse['message_id'] ?? ($twResponse['sid'] ?? $recipient->provider_message_id);
                    $recipient->provider_phone_number_id = $twResponse['phone_number_id'] ?? $senderContext['phone_number_id'];
                    $recipient->provider_display_phone_number = $twResponse['display_phone_number'] ?? $senderContext['display_phone_number'];
                    $recipient->status = $mappedStatus;
                    if ($mappedStatus === 'Delivered') {
                        $recipient->delivered_at = $recipient->delivered_at ?? now();
                    }
                    $recipient->save();
                } catch (\Throwable $e) {
                    \Log::error('Failed to send WhatsApp draft', [
                        'campaign_id' => $campaign->id,
                        'client_id'   => $client?->id,
                        'message_id'  => $message->id,
                        'error'       => $e->getMessage(),
                    ]);
                }
            }
        }

        $this->refreshWhatsappMessageCounts($message);

        return response()->json(['message' => 'Batch sent successfully.']);
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
        $recipients = $recipientModels->map(function ($r) {
            $client          = $r->client;
            $departments     = $client && $client->relationLoaded('departments')
                ? $client->departments
                : collect();

            return [
                'id'               => $r->id,
                'client_id'        => $client?->id,
                'client_name'      => $client?->name,
                'email'            => $client?->email,
                'phone'            => $r->phone ?: ($client?->phone ?? null),
                'bank_name'        => $client?->bank_name,
                'assigned_to_name' => $client?->assignedTo?->name,
                'department_names' => $departments->pluck('name')->join(', ') ?: null,
                'status'           => $r->status,
                'delivered_at'     => optional($r->delivered_at)->toDateTimeString(),
                'last_response'    => $r->last_response,
                'last_response_at' => optional($r->last_response_at)->toDateTimeString(),
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
            return in_array(strtolower($r['status']), ['pending', 'queued', 'scheduled'], true);
        })->count();

        $summary = [
            'total'     => $totalRecipients,
            'delivered' => $delivered,
            'failed'    => $failed,
            'pending'   => $pending,
            'replies'   => $recipients->filter(fn ($r) => !empty($r['last_response']))->count(),
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
            'clients_mode'     => ['required', 'in:all,selected'],
            'client_ids'       => ['array'],
            'client_ids.*'     => ['integer', 'exists:clients,id'],
            'track_responses'  => ['sometimes', 'boolean'],
            'enable_live_chat' => ['sometimes', 'boolean'],
            'send_now'         => ['sometimes', 'boolean'],
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
        }

        $clients = $clientsQuery->get([
            'clients.id',
            'clients.name',
            'clients.phone',
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

        if ($mode === 'template') {
            $templateSid   = $data['template_id'];
            $template      = $this->whatsApp->getTemplateDetails($templateSid);
            $friendlyName  = $template['name'] ?? $templateSid;
            $previewBody   = collect($template['components'] ?? [])
                ->firstWhere('type', 'BODY')['text'] ?? null;
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

        $message = $campaign->whatsappMessages()->create([
            'mode'              => $mode,
            'template_sid'      => $templateSid,
            'template_name'     => $friendlyName,
            'provider_phone_number_id' => $senderContext['phone_number_id'],
            'provider_display_phone_number' => $senderContext['display_phone_number'],
            'name'              => $friendlyName,
            'preview_body'      => $previewBody,
            'whatsapp_flow_id'  => $flowId,
            'flow_name'         => $flowName,
            'flow_definition'   => $flowDef,
            'sent_at'           => $sendNow ? $now : null,
            'total'             => $total,
            'delivered'         => 0,
            'failed'            => 0,
            'pending'           => $sendNow ? $total : 0,
            'track_responses'   => $data['track_responses']  ?? false,
            'enable_live_chat'  => $data['enable_live_chat'] ?? false,
        ]);

        // Create recipients for this batch
        $rows = [];
        foreach ($clients as $client) {
            $rows[] = [
                'whatsapp_message_id' => $message->id,
                'client_id'                    => $client->id,
                'phone'                        => $this->normalizePhone($client->phone),
                'provider_phone_number_id'     => $senderContext['phone_number_id'],
                'provider_display_phone_number'=> $senderContext['display_phone_number'],
                'status'                       => $sendNow ? 'pending' : 'draft',
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
        
            // Only send immediately when requested
        if ($sendNow && $templateSid) {
            Log::info('Campaign WhatsApp batch send started', [
                'campaign_id' => $campaign->id,
                'message_id' => $message->id,
                'template_sid' => $templateSid,
                'mode' => $mode,
                'client_count' => $clients->count(),
                'campaign_whatsapp_from' => $campaign->whatsapp_from,
            ]);

            foreach ($clients as $client) {
                if (!$client->phone) {
                    Log::warning('Campaign WhatsApp send skipped: no phone', [
                        'campaign_id' => $campaign->id,
                        'message_id' => $message->id,
                        'client_id' => $client->id,
                    ]);
                    continue;
                }
                if (!$this->canSendWhatsappToClient($client)) {
                    Log::info('Campaign WhatsApp send skipped: compliance blocked client', [
                        'campaign_id' => $campaign->id,
                        'message_id' => $message->id,
                        'client_id' => $client->id,
                        'block_status' => $this->whatsappComplianceBlockedStatus($client),
                    ]);
                    CampaignWhatsappRecipient::where('whatsapp_message_id', $message->id)
                        ->where('client_id', $client->id)
                        ->update(['status' => $this->whatsappComplianceBlockedStatus($client)]);
                    continue;
                }

                try {
                    Log::info('Campaign WhatsApp recipient send attempt', [
                        'campaign_id' => $campaign->id,
                        'message_id' => $message->id,
                        'client_id' => $client->id,
                        'phone' => $client->phone,
                        'campaign_whatsapp_from' => $campaign->whatsapp_from,
                    ]);

                    $subject = $client->name ?? '';
                    $bodyVar = $mode === 'flow'
                        ? ($flowDef[0]['message'] ?? '')
                        : '';

                    $twResponse = $this->whatsApp->sendTemplateFromSubjectMessage(
                        $client->phone,
                        $templateSid,
                        $subject,
                        $bodyVar,
                        $campaign->whatsapp_from
                    );

                    $mappedStatus = $this->mapTwilioStatus($twResponse['status'] ?? 'queued');

                    CampaignWhatsappRecipient::where('whatsapp_message_id', $message->id)
                        ->where('client_id', $client->id)
                        ->update([
                            'message_sid'  => $twResponse['sid'] ?? null,
                            'provider_message_id' => $twResponse['message_id'] ?? ($twResponse['sid'] ?? null),
                            'provider_phone_number_id' => $twResponse['phone_number_id'] ?? $senderContext['phone_number_id'],
                            'provider_display_phone_number' => $twResponse['display_phone_number'] ?? $senderContext['display_phone_number'],
                            'status'       => $mappedStatus,
                            'delivered_at' => $mappedStatus === 'Delivered' ? now() : null,
                        ]);
                } catch (\Throwable $e) {
                    Log::error('Failed to send WhatsApp for client', [
                        'campaign_id' => $campaign->id,
                        'client_id'   => $client->id,
                        'mode'        => $mode,
                        'error'       => $e->getMessage(),
                    ]);
                }
            }

            $this->refreshWhatsappMessageCounts($message);
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

        if (!$user || !$user->canManageOperationalData()) {
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

        // System admins can view all
        if ($user->canAccessAllBanks()) {
            return;
        }

        if ($user->resolvedBankId() && (int) $campaign->bank_id !== $user->resolvedBankId()) {
            abort(403, 'You are not allowed to view this campaign.');
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
        $this->authorizeManage();
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

        if (!$user->resolvedBankId()) {
            abort(422, 'Your user account is not assigned to a bank.');
        }

        return $user->resolvedBankId();
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
        if (!$raw) {
            return null;
        }

        $normalized = MetaWhatsAppService::normalizePhoneNumber($raw);
        if ($normalized) {
            return $normalized;
        }

        // fallback: ensure leading + if already looks international
        return str_starts_with($raw, '+') ? $raw : $raw;
    }

    protected function isWhatsappSuppressedClient($client): bool
    {
        if (!$client) {
            return false;
        }

        if ($client instanceof Client) {
            return $client->isWhatsappSuppressed();
        }

        return !empty($client->whatsapp_opted_out_at);
    }

    protected function clientHasWhatsappLawfulBasis($client): bool
    {
        if (!$client) {
            return false;
        }

        if ($client instanceof Client) {
            return $client->hasWhatsappLawfulBasis();
        }

        return !empty($client->whatsapp_contact_basis) || !empty($client->whatsapp_opted_in_at);
    }

    protected function canSendWhatsappToClient($client): bool
    {
        return !$this->isWhatsappSuppressedClient($client) && $this->clientHasWhatsappLawfulBasis($client);
    }

    protected function whatsappComplianceBlockedStatus($client): string
    {
        if ($this->isWhatsappSuppressedClient($client)) {
            return 'Suppressed';
        }

        return 'No Lawful Basis';
    }

    protected function refreshWhatsappMessageCounts(?CampaignWhatsappMessage $message): void
    {
        if (!$message) {
            return;
        }

        $delivered = $message->recipients()->whereRaw('LOWER(status) = ?', ['delivered'])->count();
        $failed    = $message->recipients()->whereRaw('LOWER(status) = ?', ['failed'])->count();
        $pending   = $message->recipients()->whereNotIn('status', ['Delivered', 'Failed', 'Suppressed'])->count();

        $message->update([
            'delivered' => $delivered,
            'failed'    => $failed,
            'pending'   => $pending,
        ]);
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
