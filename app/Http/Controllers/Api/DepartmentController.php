<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        if (!$user) {
            abort(401);
        }

        $query = Department::query()->orderBy('name');
        $userDepartmentIds = $user->resolvedDepartmentIds();
        if (!$user->canManageSystemSettings() && !empty($userDepartmentIds)) {
            $query->whereIn('id', $userDepartmentIds);
        }

        $perPage = (int) request()->get('per_page', 20);
        return $query->paginate($perPage);
    }

    public function store(Request $request)
    {
        $this->authorizeManageDepartments();
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'primary_whatsapp_number' => ['nullable', 'string'],
            'secondary_whatsapp_numbers' => ['nullable', 'array'],
            'secondary_whatsapp_numbers.*' => ['string'],
        ]);

        if (empty($data['primary_whatsapp_number'])) {
            $settings = \App\Models\SystemSetting::first();
            $default = $settings?->meta_whatsapp_display_phone_number ?: $settings?->twilio_whatsapp_from;
            $data['primary_whatsapp_number'] = $default ?: null;
        }

        if (!isset($data['secondary_whatsapp_numbers'])) {
            $data['secondary_whatsapp_numbers'] = [];
        }

        return Department::create($data);
    }

    public function update(Request $request, Department $department)
    {
        $this->authorizeManageDepartments();
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'primary_whatsapp_number' => ['nullable', 'string'],
            'secondary_whatsapp_numbers' => ['nullable', 'array'],
            'secondary_whatsapp_numbers.*' => ['string'],
        ]);

        if (array_key_exists('primary_whatsapp_number', $data) && empty($data['primary_whatsapp_number'])) {
            $settings = \App\Models\SystemSetting::first();
            $default = $settings?->meta_whatsapp_display_phone_number ?: $settings?->twilio_whatsapp_from;
            $data['primary_whatsapp_number'] = $default ?: null;
        }

        if (array_key_exists('secondary_whatsapp_numbers', $data) && $data['secondary_whatsapp_numbers'] === null) {
            $data['secondary_whatsapp_numbers'] = [];
        }

        $department->update($data);

        return $department;
    }

    public function destroy(Department $department)
    {
        $this->authorizeManageDepartments();
        $department->delete();
        return response()->noContent();
    }

    protected function authorizeManageDepartments(): void
    {
        $user = Auth::user();
        if (!$user || !$user->canManageUsersAndDepartments()) {
            abort(403, 'You are not allowed to manage departments.');
        }
    }
}
