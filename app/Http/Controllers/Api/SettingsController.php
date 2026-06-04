<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    public function branding()
    {
        return response()->json($this->transformBranding(SystemSetting::first()));
    }

    public function show()
    {
        $this->authorizeAdmin();

        return response()->json($this->transformSettings(SystemSetting::first()));
    }

    public function update(Request $request)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'app_name'               => ['sometimes', 'nullable', 'string', 'max:255'],
            'app_short_name'         => ['sometimes', 'nullable', 'string', 'max:50'],
            'app_tagline'            => ['sometimes', 'nullable', 'string', 'max:255'],
            'company_name'           => ['sometimes', 'nullable', 'string', 'max:255'],
            'support_email'          => ['sometimes', 'nullable', 'email', 'max:255'],
            'support_phone'          => ['sometimes', 'nullable', 'string', 'max:255'],
            'app_logo'               => ['sometimes', 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'remove_app_logo'        => ['sometimes', 'boolean'],
            'twilio_sid'             => ['sometimes', 'nullable', 'string'],
            'twilio_auth_token'      => ['sometimes', 'nullable', 'string'],
            'twilio_msg_sid'         => ['sometimes', 'nullable', 'string'],
            'twilio_template_sid'    => ['sometimes', 'nullable', 'string'],
            'twilio_whatsapp_from'   => ['sometimes', 'nullable', 'string'],
            'twilio_status_callback' => ['sometimes', 'nullable', 'string'],
            'whatsapp_provider'      => ['sometimes', 'nullable', 'string', 'max:50'],
            'meta_app_id'            => ['sometimes', 'nullable', 'string'],
            'meta_app_secret'        => ['sometimes', 'nullable', 'string'],
            'meta_access_token'      => ['sometimes', 'nullable', 'string'],
            'meta_whatsapp_business_account_id' => ['sometimes', 'nullable', 'string'],
            'meta_whatsapp_phone_number_id' => ['sometimes', 'nullable', 'string'],
            'meta_whatsapp_display_phone_number' => ['sometimes', 'nullable', 'string'],
            'meta_webhook_verify_token' => ['sometimes', 'nullable', 'string'],
        ]);

        $settings = SystemSetting::firstOrCreate([]);

        if (($data['remove_app_logo'] ?? false) && $settings->app_logo_path) {
            Storage::disk('public')->delete($settings->app_logo_path);
            $data['app_logo_path'] = null;
        }

        unset($data['remove_app_logo']);

        if ($request->hasFile('app_logo')) {
            if ($settings->app_logo_path) {
                Storage::disk('public')->delete($settings->app_logo_path);
            }
            $data['app_logo_path'] = $request->file('app_logo')->store('branding', 'public');
        }

        unset($data['app_logo']);

        $settings->fill($data);
        $settings->save();

        return response()->json($this->transformSettings($settings));
    }

    protected function transformSettings(?SystemSetting $settings): array
    {
        if (!$settings) {
            return [
                'app_name' => 'NexusCRM',
                'app_short_name' => 'NC',
                'app_tagline' => 'Mini CRM Console',
                'company_name' => null,
                'support_email' => null,
                'support_phone' => null,
                'app_logo_path' => null,
                'app_logo_url' => null,
                'twilio_sid' => null,
                'twilio_auth_token' => null,
                'twilio_msg_sid' => null,
                'twilio_template_sid' => null,
                'twilio_whatsapp_from' => null,
                'twilio_status_callback' => null,
                'whatsapp_provider' => 'meta',
                'meta_app_id' => config('services.meta_whatsapp.app_id'),
                'meta_app_secret' => config('services.meta_whatsapp.app_secret'),
                'meta_access_token' => config('services.meta_whatsapp.access_token'),
                'meta_whatsapp_business_account_id' => config('services.meta_whatsapp.business_account_id'),
                'meta_whatsapp_phone_number_id' => config('services.meta_whatsapp.phone_number_id'),
                'meta_whatsapp_display_phone_number' => config('services.meta_whatsapp.display_phone_number'),
                'meta_webhook_verify_token' => config('services.meta_whatsapp.verify_token'),
            ];
        }

        return [
            'app_name' => $settings->app_name ?: 'NexusCRM',
            'app_short_name' => $settings->app_short_name ?: 'NC',
            'app_tagline' => $settings->app_tagline ?: 'Mini CRM Console',
            'company_name' => $settings->company_name,
            'support_email' => $settings->support_email,
            'support_phone' => $settings->support_phone,
            'app_logo_path' => $settings->app_logo_path,
            'app_logo_url' => $settings->app_logo_path ? Storage::disk('public')->url($settings->app_logo_path) : null,
            'twilio_sid' => $settings->twilio_sid,
            'twilio_auth_token' => $settings->twilio_auth_token,
            'twilio_msg_sid' => $settings->twilio_msg_sid,
            'twilio_template_sid' => $settings->twilio_template_sid,
            'twilio_whatsapp_from' => $settings->twilio_whatsapp_from,
            'twilio_status_callback' => $settings->twilio_status_callback,
            'whatsapp_provider' => $settings->whatsapp_provider ?: 'meta',
            'meta_app_id' => $settings->meta_app_id ?: config('services.meta_whatsapp.app_id'),
            'meta_app_secret' => $settings->meta_app_secret ?: config('services.meta_whatsapp.app_secret'),
            'meta_access_token' => $settings->meta_access_token ?: config('services.meta_whatsapp.access_token'),
            'meta_whatsapp_business_account_id' => $settings->meta_whatsapp_business_account_id ?: config('services.meta_whatsapp.business_account_id'),
            'meta_whatsapp_phone_number_id' => $settings->meta_whatsapp_phone_number_id ?: config('services.meta_whatsapp.phone_number_id'),
            'meta_whatsapp_display_phone_number' => $settings->meta_whatsapp_display_phone_number ?: config('services.meta_whatsapp.display_phone_number'),
            'meta_webhook_verify_token' => $settings->meta_webhook_verify_token ?: config('services.meta_whatsapp.verify_token'),
        ];
    }

    protected function transformBranding(?SystemSetting $settings): array
    {
        $all = $this->transformSettings($settings);

        return [
            'app_name' => $all['app_name'],
            'app_short_name' => $all['app_short_name'],
            'app_tagline' => $all['app_tagline'],
            'company_name' => $all['company_name'],
            'support_email' => $all['support_email'],
            'support_phone' => $all['support_phone'],
            'app_logo_url' => $all['app_logo_url'],
        ];
    }

    private function authorizeAdmin(): void
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'SUPER_ADMIN') {
            abort(403, 'Only SUPER_ADMIN can manage system settings.');
        }
    }
}
