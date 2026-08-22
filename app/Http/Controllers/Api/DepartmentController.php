<?php

namespace App\Http\Controllers\Api;

use App\Concerns\HasAuditLogging;
use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    use HasAuditLogging;
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
            'whatsapp_account_id' => ['nullable', 'integer'],
        ]);

        if (empty($data['primary_whatsapp_number'])) {
            $settings = \App\Models\SystemSetting::first();
            $default = $settings?->meta_whatsapp_display_phone_number ?: $settings?->twilio_whatsapp_from;
            $data['primary_whatsapp_number'] = $default ?: null;
        }

        if (!isset($data['secondary_whatsapp_numbers'])) {
            $data['secondary_whatsapp_numbers'] = [];
        }

        $dept = Department::create($data);

        $this->audit(
            action: "Created department '{$dept->name}'",
            module: 'Departments',
            meta: ['department_id' => $dept->id]
        );

        return $dept;
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
            'whatsapp_account_id' => ['nullable', 'integer'],
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

        $this->audit(
            action: "Updated department '{$department->name}'",
            module: 'Departments',
            meta: ['department_id' => $department->id]
        );

        return $department;
    }

    public function destroy(Department $department)
    {
        $deptName = $department->name;
        $deptId = $department->id;
        $department->delete();

        $this->audit(
            action: "Deleted department '{$deptName}'",
            module: 'Departments',
            meta: ['department_id' => $deptId]
        );

        return response()->noContent();
    }

    protected function authorizeManageDepartments(): void
    {
        $user = Auth::user();
        if (!$user || !$user->canManageDepartments()) {
            abort(403, 'You are not allowed to manage departments.');
        }
    }
}
