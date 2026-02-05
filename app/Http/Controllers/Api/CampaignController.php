<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignClient;
use App\Models\CampaignWhatsappMessage;
use App\Models\CampaignEmailRecipient;
use App\Models\CampaignSmsRecipient;
use App\Models\CampaignWhatsappRecipient;
use App\Models\WhatsAppFlow;
use App\Models\Client;
use App\Services\TwilioWhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CampaignController extends Controller
{
    protected TwilioWhatsAppService $twilioWhatsApp;

    public function __construct(TwilioWhatsAppService $twilioWhatsApp)
    {
        $this->twilioWhatsApp = $twilioWhatsApp;
    }
    /**
     * List campaigns (department + role scoped).
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = Campaign::query()
            ->with('departments')
            ->withCount('clients as total_recipients')
            ->orderByDesc('created_at');

        // Department scoping (same logic as before)
        if ($user && $user->role !== 'SUPER_ADMIN') {
            $query->where(function ($q) use ($user) {
                $q->whereDoesntHave('departments');

                if ($user->department_id) {
                    $q->orWhereHas('departments', function ($qq) use ($user) {
                        $qq->where('departments.id', $user->department_id);
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

        return $campaign->load('departments');
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
                    'phone' => $client->phone,
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
            ->with('departments')
            ->paginate(50)
            ->through(function ($client) use ($campaign) {
                $pivot = CampaignClient::where('campaign_id', $campaign->id)
                    ->where('client_id', $client->id)
                    ->first();
                
                return [
                    'id' => $client->id,
                    'name' => $client->name,
                    'email' => $client->email,
                    'phone' => $client->phone,
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



    public function store(Request $request)
    {
        $this->authorizeManage();

        $data = $request->validate([
            'name'              => ['required', 'string', 'max:255'],
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
                $data['whatsapp_from'] = optional(\App\Models\SystemSetting::first())->twilio_whatsapp_from;
            }
        }

        $campaign = Campaign::create($data);

        if (!empty($deptIds)) {
            $campaign->departments()->sync($deptIds);
        }

        return response()->json($campaign->load('departments'), 201);
    }

    public function update(Request $request, Campaign $campaign)
    {
        $this->authorizeManageCampaign($campaign);

        $data = $request->validate([
            'name'              => ['sometimes', 'string', 'max:255'],
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
                $data['whatsapp_from'] = optional(\App\Models\SystemSetting::first())->twilio_whatsapp_from;
            }
        }

        $campaign->update($data);

        if (!is_null($deptIds)) {
            $campaign->departments()->sync($deptIds);
        }

        return $campaign->load('departments');
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
     * Trigger sending of campaign (enqueue Twilio / Email / ZoomConnect jobs).
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
        //  - For WhatsApp: call Twilio WhatsApp API with approved template
        //  - For Email: push to your mailer
        //  - For SMS: call ZoomConnect API
        //  - Store each send result into campaign_* tables for reporting
        //  - Update campaign stats + audit trail

        // For now we just return a simple JSON stub
        return response()->json([
            'message'  => 'Send job queued (stub). Implement SendCampaignJob with Twilio/Email/ZoomConnect.',
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

        $campaign->load([
            'clients:id',                // just count them
            'whatsappMessages:id,campaign_id,total,delivered,failed,pending',
            'emailMessages:id,campaign_id,total,delivered,bounced,opened,clicked',
            'smsMessages:id,campaign_id,total,delivered,failed,pending',
        ]);

        // total clients attached via pivot
        $totalClients = $campaign->clients->count();

        // WhatsApp aggregate (sum from batches)
        $whatsTotals = [
            'total'     => $campaign->whatsappMessages->sum('total'),
            'delivered' => $campaign->whatsappMessages->sum('delivered'),
            'failed'    => $campaign->whatsappMessages->sum('failed'),
            'pending'   => $campaign->whatsappMessages->sum('pending'),
        ];

        // Email aggregate
        $emailTotals = [
            'total'     => $campaign->emailMessages->sum('total'),
            'delivered' => $campaign->emailMessages->sum('delivered'),
            'bounced'   => $campaign->emailMessages->sum('bounced'),
            'opened'    => $campaign->emailMessages->sum('opened'),
            'clicked'   => $campaign->emailMessages->sum('clicked'),
        ];

        // SMS aggregate
        $smsTotals = [
            'total'     => $campaign->smsMessages->sum('total'),
            'delivered' => $campaign->smsMessages->sum('delivered'),
            'failed'    => $campaign->smsMessages->sum('failed'),
            'pending'   => $campaign->smsMessages->sum('pending'),
        ];

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

        $messages = $campaign->whatsappMessages()
            ->orderByDesc('created_at')
            ->withCount([
                'recipients as yes_responses_count' => function ($q) {
                    $q->whereRaw('LOWER(last_response) = ?', ['yes']);
                },
                'recipients as no_responses_count' => function ($q) {
                    $q->whereRaw('LOWER(last_response) = ?', ['no']);
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

        $mapped = $messages->map(function ($m) {
            $status = 'Draft';
            if ($m->sent_at) {
                if ($m->pending > 0) {
                    $status = 'Pending';
                } elseif ($m->failed > 0 && $m->delivered === 0) {
                    $status = 'Failed';
                } elseif ($m->delivered > 0 && $m->pending === 0) {
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
                'total'         => $m->total,
                'delivered'     => $m->delivered,
                'failed'        => $m->failed,
                'pending'       => $m->pending,
                'created_at'    => optional($m->created_at)->toDateTimeString(),
                'enable_live_chat' => (bool) $m->enable_live_chat,
                'yes_responses_count' => $m->yes_responses_count ?? 0,
                'no_responses_count' => $m->no_responses_count ?? 0,
                'status'        => $status,
            ];
        });

        return response()->json($mapped);
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

        $clients = $clientsQuery->get(['clients.id', 'clients.name', 'clients.phone']);
        if ($clients->isEmpty()) {
            return response()->json(['message' => 'No clients found for this batch.'], 422);
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
            $template     = $this->twilioWhatsApp->getTemplateDetails($templateSid);
            $friendlyName = $template['friendly_name'] ?? $templateSid;
            $types        = $template['types'] ?? [];
            $previewBody  = $types['twilio/text']['body']
                            ?? $types['twilio/quick-reply']['body']
                            ?? null;
        } else {
            $flow        = WhatsAppFlow::findOrFail($data['flow_id']);
            $flowId      = $flow->id;
            $flowName    = $flow->name;
            $flowDef     = $flow->flow_definition;
            $templateSid = $flow->template_sid;
            $friendlyName = $flowName;
            $previewBody = $flowDef && isset($flowDef[0]['message']) ? $flowDef[0]['message'] : 'Flow start';
        }

        $total = $clients->count();
        $now   = now();

        // Reset recipients
        CampaignWhatsappRecipient::where('whatsapp_message_id', $message->id)->delete();

        $rows = [];
        foreach ($clients as $client) {
            $rows[] = [
                'whatsapp_message_id' => $message->id,
                'client_id'           => $client->id,
                'phone'               => $client->phone,
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
                try {
                    $subject = $client->name ?? '';
                    $bodyVar = $mode === 'flow'
                        ? ($flowDef[0]['message'] ?? '')
                        : '';
                    $twResponse = $this->twilioWhatsApp->sendTemplateFromSubjectMessage(
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

        if ($recipients->isEmpty()) {
            return response()->json(['message' => 'No recipients found for this batch.'], 422);
        }

        $now = now();

        // Update recipient statuses to pending
        CampaignWhatsappRecipient::where('whatsapp_message_id', $message->id)
            ->update(['status' => 'pending', 'updated_at' => $now]);

        // Update message meta
        $message->update([
            'sent_at'   => $now,
            'pending'   => $recipients->count(),
            'delivered' => 0,
            'failed'    => 0,
        ]);

        // Update campaign client pivots
        CampaignClient::where('campaign_id', $campaign->id)
            ->whereIn('client_id', $recipients->pluck('client_id'))
            ->update([
                'whatsapp_status'  => 'Pending',
                'whatsapp_sent_at' => $now,
                'updated_at'       => $now,
            ]);

        // Send via Twilio
        if ($message->template_sid) {
            foreach ($recipients as $recipient) {
                $client = $recipient->client;
                $phone  = $recipient->phone ?: $client?->phone;
                if (!$phone) {
                    continue;
                }
                try {
                    $subject = $client?->name ?? '';
                    $bodyVar = $message->mode === 'flow'
                        ? ($message->flow_definition[0]['message'] ?? '')
                        : '';
                    $twResponse = $this->twilioWhatsApp->sendTemplateFromSubjectMessage(
                        $phone,
                        $message->template_sid,
                        $subject,
                        $bodyVar
                    );

                    $mappedStatus = $this->mapTwilioStatus($twResponse['status'] ?? 'queued');
                    $recipient->message_sid = $twResponse['sid'] ?? $recipient->message_sid;
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

        return response()->json($messages);
    }

    public function emailRecipients(Campaign $campaign, $emailId)
    {
        $this->authorizeView($campaign);

        $message = $campaign->emailMessages()
            ->where('id', $emailId)
            ->firstOrFail();

        $recipients = CampaignEmailRecipient::with('client')
            ->where('campaign_email_message_id', $message->id)
            ->get()
            ->map(function ($r) {
                return [
                    'id'           => $r->id,
                    'client_name'  => $r->client?->name,
                    'email'        => $r->email ?: $r->client?->email,
                    'phone'        => $r->client?->phone,
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

        return response()->json($messages);
    }

    public function smsRecipients(Campaign $campaign, $smsId)
    {
        $this->authorizeView($campaign);

        $message = $campaign->smsMessages()
            ->where('id', $smsId)
            ->firstOrFail();

        $recipients = CampaignSmsRecipient::with('client')
            ->where('campaign_sms_message_id', $message->id)
            ->get()
            ->map(function ($r) {
                return [
                    'id'           => $r->id,
                    'client_name'  => $r->client?->name,
                    'email'        => $r->client?->email,
                    'phone'        => $r->phone ?: $r->client?->phone,
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

        /** @var \App\Models\CampaignWhatsappMessage $message */
        $message = $campaign->whatsappMessages()
            ->where('id', $messageId)
            ->firstOrFail();

        // Load recipients + client + client departments
        $recipientModels = CampaignWhatsappRecipient::with(['client.departments'])
            ->where('whatsapp_message_id', $message->id)
            ->get();

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
     *  - template_id: string (Twilio ContentSid)
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

        $clients = $clientsQuery->get(['clients.id', 'clients.name', 'clients.phone']);

        if ($clients->isEmpty()) {
            return response()->json([
                'message' => 'No clients found for this batch.',
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
            $template      = $this->twilioWhatsApp->getTemplateDetails($templateSid);
            $friendlyName  = $template['friendly_name'] ?? $templateSid;
            $types         = $template['types'] ?? [];
            $previewBody   = $types['twilio/text']['body']
                            ?? $types['twilio/quick-reply']['body']
                            ?? null;
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

        // Create parent WhatsApp "batch" row via relationship
        $total = $clients->count();
        $now   = now();

        $message = $campaign->whatsappMessages()->create([
            'mode'              => $mode,
            'template_sid'      => $templateSid,
            'template_name'     => $friendlyName,
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
                'phone'                        => $client->phone,
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
        
            // 🔹 ONLY send via Twilio if send_now is true
        if ($sendNow && $templateSid) {
            foreach ($clients as $client) {
                if (!$client->phone) {
                    continue;
                }

                try {
                    $subject = $client->name ?? '';
                    $bodyVar = $mode === 'flow'
                        ? ($flowDef[0]['message'] ?? '')
                        : '';

                    $twResponse = $this->twilioWhatsApp->sendTemplateFromSubjectMessage(
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

        if (!$user || !in_array($user->role, ['SUPER_ADMIN', 'MANAGER'])) {
            abort(403, 'You are not allowed to manage campaigns.');
        }
    }

    protected function authorizeView(Campaign $campaign): void
    {
        $user = Auth::user();

        if (!$user) {
            abort(401);
        }

        // SUPER_ADMIN can view all
        if ($user->role === 'SUPER_ADMIN') {
            return;
        }

        // Others: only global or same department
        if (!is_null($campaign->department_id) && $campaign->department_id !== $user->department_id) {
            abort(403, 'You are not allowed to view this campaign.');
        }
    }

    protected function authorizeManageCampaign(Campaign $campaign): void
    {
        $this->authorizeManage();
        $this->authorizeView($campaign);
    }

    protected function mapTwilioStatus(?string $status): string
    {
        return match (strtolower((string) $status)) {
            'delivered', 'read' => 'Delivered',
            'failed', 'undelivered' => 'Failed',
            default => 'Pending',
        };
    }

    protected function refreshWhatsappMessageCounts(?CampaignWhatsappMessage $message): void
    {
        if (!$message) {
            return;
        }

        $delivered = $message->recipients()->whereRaw('LOWER(status) = ?', ['delivered'])->count();
        $failed    = $message->recipients()->whereRaw('LOWER(status) = ?', ['failed'])->count();
        $pending   = $message->recipients()->whereNotIn('status', ['Delivered', 'Failed'])->count();

        $message->update([
            'delivered' => $delivered,
            'failed'    => $failed,
            'pending'   => $pending,
        ]);
    }

     /**
     * List WhatsApp templates from Twilio for dropdowns.
     *
     * GET /api/whatsapp-templates?approved=1
     */
    public function listWhatsappTemplates(Request $request): JsonResponse
    {
        $onlyApproved = filter_var($request->query('approved', '1'), FILTER_VALIDATE_BOOLEAN);

        $templates = $this->twilioWhatsApp->getWhatsAppTemplates($onlyApproved);

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
        $details   = $this->twilioWhatsApp->getTemplateDetails($id);
        $approvals = $this->twilioWhatsApp->getTemplateApprovalStatus($id);

        return response()->json([
            'template'  => $details,
            'approvals' => $approvals,
        ]);
    }
}
