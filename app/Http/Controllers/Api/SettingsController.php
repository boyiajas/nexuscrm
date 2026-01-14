<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SettingsController extends Controller
{
    public function show()
    {
        $this->authorizeAdmin();

        $settings = SystemSetting::first();

        return response()->json($settings);
    }

    public function update(Request $request)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'twilio_sid'             => ['required', 'string'],
            'twilio_auth_token'      => ['required', 'string'],
            'twilio_msg_sid'         => ['nullable', 'string'],
            'twilio_template_sid'    => ['nullable', 'string'],
            'twilio_whatsapp_from'   => ['nullable', 'string'],
            'twilio_status_callback' => ['nullable', 'string'],
        ]);

        $settings = SystemSetting::first();

        if ($settings) {
            $settings->update($data);
        } else {
            $settings = SystemSetting::create($data);
        }

        return response()->json($settings);
    }

    private function authorizeAdmin(): void
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'SUPER_ADMIN') {
            abort(403, 'Only SUPER_ADMIN can manage system settings.');
        }
    }
}
