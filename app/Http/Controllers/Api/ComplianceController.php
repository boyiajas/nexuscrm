<?php

namespace App\Http\Controllers\Api;

use App\Concerns\HasAuditLogging;
use App\Http\Controllers\Controller;
use App\Models\BankTransferProfile;
use App\Models\ComplaintCase;
use App\Models\DataSubjectRequest;
use App\Models\InformationOfficer;
use App\Models\RetentionAction;
use App\Models\RetentionPolicy;
use App\Models\User;
use App\Services\BankTransferService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ComplianceController extends Controller
{
    use HasAuditLogging;

    public function __construct(private BankTransferService $bankTransfers)
    {
    }

    public function overview()
    {
        $user = Auth::user();
        $this->authorizeView($user);

        return response()->json([
            'data_subject_requests' => $this->scopedQuery(DataSubjectRequest::query(), $user)->with(['bank:id,name', 'client:id,name,email,phone', 'reporter:id,name,role', 'assignee:id,name,role'])->latest()->limit(50)->get(),
            'complaints' => $this->scopedQuery(ComplaintCase::query(), $user)->with(['bank:id,name', 'client:id,name,email,phone', 'reporter:id,name,role', 'assignee:id,name,role'])->latest()->limit(50)->get(),
            'information_officers' => $this->scopedQuery(InformationOfficer::query(), $user)->with('bank:id,name')->latest()->get(),
            'retention_policies' => $this->scopedQuery(RetentionPolicy::query(), $user)->with('bank:id,name')->latest()->get(),
            'retention_actions' => $this->scopedQuery(RetentionAction::query(), $user)->with(['bank:id,name', 'policy:id,dataset', 'requester:id,name,role', 'approver:id,name,role'])->latest()->limit(50)->get(),
            'bank_transfer_profiles' => $this->scopedQuery(BankTransferProfile::query(), $user)->with(['bank:id,name', 'runs' => fn ($q) => $q->latest()->limit(5)])->latest()->get(),
        ]);
    }

    public function storeDataSubjectRequest(Request $request)
    {
        $user = Auth::user();
        $this->authorizeManage($user);

        $data = $request->validate([
            'bank_id' => ['nullable', 'integer', 'exists:banks,id'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'assigned_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'request_type' => ['required', Rule::in(['access', 'correction', 'objection', 'opt_out', 'deletion', 'complaint'])],
            'status' => ['nullable', Rule::in(['open', 'in_progress', 'waiting_bank', 'resolved', 'rejected'])],
            'requester_name' => ['nullable', 'string', 'max:255'],
            'requester_email' => ['nullable', 'email', 'max:255'],
            'requester_phone' => ['nullable', 'string', 'max:50'],
            'received_channel' => ['nullable', 'string', 'max:50'],
            'details' => ['required', 'string', 'max:10000'],
            'due_at' => ['nullable', 'date'],
            'resolution_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $record = DataSubjectRequest::create([
            ...$data,
            'bank_id' => $this->resolveBankId($user, $data['bank_id'] ?? null),
            'reported_by_user_id' => $user->id,
            'status' => $data['status'] ?? 'open',
            'assigned_to_user_id' => $this->resolveAssigneeId($user, $data['bank_id'] ?? null, $data['assigned_to_user_id'] ?? null),
        ]);

        $this->audit("Created data subject request #{$record->id}", 'Compliance', [
            'workflow' => 'data_subject_request',
            'record_id' => $record->id,
            'bank_id' => $record->bank_id,
            'request_type' => $record->request_type,
        ]);

        return response()->json($record->load(['bank:id,name', 'client:id,name,email,phone', 'reporter:id,name,role', 'assignee:id,name,role']), 201);
    }

    public function updateDataSubjectRequest(Request $request, DataSubjectRequest $dataSubjectRequest)
    {
        $user = Auth::user();
        $this->authorizeManage($user);
        $this->authorizeRecordBank($user, $dataSubjectRequest->bank_id);

        $data = $request->validate([
            'assigned_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', Rule::in(['open', 'in_progress', 'waiting_bank', 'resolved', 'rejected'])],
            'due_at' => ['nullable', 'date'],
            'resolution_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        if (array_key_exists('assigned_to_user_id', $data)) {
            $data['assigned_to_user_id'] = $this->resolveAssigneeId($user, $dataSubjectRequest->bank_id, $data['assigned_to_user_id']);
        }
        if (($data['status'] ?? null) === 'resolved' && !$dataSubjectRequest->resolved_at) {
            $data['resolved_at'] = now();
        }

        $dataSubjectRequest->update($data);

        $this->audit("Updated data subject request #{$dataSubjectRequest->id}", 'Compliance', [
            'workflow' => 'data_subject_request',
            'record_id' => $dataSubjectRequest->id,
            'bank_id' => $dataSubjectRequest->bank_id,
        ]);

        return response()->json($dataSubjectRequest->load(['bank:id,name', 'client:id,name,email,phone', 'reporter:id,name,role', 'assignee:id,name,role']));
    }

    public function storeComplaint(Request $request)
    {
        $user = Auth::user();
        $this->authorizeManage($user);

        $data = $request->validate([
            'bank_id' => ['nullable', 'integer', 'exists:banks,id'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'assigned_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'complaint_type' => ['required', Rule::in(['service', 'privacy', 'messaging', 'bank_instruction', 'data_quality', 'other'])],
            'severity' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'status' => ['nullable', Rule::in(['open', 'investigating', 'escalated', 'resolved', 'closed'])],
            'title' => ['required', 'string', 'max:255'],
            'details' => ['required', 'string', 'max:10000'],
            'escalation_required' => ['sometimes', 'boolean'],
            'regulator_notification_required' => ['sometimes', 'boolean'],
        ]);

        $record = ComplaintCase::create([
            ...$data,
            'bank_id' => $this->resolveBankId($user, $data['bank_id'] ?? null),
            'reported_by_user_id' => $user->id,
            'status' => $data['status'] ?? 'open',
            'assigned_to_user_id' => $this->resolveAssigneeId($user, $data['bank_id'] ?? null, $data['assigned_to_user_id'] ?? null),
        ]);

        $this->audit("Created complaint case #{$record->id}", 'Compliance', [
            'workflow' => 'complaint_case',
            'record_id' => $record->id,
            'bank_id' => $record->bank_id,
            'complaint_type' => $record->complaint_type,
        ]);

        return response()->json($record->load(['bank:id,name', 'client:id,name,email,phone', 'reporter:id,name,role', 'assignee:id,name,role']), 201);
    }

    public function updateComplaint(Request $request, ComplaintCase $complaintCase)
    {
        $user = Auth::user();
        $this->authorizeManage($user);
        $this->authorizeRecordBank($user, $complaintCase->bank_id);

        $data = $request->validate([
            'assigned_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'severity' => ['nullable', Rule::in(['low', 'medium', 'high', 'critical'])],
            'status' => ['nullable', Rule::in(['open', 'investigating', 'escalated', 'resolved', 'closed'])],
            'resolution_notes' => ['nullable', 'string', 'max:5000'],
            'escalation_required' => ['sometimes', 'boolean'],
            'regulator_notification_required' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('assigned_to_user_id', $data)) {
            $data['assigned_to_user_id'] = $this->resolveAssigneeId($user, $complaintCase->bank_id, $data['assigned_to_user_id']);
        }
        if (in_array($data['status'] ?? '', ['resolved', 'closed'], true) && !$complaintCase->resolved_at) {
            $data['resolved_at'] = now();
        }

        $complaintCase->update($data);

        $this->audit("Updated complaint case #{$complaintCase->id}", 'Compliance', [
            'workflow' => 'complaint_case',
            'record_id' => $complaintCase->id,
            'bank_id' => $complaintCase->bank_id,
        ]);

        return response()->json($complaintCase->load(['bank:id,name', 'client:id,name,email,phone', 'reporter:id,name,role', 'assignee:id,name,role']));
    }

    public function storeOfficer(Request $request)
    {
        $user = Auth::user();
        $this->authorizeManage($user);

        $data = $request->validate([
            'bank_id' => ['nullable', 'integer', 'exists:banks,id'],
            'officer_type' => ['required', Rule::in(['information_officer', 'deputy_information_officer'])],
            'name' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $record = InformationOfficer::create([
            ...$data,
            'bank_id' => $this->resolveBankId($user, $data['bank_id'] ?? null),
            'status' => $data['status'] ?? 'active',
        ]);

        $this->audit("Created information officer #{$record->id}", 'Compliance', [
            'workflow' => 'information_officer',
            'record_id' => $record->id,
            'bank_id' => $record->bank_id,
        ]);

        return response()->json($record->load('bank:id,name'), 201);
    }

    public function updateOfficer(Request $request, InformationOfficer $informationOfficer)
    {
        $user = Auth::user();
        $this->authorizeManage($user);
        $this->authorizeRecordBank($user, $informationOfficer->bank_id);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $informationOfficer->update($data);

        $this->audit("Updated information officer #{$informationOfficer->id}", 'Compliance', [
            'workflow' => 'information_officer',
            'record_id' => $informationOfficer->id,
            'bank_id' => $informationOfficer->bank_id,
        ]);

        return response()->json($informationOfficer->load('bank:id,name'));
    }

    public function storeRetentionPolicy(Request $request)
    {
        $user = Auth::user();
        $this->authorizeManage($user);

        $data = $request->validate([
            'bank_id' => ['nullable', 'integer', 'exists:banks,id'],
            'dataset' => ['required', 'string', 'max:100'],
            'retention_days' => ['required', 'integer', 'min:1'],
            'archive_after_days' => ['nullable', 'integer', 'min:1'],
            'delete_after_days' => ['nullable', 'integer', 'min:1'],
            'legal_hold_allowed' => ['sometimes', 'boolean'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $record = RetentionPolicy::create([
            ...$data,
            'bank_id' => $this->resolveBankId($user, $data['bank_id'] ?? null),
            'status' => $data['status'] ?? 'active',
            'legal_hold_allowed' => (bool) ($data['legal_hold_allowed'] ?? true),
        ]);

        $this->audit("Created retention policy #{$record->id}", 'Compliance', [
            'workflow' => 'retention_policy',
            'record_id' => $record->id,
            'bank_id' => $record->bank_id,
            'dataset' => $record->dataset,
        ]);

        return response()->json($record->load('bank:id,name'), 201);
    }

    public function updateRetentionPolicy(Request $request, RetentionPolicy $retentionPolicy)
    {
        $user = Auth::user();
        $this->authorizeManage($user);
        $this->authorizeRecordBank($user, $retentionPolicy->bank_id);

        $data = $request->validate([
            'retention_days' => ['nullable', 'integer', 'min:1'],
            'archive_after_days' => ['nullable', 'integer', 'min:1'],
            'delete_after_days' => ['nullable', 'integer', 'min:1'],
            'legal_hold_allowed' => ['sometimes', 'boolean'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $retentionPolicy->update($data);

        $this->audit("Updated retention policy #{$retentionPolicy->id}", 'Compliance', [
            'workflow' => 'retention_policy',
            'record_id' => $retentionPolicy->id,
            'bank_id' => $retentionPolicy->bank_id,
        ]);

        return response()->json($retentionPolicy->load('bank:id,name'));
    }

    public function storeRetentionAction(Request $request)
    {
        $user = Auth::user();
        $this->authorizeManage($user);

        $data = $request->validate([
            'retention_policy_id' => ['nullable', 'integer', 'exists:retention_policies,id'],
            'bank_id' => ['nullable', 'integer', 'exists:banks,id'],
            'dataset' => ['required', 'string', 'max:100'],
            'action_type' => ['required', Rule::in(['archive', 'delete'])],
            'scope_summary' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $record = RetentionAction::create([
            ...$data,
            'bank_id' => $this->resolveBankId($user, $data['bank_id'] ?? null),
            'requested_by_user_id' => $user->id,
            'status' => 'pending',
        ]);

        $this->audit("Created retention action #{$record->id}", 'Compliance', [
            'workflow' => 'retention_action',
            'record_id' => $record->id,
            'bank_id' => $record->bank_id,
            'action_type' => $record->action_type,
        ]);

        return response()->json($record->load(['bank:id,name', 'policy:id,dataset', 'requester:id,name,role', 'approver:id,name,role']), 201);
    }

    public function approveRetentionAction(RetentionAction $retentionAction)
    {
        $user = Auth::user();
        $this->authorizeManage($user);
        $this->authorizeRecordBank($user, $retentionAction->bank_id);

        $retentionAction->update([
            'status' => 'approved',
            'approved_by_user_id' => $user->id,
            'approved_at' => now(),
        ]);

        $this->audit("Approved retention action #{$retentionAction->id}", 'Compliance', [
            'workflow' => 'retention_action',
            'record_id' => $retentionAction->id,
            'bank_id' => $retentionAction->bank_id,
        ]);

        return response()->json($retentionAction->load(['bank:id,name', 'policy:id,dataset', 'requester:id,name,role', 'approver:id,name,role']));
    }

    public function completeRetentionAction(Request $request, RetentionAction $retentionAction)
    {
        $user = Auth::user();
        $this->authorizeManage($user);
        $this->authorizeRecordBank($user, $retentionAction->bank_id);

        $data = $request->validate([
            'execution_result' => ['nullable', 'string', 'max:5000'],
        ]);

        $retentionAction->update([
            'status' => 'completed',
            'executed_at' => now(),
            'execution_result' => $data['execution_result'] ?? 'Action completed and logged.',
        ]);

        $this->audit("Completed retention action #{$retentionAction->id}", 'Compliance', [
            'workflow' => 'retention_action',
            'record_id' => $retentionAction->id,
            'bank_id' => $retentionAction->bank_id,
        ]);

        return response()->json($retentionAction->load(['bank:id,name', 'policy:id,dataset', 'requester:id,name,role', 'approver:id,name,role']));
    }

    public function storeTransferProfile(Request $request)
    {
        $user = Auth::user();
        $this->authorizeManage($user);

        $data = $request->validate([
            'bank_id' => ['nullable', 'integer', 'exists:banks,id'],
            'name' => ['required', 'string', 'max:255'],
            'protocol' => ['required', Rule::in(['sftp'])],
            'environment' => ['required', Rule::in(['development', 'staging', 'production'])],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string'],
            'private_key' => ['nullable', 'string'],
            'remote_path' => ['nullable', 'string', 'max:255'],
            'archive_path' => ['nullable', 'string', 'max:255'],
            'filename_pattern' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $record = BankTransferProfile::create([
            ...$data,
            'bank_id' => $this->resolveBankId($user, $data['bank_id'] ?? null),
            'status' => $data['status'] ?? 'inactive',
            'port' => $data['port'] ?? 22,
        ]);

        $this->audit("Created bank transfer profile #{$record->id}", 'Compliance', [
            'workflow' => 'bank_transfer_profile',
            'record_id' => $record->id,
            'bank_id' => $record->bank_id,
        ]);

        return response()->json($record->load('bank:id,name'), 201);
    }

    public function updateTransferProfile(Request $request, BankTransferProfile $bankTransferProfile)
    {
        $user = Auth::user();
        $this->authorizeManage($user);
        $this->authorizeRecordBank($user, $bankTransferProfile->bank_id);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'environment' => ['nullable', Rule::in(['development', 'staging', 'production'])],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string'],
            'private_key' => ['nullable', 'string'],
            'remote_path' => ['nullable', 'string', 'max:255'],
            'archive_path' => ['nullable', 'string', 'max:255'],
            'filename_pattern' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $bankTransferProfile->update($data);

        $this->audit("Updated bank transfer profile #{$bankTransferProfile->id}", 'Compliance', [
            'workflow' => 'bank_transfer_profile',
            'record_id' => $bankTransferProfile->id,
            'bank_id' => $bankTransferProfile->bank_id,
        ]);

        return response()->json($bankTransferProfile->load('bank:id,name'));
    }

    public function testTransferProfile(BankTransferProfile $bankTransferProfile)
    {
        $user = Auth::user();
        $this->authorizeManage($user);
        $this->authorizeRecordBank($user, $bankTransferProfile->bank_id);

        $run = $this->bankTransfers->testConnection($bankTransferProfile, $user);
        $bankTransferProfile->forceFill(['last_tested_at' => now()])->save();

        $this->audit("Tested bank transfer profile #{$bankTransferProfile->id}", 'Compliance', [
            'workflow' => 'bank_transfer_profile',
            'record_id' => $bankTransferProfile->id,
            'bank_id' => $bankTransferProfile->bank_id,
            'run_status' => $run->status,
        ]);

        return response()->json($run);
    }

    public function syncTransferProfile(BankTransferProfile $bankTransferProfile)
    {
        $user = Auth::user();
        $this->authorizeManage($user);
        $this->authorizeRecordBank($user, $bankTransferProfile->bank_id);

        $run = $this->bankTransfers->sync($bankTransferProfile, $user);

        $this->audit("Ran bank transfer sync for profile #{$bankTransferProfile->id}", 'Compliance', [
            'workflow' => 'bank_transfer_profile',
            'record_id' => $bankTransferProfile->id,
            'bank_id' => $bankTransferProfile->bank_id,
            'run_status' => $run->status,
        ]);

        return response()->json($run);
    }

    protected function authorizeView(?User $user): void
    {
        abort_unless($user && $user->canViewComplianceConsole(), 403, 'You are not allowed to access compliance workflows.');
    }

    protected function authorizeManage(?User $user): void
    {
        abort_unless($user && $user->canManageComplianceConsole(), 403, 'You are not allowed to manage compliance workflows.');
    }

    protected function scopedQuery(Builder $query, User $user): Builder
    {
        if (!$user->canAccessAllBanks() && !empty($user->resolvedBankIds())) {
            $query->whereIn('bank_id', $user->resolvedBankIds());
        }

        return $query;
    }

    protected function authorizeRecordBank(User $user, ?int $bankId): void
    {
        if ($user->canAccessAllBanks()) {
            return;
        }

        abort_if($bankId && !empty($user->resolvedBankIds()) && !in_array((int) $bankId, $user->resolvedBankIds(), true), 403, 'You are not allowed to access records for this bank.');
    }

    protected function resolveBankId(User $user, ?int $requestedBankId): ?int
    {
        if ($user->canAccessAllBanks()) {
            return $requestedBankId;
        }

        $ids = $user->resolvedBankIds();
        return !empty($ids) ? $ids[0] : null;
    }

    protected function resolveAssigneeId(User $user, ?int $bankId, ?int $assignedToUserId): ?int
    {
        if (!$assignedToUserId) {
            return null;
        }

        $assignee = User::query()->findOrFail($assignedToUserId);

        if (!$user->canAccessAllBanks() && empty(array_intersect($assignee->resolvedBankIds(), $user->resolvedBankIds()))) {
            abort(422, 'Assignee must belong to the same bank scope.');
        }

        if ($bankId && !empty($assignee->resolvedBankIds()) && !in_array((int) $bankId, $assignee->resolvedBankIds(), true)) {
            abort(422, 'Assignee must belong to the same bank.');
        }

        return $assignee->id;
    }
}
