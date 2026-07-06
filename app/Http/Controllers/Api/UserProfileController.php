<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\TransientToken;

class UserProfileController extends Controller
{
    public function show(Request $request)
    {
        return $request->user()->load('departments:id,name');
    }

    public function departmentOptions(Request $request)
    {
        $user = $request->user();

        if ($user->role === \App\Models\User::ROLE_STAFF_LEGACY) {
            return response()->json(
                $user->departments()
                    ->orderBy('name')
                    ->get(['departments.id', 'departments.name'])
            );
        }

        return response()->json(
            Department::query()
                ->orderBy('name')
                ->get(['id', 'name'])
        );
    }

    public function sessions(Request $request)
    {
        $user = $request->user();

        return response()->json(
            $user->loginSessions()
                ->orderByDesc('authenticated_at')
                ->limit(10)
                ->get()
                ->map(function ($session) use ($user) {
                    $currentToken = $user->currentAccessToken();
                    $currentTokenId = $currentToken instanceof TransientToken ? null : $currentToken?->id;

                    return [
                        'id' => $session->id,
                        'session_uuid' => $session->session_uuid,
                        'ip_address' => $session->ip_address,
                        'user_agent' => $session->user_agent,
                        'authentication_method' => $session->authentication_method,
                        'authenticated_at' => optional($session->authenticated_at)->toDateTimeString(),
                        'last_activity_at' => optional($session->last_activity_at)->toDateTimeString(),
                        'logged_out_at' => optional($session->logged_out_at)->toDateTimeString(),
                        'logout_reason' => $session->logout_reason,
                        'is_current' => $currentTokenId && $session->personal_access_token_id === $currentTokenId,
                    ];
                })
                ->values()
        );
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'username' => ['nullable', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'middle_initial' => ['nullable', 'string', 'max:1'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'primary_phone' => ['nullable', 'string', 'max:255'],
            'secondary_phone' => ['nullable', 'string', 'max:255'],
            'inactivity_timeout' => ['nullable', 'integer', 'min:1'],
            'is_provider' => ['sometimes', 'boolean'],
            'is_time_clock_user' => ['sometimes', 'boolean'],
            'department_ids' => ['sometimes', 'array'],
            'department_ids.*' => ['integer', 'exists:departments,id'],
        ]);

        $selectedDepartmentIds = null;
        if (array_key_exists('department_ids', $data)) {
            $selectedDepartmentIds = array_values(array_unique(array_map('intval', $data['department_ids'] ?? [])));

            if ($user->role === \App\Models\User::ROLE_STAFF_LEGACY) {
                $currentIds = $user->resolvedDepartmentIds();
                sort($currentIds);
                $incoming = $selectedDepartmentIds;
                sort($incoming);

                if ($incoming !== $currentIds) {
                    abort(403, 'Staff users cannot change their department assignment.');
                }
            }

            unset($data['department_ids']);
        }

        $user->update($data);

        if (is_array($selectedDepartmentIds)) {
            $user->departments()->sync($selectedDepartmentIds);

            $primaryDepartment = null;
            if (!empty($selectedDepartmentIds)) {
                $primaryDepartment = Department::query()
                    ->whereKey($selectedDepartmentIds[0])
                    ->first();
            }

            $user->forceFill([
                'department_id' => $primaryDepartment?->id,
                'department' => $primaryDepartment?->name,
            ])->save();
        }

        return $user->fresh()->load('departments:id,name');
    }
}
