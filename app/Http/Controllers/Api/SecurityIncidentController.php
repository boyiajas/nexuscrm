<?php

namespace App\Http\Controllers\Api;

use App\Concerns\HasAuditLogging;
use App\Http\Controllers\Controller;
use App\Models\SecurityIncident;
use App\Models\SecurityIncidentEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SecurityIncidentController extends Controller
{
    use HasAuditLogging;

    public function index(Request $request)
    {
        $user = Auth::user();
        $this->authorizeView($user);

        $query = SecurityIncident::with(['bank:id,name', 'reporter:id,name,role', 'assignee:id,name,role'])
            ->latest();

        $this->applyBankScope($query, $user);

        if ($status = $request->get('status')) {
            if (!in_array($status, ['all', ''], true)) {
                $query->where('status', $status);
            }
        }

        if ($type = $request->get('type')) {
            if (!in_array($type, ['all', ''], true)) {
                $query->where('type', $type);
            }
        }

        if ($severity = $request->get('severity')) {
            if (!in_array($severity, ['all', ''], true)) {
                $query->where('severity', $severity);
            }
        }

        if ($request->filled('bank_id') && $user->canAccessAllBanks()) {
            $query->where('bank_id', (int) $request->get('bank_id'));
        }

        if ($q = trim((string) $request->get('q'))) {
            $query->where(function ($inner) use ($q) {
                $inner->where('reference', 'like', "%{$q}%")
                    ->orWhere('title', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%")
                    ->orWhere('affected_module', 'like', "%{$q}%");
            });
        }

        return $query->paginate((int) $request->get('per_page', 15));
    }

    public function show(SecurityIncident $securityIncident)
    {
        $user = Auth::user();
        $this->authorizeView($user);
        $this->authorizeIncidentBank($user, $securityIncident);

        return response()->json(
            $securityIncident->load([
                'bank:id,name',
                'reporter:id,name,role,email',
                'assignee:id,name,role,email',
                'events.user:id,name,role',
            ])
        );
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        abort_unless($user && $user->canCreateSecurityIncidents(), 403, 'You are not allowed to create security incidents.');

        $data = $request->validate([
            'bank_id' => ['nullable', 'integer', 'exists:banks,id'],
            'type' => ['required', 'string', Rule::in(['security_breach', 'privacy_breach', 'malware_detection', 'export_abuse', 'unauthorized_access', 'whatsapp_misdirect', 'system_outage', 'other'])],
            'severity' => ['required', 'string', Rule::in(['low', 'medium', 'high', 'critical'])],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'affected_module' => ['nullable', 'string', 'max:100'],
            'affected_records_count' => ['nullable', 'integer', 'min:0'],
            'suspected_personal_data_exposed' => ['sometimes', 'boolean'],
            'regulator_notification_required' => ['sometimes', 'boolean'],
            'bank_notification_required' => ['sometimes', 'boolean'],
            'assigned_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $bankId = $this->resolveRequestedBankId($user, $data['bank_id'] ?? null);
        $assigneeId = $this->resolveAssigneeId($user, $bankId, $data['assigned_to_user_id'] ?? null);

        $incident = SecurityIncident::create([
            'reference' => $this->generateReference(),
            'bank_id' => $bankId,
            'type' => $data['type'],
            'severity' => $data['severity'],
            'status' => 'open',
            'title' => $data['title'],
            'description' => $data['description'],
            'affected_module' => $data['affected_module'] ?? null,
            'affected_records_count' => $data['affected_records_count'] ?? null,
            'suspected_personal_data_exposed' => (bool) ($data['suspected_personal_data_exposed'] ?? false),
            'regulator_notification_required' => (bool) ($data['regulator_notification_required'] ?? false),
            'bank_notification_required' => (bool) ($data['bank_notification_required'] ?? false),
            'reported_by_user_id' => $user->id,
            'assigned_to_user_id' => $assigneeId,
        ]);

        $incident->events()->create([
            'user_id' => $user->id,
            'event_type' => 'created',
            'note' => 'Incident created.',
            'meta' => [
                'severity' => $incident->severity,
                'type' => $incident->type,
            ],
        ]);

        $this->audit(
            action: "Created security incident {$incident->reference}",
            module: 'Security Incidents',
            meta: [
                'incident_id' => $incident->id,
                'reference' => $incident->reference,
                'bank_id' => $incident->bank_id,
                'severity' => $incident->severity,
                'type' => $incident->type,
            ]
        );

        return response()->json($incident->load(['bank:id,name', 'reporter:id,name,role', 'assignee:id,name,role']), 201);
    }

    public function update(Request $request, SecurityIncident $securityIncident)
    {
        $user = Auth::user();
        abort_unless($user && $user->canManageSecurityIncidents(), 403, 'You are not allowed to manage security incidents.');
        $this->authorizeIncidentBank($user, $securityIncident);

        $data = $request->validate([
            'status' => ['sometimes', 'string', Rule::in(['open', 'investigating', 'contained', 'notified', 'resolved', 'closed'])],
            'severity' => ['sometimes', 'string', Rule::in(['low', 'medium', 'high', 'critical'])],
            'assigned_to_user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'regulator_notification_required' => ['sometimes', 'boolean'],
            'bank_notification_required' => ['sometimes', 'boolean'],
            'suspected_personal_data_exposed' => ['sometimes', 'boolean'],
            'affected_records_count' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:5000'],
        ]);

        $changes = [];

        if (array_key_exists('status', $data) && $data['status'] !== $securityIncident->status) {
            $changes['status'] = [$securityIncident->status, $data['status']];
            $securityIncident->status = $data['status'];

            if ($data['status'] === 'contained' && !$securityIncident->contained_at) {
                $securityIncident->contained_at = now();
            }

            if ($data['status'] === 'closed') {
                $securityIncident->closed_at = now();
            }
        }

        if (array_key_exists('severity', $data) && $data['severity'] !== $securityIncident->severity) {
            $changes['severity'] = [$securityIncident->severity, $data['severity']];
            $securityIncident->severity = $data['severity'];
        }

        if (array_key_exists('assigned_to_user_id', $data)) {
            $resolvedAssigneeId = $this->resolveAssigneeId($user, $securityIncident->bank_id, $data['assigned_to_user_id']);
            if ((int) $resolvedAssigneeId !== (int) $securityIncident->assigned_to_user_id) {
                $changes['assigned_to_user_id'] = [$securityIncident->assigned_to_user_id, $resolvedAssigneeId];
                $securityIncident->assigned_to_user_id = $resolvedAssigneeId;
            }
        }

        foreach ([
            'regulator_notification_required',
            'bank_notification_required',
            'suspected_personal_data_exposed',
            'affected_records_count',
        ] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== $securityIncident->{$field}) {
                $changes[$field] = [$securityIncident->{$field}, $data[$field]];
                $securityIncident->{$field} = $data[$field];
            }
        }

        $securityIncident->save();

        if (!empty($changes) || !empty($data['note'])) {
            $securityIncident->events()->create([
                'user_id' => $user->id,
                'event_type' => 'updated',
                'note' => $data['note'] ?? null,
                'meta' => [
                    'changes' => $changes,
                ],
            ]);
        }

        $this->audit(
            action: "Updated security incident {$securityIncident->reference}",
            module: 'Security Incidents',
            meta: [
                'incident_id' => $securityIncident->id,
                'reference' => $securityIncident->reference,
                'changes' => $changes,
            ]
        );

        return response()->json($securityIncident->load(['bank:id,name', 'reporter:id,name,role', 'assignee:id,name,role']));
    }

    public function addEvent(Request $request, SecurityIncident $securityIncident)
    {
        $user = Auth::user();
        abort_unless($user && $user->canManageSecurityIncidents(), 403, 'You are not allowed to add security incident events.');
        $this->authorizeIncidentBank($user, $securityIncident);

        $data = $request->validate([
            'event_type' => ['required', 'string', Rule::in(['note', 'triaged', 'contained', 'notification_sent', 'resolved', 'evidence_preserved'])],
            'note' => ['required', 'string', 'max:5000'],
        ]);

        $event = $securityIncident->events()->create([
            'user_id' => $user->id,
            'event_type' => $data['event_type'],
            'note' => $data['note'],
        ]);

        if ($data['event_type'] === 'contained' && !$securityIncident->contained_at) {
            $securityIncident->contained_at = now();
            if ($securityIncident->status === 'open') {
                $securityIncident->status = 'contained';
            }
            $securityIncident->save();
        }

        $this->audit(
            action: "Added {$data['event_type']} event to security incident {$securityIncident->reference}",
            module: 'Security Incidents',
            meta: [
                'incident_id' => $securityIncident->id,
                'event_id' => $event->id,
                'event_type' => $data['event_type'],
            ]
        );

        return response()->json($event->load('user:id,name,role'), 201);
    }

    protected function authorizeView(?User $user): void
    {
        abort_unless($user && $user->canViewSecurityIncidents(), 403, 'You are not allowed to access security incidents.');
    }

    protected function authorizeIncidentBank(User $user, SecurityIncident $incident): void
    {
        if ($user->canAccessAllBanks()) {
            return;
        }

        if ($incident->bank_id && $user->resolvedBankId() && (int) $incident->bank_id !== $user->resolvedBankId()) {
            abort(403, 'You are not allowed to access this incident.');
        }
    }

    protected function applyBankScope($query, User $user): void
    {
        if (!$user->canAccessAllBanks() && $user->resolvedBankId()) {
            $query->where(function ($inner) use ($user) {
                $inner->whereNull('bank_id')
                    ->orWhere('bank_id', $user->resolvedBankId());
            });
        }
    }

    protected function resolveRequestedBankId(User $user, ?int $requestedBankId): ?int
    {
        if ($user->canAccessAllBanks()) {
            return $requestedBankId;
        }

        return $user->resolvedBankId();
    }

    protected function resolveAssigneeId(User $user, ?int $bankId, ?int $requestedAssigneeId): ?int
    {
        if (!$requestedAssigneeId) {
            return null;
        }

        $assignee = User::query()->findOrFail($requestedAssigneeId);
        if ($bankId && !$assignee->canAccessAllBanks() && (int) $assignee->bank_id !== (int) $bankId) {
            abort(422, 'Assigned incident owner must belong to the same bank.');
        }

        if (!$assignee->canViewSecurityIncidents()) {
            abort(422, 'Assigned incident owner must have security incident access.');
        }

        return $assignee->id;
    }

    protected function generateReference(): string
    {
        return 'INC-' . now()->format('Ymd') . '-' . str_pad((string) (SecurityIncident::query()->count() + 1), 5, '0', STR_PAD_LEFT);
    }
}
