<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeManageRoles($request);

        $query = Role::query()
            ->with('permissions')
            ->withCount('users')
            ->orderBy('name');

        if ($search = trim((string) $request->get('search', ''))) {
            $query->where(function ($inner) use ($search) {
                $inner->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        if ($request->boolean('all')) {
            return response()->json($query->get());
        }

        $perPage = max(1, min((int) $request->get('per_page', 20), 200));

        return response()->json($query->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeManageRoles($request);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:255', 'unique:roles,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'whatsapp_daily_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'watermark_enabled' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ]);

        $data['code'] = $this->normalizeRoleCode($data['code']);
        $data['is_active'] = $data['is_active'] ?? true;
        $data['watermark_enabled'] = $data['watermark_enabled'] ?? true;
        $data['is_system'] = false;

        $permissions = $data['permissions'] ?? [];
        unset($data['permissions']);

        $role = Role::create($data);

        if (!empty($permissions)) {
            $permissionIds = Permission::whereIn('code', $permissions)->orWhereIn('id', $permissions)->pluck('id')->all();
            $role->permissions()->sync($permissionIds);
        }

        return response()->json($role->load('permissions')->loadCount('users'), 201);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $this->authorizeManageRoles($request);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:255', Rule::unique('roles', 'code')->ignore($role->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'whatsapp_daily_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'watermark_enabled' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ]);

        $data['code'] = $this->normalizeRoleCode($data['code']);
        
        $permissions = $data['permissions'] ?? null;
        unset($data['permissions']);

        $role->update($data);

        if (is_array($permissions)) {
            $permissionIds = Permission::whereIn('code', $permissions)->orWhereIn('id', $permissions)->pluck('id')->all();
            $role->permissions()->sync($permissionIds);
        }

        return response()->json($role->fresh(['permissions'])->loadCount('users'));
    }

    public function permissions(Request $request): JsonResponse
    {
        $this->authorizeManageRoles($request);
        $permissions = Permission::query()->orderBy('module')->orderBy('name')->get();
        return response()->json($permissions);
    }

    public function toggleWatermark(Request $request, Role $role): JsonResponse
    {
        $this->authorizeManageRoles($request);

        $data = $request->validate([
            'watermark_enabled' => ['required', 'boolean'],
        ]);

        $role->update([
            'watermark_enabled' => (bool) $data['watermark_enabled'],
        ]);

        return response()->json($role->fresh()->loadCount('users'));
    }

    public function destroy(Request $request, Role $role): JsonResponse
    {
        $this->authorizeManageRoles($request);

        if ($role->users()->exists()) {
            return response()->json([
                'message' => 'This role cannot be deleted because it is assigned to one or more users.',
            ], 422);
        }

        $role->delete();

        return response()->json(['message' => 'Role deleted successfully.']);
    }

    protected function authorizeManageRoles(Request $request): void
    {
        $user = $request->user();
        if (!$user || !$user->canManageUsersAndDepartments()) {
            abort(403, 'You are not allowed to manage roles.');
        }
    }

    protected function normalizeRoleCode(string $code): string
    {
        return strtoupper(trim(preg_replace('/[^A-Za-z0-9]+/', '_', $code), '_'));
    }
}
