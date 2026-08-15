<?php

namespace App\Http\Controllers\Api;

use App\Concerns\HasAuditLogging;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\ExportRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ExportRequestController extends Controller
{
    use HasAuditLogging;

    public function index(Request $request)
    {
        $user = Auth::user();
        abort_unless($user && $user->canViewExportRequests(), 403, 'You are not allowed to access export requests.');
        $query = ExportRequest::query()
            ->with([
                'requestedBy:id,name,role',
                'approvedBy:id,name',
                'rejectedBy:id,name',
                'downloadedBy:id,name',
                'bank:id,name',
            ])
            ->latest();

        if ($user->canApproveExportRequests()) {
            if (!$user->canAccessAllBanks() && !empty($user->resolvedBankIds())) {
                $query->whereIn('bank_id', $user->resolvedBankIds());
            }
        } else {
            $query->where('requested_by_user_id', $user->id);
        }

        if ($status = $request->get('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        if ($dataset = $request->get('dataset')) {
            if ($dataset !== 'all') {
                $query->where('dataset', $dataset);
            }
        }

        if ($q = trim((string) $request->get('q', ''))) {
            $query->where(function ($builder) use ($q) {
                $builder->where('justification', 'like', "%{$q}%")
                    ->orWhere('dataset', 'like', "%{$q}%")
                    ->orWhereHas('requestedBy', function ($userQuery) use ($q) {
                        $userQuery->where('name', 'like', "%{$q}%")
                            ->orWhere('role', 'like', "%{$q}%")
                            ->orWhere('email', 'like', "%{$q}%");
                    })
                    ->orWhereHas('bank', function ($bankQuery) use ($q) {
                        $bankQuery->where('name', 'like', "%{$q}%");
                    });
            });
        }

        if ($from = $request->get('date_from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->get('date_to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        return $query->paginate((int) $request->get('per_page', 20))
            ->through(fn (ExportRequest $exportRequest) => $this->transform($exportRequest));
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        abort_unless($user && $user->canRequestSensitiveExports(), 403, 'You are not allowed to request exports.');

        $data = $request->validate([
            'dataset' => ['required', 'string', 'in:' . implode(',', ExportRequest::ALL_DATASETS)],
            'target_type' => ['nullable', 'string', 'max:50'],
            'target_id' => ['nullable', 'integer'],
            'filters' => ['nullable', 'array'],
            'justification' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $unusualExportDetected = $this->detectUnusualExportActivity($user);
        $autoApprove = $user->canBypassExportApproval() && !$unusualExportDetected;

        $exportRequest = ExportRequest::create([
            'requested_by_user_id' => $user->id,
            'bank_id' => $this->resolveBankId($user, $data),
            'dataset' => $data['dataset'],
            'target_type' => $data['target_type'] ?? null,
            'target_id' => $data['target_id'] ?? null,
            'filters' => $data['filters'] ?? [],
            'justification' => trim($data['justification']),
            'status' => $autoApprove
                ? ExportRequest::STATUS_APPROVED
                : ExportRequest::STATUS_PENDING,
            'approved_by_user_id' => $autoApprove ? $user->id : null,
            'approved_at' => $autoApprove ? now() : null,
        ]);

        $this->audit(
            action: 'Created export request',
            module: 'Export Requests',
            meta: [
                'export_request_id' => $exportRequest->id,
                'dataset' => $exportRequest->dataset,
                'target_type' => $exportRequest->target_type,
                'target_id' => $exportRequest->target_id,
                'status' => $exportRequest->status,
                'bank_id' => $exportRequest->bank_id,
                'unusual_export_detected' => $unusualExportDetected,
            ]
        );

        if ($unusualExportDetected) {
            $this->audit(
                action: 'Unusual export activity detected',
                module: 'Export Requests',
                meta: [
                    'requested_by_user_id' => $user->id,
                    'dataset' => $exportRequest->dataset,
                    'export_request_id' => $exportRequest->id,
                ]
            );

            $this->notifyExportApproversOfUnusualActivity($exportRequest);
        }

        return response()->json([
            'mode' => $exportRequest->status === ExportRequest::STATUS_APPROVED ? 'download' : 'request',
            'message' => $unusualExportDetected
                ? 'Export request submitted for approval and flagged for unusual export volume review.'
                : ($exportRequest->status === ExportRequest::STATUS_APPROVED
                    ? 'Export approved and ready for download.'
                    : 'Export request submitted for approval.'),
            'request' => $this->transform($exportRequest->fresh(['requestedBy:id,name,role', 'bank:id,name'])),
        ], 201);
    }

    public function approve(ExportRequest $exportRequest)
    {
        $user = Auth::user();
        abort_unless($user && $user->canApproveExportRequests(), 403, 'You are not allowed to approve export requests.');
        $this->authorizeBankScope($user, $exportRequest);

        if ($exportRequest->status === ExportRequest::STATUS_DOWNLOADED) {
            abort(422, 'This export request has already been used.');
        }

        if ((int) $exportRequest->requested_by_user_id === (int) $user->id && !$user->isSuperAdmin()) {
            abort(403, 'You cannot approve your own export request.');
        }

        $exportRequest->forceFill([
            'status' => ExportRequest::STATUS_APPROVED,
            'approved_by_user_id' => $user->id,
            'approved_at' => now(),
            'rejected_by_user_id' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
        ])->save();

        $this->audit(
            action: 'Approved export request',
            module: 'Export Requests',
            meta: [
                'export_request_id' => $exportRequest->id,
                'dataset' => $exportRequest->dataset,
                'requested_by_user_id' => $exportRequest->requested_by_user_id,
            ]
        );

        return $this->transform($exportRequest->fresh(['requestedBy:id,name,role', 'approvedBy:id,name', 'bank:id,name']));
    }

    public function reject(Request $request, ExportRequest $exportRequest)
    {
        $user = Auth::user();
        abort_unless($user && $user->canApproveExportRequests(), 403, 'You are not allowed to reject export requests.');
        $this->authorizeBankScope($user, $exportRequest);

        if ($exportRequest->status === ExportRequest::STATUS_DOWNLOADED) {
            abort(422, 'This export request has already been used.');
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        $exportRequest->forceFill([
            'status' => ExportRequest::STATUS_REJECTED,
            'rejected_by_user_id' => $user->id,
            'rejected_at' => now(),
            'rejection_reason' => trim($data['reason']),
        ])->save();

        $this->audit(
            action: 'Rejected export request',
            module: 'Export Requests',
            meta: [
                'export_request_id' => $exportRequest->id,
                'dataset' => $exportRequest->dataset,
                'requested_by_user_id' => $exportRequest->requested_by_user_id,
            ]
        );

        return $this->transform($exportRequest->fresh(['requestedBy:id,name,role', 'rejectedBy:id,name', 'bank:id,name']));
    }

    protected function transform(ExportRequest $exportRequest): array
    {
        $user = Auth::user();
        $canUseDownload = $exportRequest->status === ExportRequest::STATUS_APPROVED
            && ($user?->canBypassExportApproval() || (int) $exportRequest->requested_by_user_id === (int) $user?->id);

        return [
            'id' => $exportRequest->id,
            'dataset' => $exportRequest->dataset,
            'dataset_label' => $this->datasetLabel($exportRequest->dataset),
            'target_type' => $exportRequest->target_type,
            'target_id' => $exportRequest->target_id,
            'status' => $exportRequest->status,
            'justification' => $exportRequest->justification,
            'rejection_reason' => $exportRequest->rejection_reason,
            'filters' => $exportRequest->filters ?? [],
            'bank_id' => $exportRequest->bank_id,
            'bank_name' => $exportRequest->bank?->name,
            'requested_by_name' => $exportRequest->requestedBy?->name,
            'requested_by_role' => $exportRequest->requestedBy?->role,
            'approved_by_name' => $exportRequest->approvedBy?->name,
            'rejected_by_name' => $exportRequest->rejectedBy?->name,
            'downloaded_by_name' => $exportRequest->downloadedBy?->name,
            'target_label' => $this->targetLabel($exportRequest),
            'scope_summary' => $this->scopeSummary($exportRequest),
            'created_at' => optional($exportRequest->created_at)->toDateTimeString(),
            'approved_at' => optional($exportRequest->approved_at)->toDateTimeString(),
            'rejected_at' => optional($exportRequest->rejected_at)->toDateTimeString(),
            'downloaded_at' => optional($exportRequest->downloaded_at)->toDateTimeString(),
            'download_filename' => $exportRequest->download_filename,
            'download_url' => $canUseDownload ? $this->downloadUrlFor($exportRequest) : null,
        ];
    }

    protected function datasetLabel(string $dataset): string
    {
        return match ($dataset) {
            ExportRequest::DATASET_CLIENTS => 'Clients',
            ExportRequest::DATASET_AUDIT_LOGS => 'Audit Logs',
            ExportRequest::DATASET_CAMPAIGN_CLIENTS => 'Campaign Clients',
            ExportRequest::DATASET_CAMPAIGN_WHATSAPP_MESSAGES => 'Campaign WhatsApp Messages',
            ExportRequest::DATASET_CAMPAIGN_EMAILS => 'Campaign Emails',
            ExportRequest::DATASET_CAMPAIGN_SMS_MESSAGES => 'Campaign SMS Messages',
            default => ucfirst(str_replace('_', ' ', $dataset)),
        };
    }

    protected function targetLabel(ExportRequest $exportRequest): string
    {
        if ($exportRequest->target_type && $exportRequest->target_id) {
            return "{$exportRequest->target_type} #{$exportRequest->target_id}";
        }

        return 'General dataset';
    }

    protected function scopeSummary(ExportRequest $exportRequest): array
    {
        $filters = $exportRequest->filters ?? [];

        return match ($exportRequest->dataset) {
            ExportRequest::DATASET_CLIENTS => array_values(array_filter([
                ['label' => 'Search', 'value' => $filters['search'] ?? null],
                ['label' => 'Department', 'value' => $filters['department'] ?? null],
                ['label' => 'Tag', 'value' => $filters['tag'] ?? null],
                ['label' => 'Bank ID', 'value' => $filters['bank_id'] ?? null],
            ], fn ($row) => filled($row['value']))),
            ExportRequest::DATASET_AUDIT_LOGS => array_values(array_filter([
                ['label' => 'Module', 'value' => $filters['module'] ?? null],
                ['label' => 'User ID', 'value' => $filters['user_id'] ?? null],
                ['label' => 'From', 'value' => $filters['date_from'] ?? null],
                ['label' => 'To', 'value' => $filters['date_to'] ?? null],
                ['label' => 'Search', 'value' => $filters['q'] ?? null],
            ], fn ($row) => filled($row['value']) && $row['value'] !== 'all')),
            ExportRequest::DATASET_CAMPAIGN_CLIENTS,
            ExportRequest::DATASET_CAMPAIGN_WHATSAPP_MESSAGES,
            ExportRequest::DATASET_CAMPAIGN_EMAILS,
            ExportRequest::DATASET_CAMPAIGN_SMS_MESSAGES => array_values(array_filter([
                ['label' => 'Target', 'value' => $this->targetLabel($exportRequest)],
                ['label' => 'Bank', 'value' => $exportRequest->bank?->name],
            ], fn ($row) => filled($row['value']))),
            default => [['label' => 'Filters', 'value' => empty($filters) ? 'Default scope' : json_encode($filters)]],
        };
    }

    protected function downloadUrlFor(ExportRequest $exportRequest): ?string
    {
        if (!in_array($exportRequest->status, [ExportRequest::STATUS_APPROVED], true)) {
            return null;
        }

        $query = http_build_query(array_merge(
            $exportRequest->filters ?? [],
            ['export_request_id' => $exportRequest->id],
        ));

        return match ($exportRequest->dataset) {
            ExportRequest::DATASET_CLIENTS => "/api/clients/export?{$query}",
            ExportRequest::DATASET_AUDIT_LOGS => "/api/audit-logs/export?{$query}",
            ExportRequest::DATASET_CAMPAIGN_CLIENTS => "/api/campaigns/{$exportRequest->target_id}/clients/export?{$query}",
            ExportRequest::DATASET_CAMPAIGN_WHATSAPP_MESSAGES => "/api/campaigns/{$exportRequest->target_id}/whatsapp-messages/export?{$query}",
            ExportRequest::DATASET_CAMPAIGN_EMAILS => "/api/campaigns/{$exportRequest->target_id}/emails/export?{$query}",
            ExportRequest::DATASET_CAMPAIGN_SMS_MESSAGES => "/api/campaigns/{$exportRequest->target_id}/sms-messages/export?{$query}",
            default => null,
        };
    }

    protected function resolveBankId($user, array $data): ?int
    {
        if (($data['target_type'] ?? null) === 'campaign' && !empty($data['target_id'])) {
            return Campaign::query()->whereKey($data['target_id'])->value('bank_id');
        }

        if (!$user->canAccessAllBanks()) {
            return $user->resolvedBankId();
        }

        return isset($data['filters']['bank_id']) && $data['filters']['bank_id'] !== ''
            ? (int) $data['filters']['bank_id']
            : null;
    }

    protected function authorizeBankScope($user, ExportRequest $exportRequest): void
    {
        if ($user->canAccessAllBanks()) {
            return;
        }

        if (!empty($user->resolvedBankIds()) && !in_array((int) $exportRequest->bank_id, $user->resolvedBankIds(), true)) {
            abort(403, 'You are not allowed to act on this export request.');
        }
    }

    protected function detectUnusualExportActivity($user): bool
    {
        $threshold = (int) env('EXPORT_REQUEST_ALERT_THRESHOLD', 5);
        if ($threshold <= 0) {
            return false;
        }

        $count = ExportRequest::query()
            ->where('requested_by_user_id', $user->id)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        return $count >= $threshold;
    }

    protected function notifyExportApproversOfUnusualActivity(ExportRequest $exportRequest): void
    {
        $approverEmails = User::query()
            ->with('roles:id,code,is_active')
            ->where('status', 'Active')
            ->when($exportRequest->bank_id, function ($query) use ($exportRequest) {
                $query->where(function ($builder) use ($exportRequest) {
                    $builder->whereNull('bank_id')
                        ->orWhereIn('bank_id', [$exportRequest->bank_id]);
                });
            })
            ->get()
            ->filter(fn (User $user) => $user->canApproveExportRequests())
            ->pluck('email')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($approverEmails)) {
            return;
        }

        try {
            Mail::raw(
                "An export request has been flagged for unusual activity review.\n\n"
                . "Request ID: {$exportRequest->id}\n"
                . "Dataset: {$exportRequest->dataset}\n"
                . "Requested by user ID: {$exportRequest->requested_by_user_id}\n"
                . "Bank ID: " . ($exportRequest->bank_id ?: 'N/A') . "\n"
                . "Justification: {$exportRequest->justification}\n\n"
                . "Please review the request in the Export Requests module.",
                function ($message) use ($approverEmails, $exportRequest) {
                    $message->to($approverEmails)
                        ->subject("Unusual export activity detected for request #{$exportRequest->id}");
                }
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to send unusual export escalation email', [
                'export_request_id' => $exportRequest->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
