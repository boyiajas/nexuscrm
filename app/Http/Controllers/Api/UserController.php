<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Services\UserSessionTracker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index()
    {
        $this->authorizeManageUsers();
        return User::with(['bank', 'roles:id,code,name,whatsapp_daily_limit'])->orderBy('name')->paginate(20);
    }

    public function assignees()
    {
        $user = auth()->user();
        if (!$user || !$user->canManageOperationalData()) {
            abort(403, 'You are not allowed to assign portfolios.');
        }

        $query = User::query()
            ->select(['id', 'name', 'email', 'role', 'bank_id'])
            ->where('status', 'Active')
            ->whereIn('role', [
                User::ROLE_MANAGER,
                User::ROLE_CALL_CENTRE_MANAGER,
                User::ROLE_TEAM_LEADER,
                User::ROLE_AGENT,
                User::ROLE_STAFF_LEGACY,
            ])
            ->orderBy('name');

        if (!$user->canAccessAllBanks() && $user->resolvedBankId()) {
            $query->where('bank_id', $user->resolvedBankId());
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $this->authorizeManageUsers();
        $data = $request->validate([
            'name'              => ['required', 'string', 'max:255'],
            'email'             => ['required', 'email', 'max:255', 'unique:users,email'],
            'bank_id'           => ['nullable', 'integer', 'exists:banks,id'],
            'username'          => ['nullable', 'string', 'max:255'],
            'first_name'        => ['nullable', 'string', 'max:255'],
            'middle_initial'    => ['nullable', 'string', 'max:1'],
            'last_name'         => ['nullable', 'string', 'max:255'],
            'primary_phone'     => ['nullable', 'string', 'max:255'],
            'secondary_phone'   => ['nullable', 'string', 'max:255'],
            'inactivity_timeout'=> ['nullable', 'integer', 'min:1'],
            'is_provider'       => ['sometimes', 'boolean'],
            'is_time_clock_user'=> ['sometimes', 'boolean'],
            'password'          => ['required', 'string', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
            'role'              => ['required', Rule::in(User::ALL_ROLES)],
            'role_ids'          => ['sometimes', 'array', 'min:1'],
            'role_ids.*'        => ['integer', 'exists:roles,id'],
            'department'        => ['nullable', 'string', 'max:255'],
            'department_id'     => ['nullable', 'integer', 'exists:departments,id'],
            'status'            => ['required', Rule::in(['Active', 'Inactive'])],
        ]);

        $roleIds = $this->normalizeRoleIds($data['role_ids'] ?? null, $data['role'] ?? null);
        $data['role'] = $this->resolvePrimaryRoleCode($roleIds, $data['role'] ?? null);
        $data['password'] = Hash::make($data['password']);
        $data['password_changed_at'] = now();
        $data['password_reset_required'] = true;
        $this->syncDepartmentFields($data);
        $data['bank_id'] = $this->resolveRequestedBankId($data['role'], $data['bank_id'] ?? null);
        if (($data['status'] ?? 'Active') === 'Inactive') {
            $data['deactivated_at'] = now();
            $data['deactivated_by_user_id'] = auth()->id();
        }

        $user = User::create($data);
        $this->syncDepartmentMembership($user, $data['department_id'] ?? null);
        $this->syncUserRoles($user, $roleIds);

        return response()->json($user->load(['bank', 'roles:id,code,name,whatsapp_daily_limit']), 201);
    }

    public function update(Request $request, User $user)
    {
        $this->authorizeManageUsers();
        $data = $request->validate([
            'name'              => ['sometimes', 'string', 'max:255'],
            'email'             => ['sometimes', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'bank_id'           => ['nullable', 'integer', 'exists:banks,id'],
            'username'          => ['nullable', 'string', 'max:255'],
            'first_name'        => ['nullable', 'string', 'max:255'],
            'middle_initial'    => ['nullable', 'string', 'max:1'],
            'last_name'         => ['nullable', 'string', 'max:255'],
            'primary_phone'     => ['nullable', 'string', 'max:255'],
            'secondary_phone'   => ['nullable', 'string', 'max:255'],
            'inactivity_timeout'=> ['nullable', 'integer', 'min:1'],
            'is_provider'       => ['sometimes', 'boolean'],
            'is_time_clock_user'=> ['sometimes', 'boolean'],
            'password'          => ['nullable', 'string', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
            'role'              => ['sometimes', Rule::in(User::ALL_ROLES)],
            'role_ids'          => ['sometimes', 'array', 'min:1'],
            'role_ids.*'        => ['integer', 'exists:roles,id'],
            'department'        => ['nullable', 'string', 'max:255'],
            'department_id'     => ['nullable', 'integer', 'exists:departments,id'],
            'status'            => ['sometimes', Rule::in(['Active', 'Inactive'])],
        ]);

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
            $data['password_changed_at'] = now();
            $data['password_reset_required'] = true;
            $user->tokens()->delete();
            UserSessionTracker::closeAllForUser($user, 'password_changed_by_admin');
        } else {
            unset($data['password']);
        }

        $roleIds = array_key_exists('role_ids', $data)
            ? $this->normalizeRoleIds($data['role_ids'] ?? null, $data['role'] ?? $user->role)
            : null;
        $data['role'] = $this->resolvePrimaryRoleCode($roleIds, $data['role'] ?? $user->role);

        $this->syncDepartmentFields($data);
        $role = $data['role'] ?? $user->role;
        $data['bank_id'] = $this->resolveRequestedBankId($role, $data['bank_id'] ?? $user->bank_id);

        if (array_key_exists('status', $data)) {
            if ($data['status'] === 'Inactive') {
                $data['deactivated_at'] = now();
                $data['deactivated_by_user_id'] = auth()->id();
                $user->tokens()->delete();
                UserSessionTracker::closeAllForUser($user, 'user_deactivated');
            } elseif ($data['status'] === 'Active') {
                $data['deactivated_at'] = null;
                $data['deactivated_by_user_id'] = null;
            }
        }

        $user->update($data);
        if (is_array($roleIds)) {
            $this->syncUserRoles($user, $roleIds);
        }
        if (array_key_exists('department_id', $data) || array_key_exists('department', $data)) {
            $this->syncDepartmentMembership($user, $data['department_id'] ?? $user->department_id);
        }

        return $user->load(['bank', 'roles:id,code,name,whatsapp_daily_limit']);
    }

    public function destroy(User $user)
    {
        $this->authorizeManageUsers();
        if ($user->id === auth()->id()) {
            return response()->json(['message' => 'Cannot delete yourself'], 422);
        }

        $user->tokens()->delete();
        UserSessionTracker::closeAllForUser($user, 'user_deleted');
        $user->delete();

        return response()->noContent();
    }

    protected function syncDepartmentFields(array &$data): void
    {
        if (array_key_exists('department_id', $data)) {
            if (empty($data['department_id'])) {
                $data['department_id'] = null;
                $data['department'] = null;
                return;
            }

            $department = Department::find($data['department_id']);
            $data['department'] = $department?->name;
            return;
        }

        if (array_key_exists('department', $data)) {
            if (empty($data['department'])) {
                $data['department'] = null;
                $data['department_id'] = null;
                return;
            }

            $department = Department::query()->where('name', $data['department'])->first();
            $data['department'] = $department?->name ?? $data['department'];
            $data['department_id'] = $department?->id;
        }
    }

    protected function syncDepartmentMembership(User $user, $departmentId): void
    {
        if (empty($departmentId)) {
            $user->departments()->sync([]);
            return;
        }

        $user->departments()->sync([(int) $departmentId]);
    }

    protected function authorizeManageUsers(): void
    {
        $user = auth()->user();
        if (!$user || !$user->canManageUsersAndDepartments()) {
            abort(403, 'You are not allowed to manage users.');
        }
    }

    protected function resolveRequestedBankId(string $role, $requestedBankId): ?int
    {
        if (in_array($role, [User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN], true)) {
            return $requestedBankId ? (int) $requestedBankId : null;
        }

        if (!$requestedBankId) {
            abort(422, 'A bank is required for this user role.');
        }

        if (!Bank::query()->whereKey($requestedBankId)->exists()) {
            abort(422, 'The selected bank is invalid.');
        }

        return (int) $requestedBankId;
    }

    protected function normalizeRoleIds(?array $roleIds, ?string $fallbackRole): array
    {
        $normalized = collect($roleIds ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (!empty($normalized)) {
            return $normalized;
        }

        if ($fallbackRole) {
            $fallbackId = Role::query()->where('code', $fallbackRole)->value('id');
            if ($fallbackId) {
                return [(int) $fallbackId];
            }
        }

        abort(422, 'At least one role must be selected.');
    }

    protected function resolvePrimaryRoleCode(?array $roleIds, ?string $fallbackRole): string
    {
        if (!empty($roleIds)) {
            $codes = Role::query()->whereIn('id', $roleIds)->pluck('code')->map(fn ($code) => (string) $code)->all();
            $primary = User::resolvePrimaryRoleFromCodes($codes);
            if ($primary) {
                return $primary;
            }
        }

        return $fallbackRole ?: User::ROLE_AGENT;
    }

    protected function syncUserRoles(User $user, array $roleIds): void
    {
        $user->roles()->sync($roleIds);
    }
}
