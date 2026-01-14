<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index()
    {
        return Department::orderBy('name')->paginate(20);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'whatsapp_numbers' => ['nullable', 'array'],
            'whatsapp_numbers.*' => ['string'],
        ]);

        if (empty($data['whatsapp_numbers'])) {
            $default = optional(\App\Models\SystemSetting::first())->twilio_whatsapp_from;
            $data['whatsapp_numbers'] = $default ? [$default] : [];
        }

        return Department::create($data);
    }

    public function update(Request $request, Department $department)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'whatsapp_numbers' => ['nullable', 'array'],
            'whatsapp_numbers.*' => ['string'],
        ]);

        if (array_key_exists('whatsapp_numbers', $data) && empty($data['whatsapp_numbers'])) {
            $default = optional(\App\Models\SystemSetting::first())->twilio_whatsapp_from;
            $data['whatsapp_numbers'] = $default ? [$default] : [];
        }

        $department->update($data);

        return $department;
    }

    public function destroy(Department $department)
    {
        $department->delete();
        return response()->noContent();
    }
}
