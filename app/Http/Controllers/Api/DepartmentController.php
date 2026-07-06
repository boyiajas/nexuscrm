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
            'whatsapp_numbers' => ['nullable', 'array'],
            'whatsapp_numbers.*' => ['string'],
        ]);

        if (empty($data['whatsapp_numbers'])) {
            $settings = \App\Models\SystemSetting::first();
            $default = $settings?->meta_whatsapp_display_phone_number ?: $settings?->twilio_whatsapp_from;
            $data['whatsapp_numbers'] = $default ? [$default] : [];
        }

        return Department::create($data);
    }

    public function update(Request $request, Department $department)
    {
        $this->authorizeManageDepartments();
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'whatsapp_numbers' => ['nullable', 'array'],
            'whatsapp_numbers.*' => ['string'],
        ]);

        if (array_key_exists('whatsapp_numbers', $data) && empty($data['whatsapp_numbers'])) {
            $settings = \App\Models\SystemSetting::first();
            $default = $settings?->meta_whatsapp_display_phone_number ?: $settings?->twilio_whatsapp_from;
            $data['whatsapp_numbers'] = $default ? [$default] : [];
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
