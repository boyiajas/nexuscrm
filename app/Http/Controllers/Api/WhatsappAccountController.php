<?php

namespace App\Http\Controllers\Api;

use App\Concerns\HasAuditLogging;
use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Models\WhatsappAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WhatsappAccountController extends Controller
{
    use HasAuditLogging;

    private function authorizeAdmin(): void
    {
        $user = Auth::user();
        if (!$user || (!$user->canManageSystemSettings() && !$user->canAccessWabaProfileSettings())) {
            abort(403, 'Unauthorized access to WhatsApp profiles.');
        }
    }

    public function index()
    {
        $this->authorizeAdmin();
        $accounts = WhatsappAccount::all()->map(function ($account) {
            return [
                'id' => $account->id,
                'name' => $account->name,
                'app_id' => $account->app_id,
                'app_secret' => $account->app_secret ? '********' : null,
                'access_token' => $account->access_token ? '********' : null,
                'waba_id' => $account->waba_id,
                'phone_number_id' => $account->phone_number_id,
                'display_phone_number' => $account->display_phone_number,
                'webhook_verify_token' => $account->webhook_verify_token ? '********' : null,
            ];
        });
        
        return response()->json($accounts);
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'app_id' => 'required|string|max:255',
            'app_secret' => 'required|string',
            'access_token' => 'required|string',
            'waba_id' => 'required|string|max:255',
            'phone_number_id' => 'required|string|max:255',
            'display_phone_number' => 'nullable|string|max:255',
            'webhook_verify_token' => 'required|string',
        ]);

        $account = WhatsappAccount::create($data);

        $this->audit(
            action: 'Created WhatsApp profile',
            module: 'Settings',
            meta: ['profile_name' => $account->name]
        );

        return response()->json(['message' => 'WhatsApp profile saved successfully.', 'account' => $account]);
    }

    public function update(Request $request, $id)
    {
        $this->authorizeAdmin();

        $account = WhatsappAccount::findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'app_id' => 'required|string|max:255',
            'app_secret' => 'nullable|string',
            'access_token' => 'nullable|string',
            'waba_id' => 'required|string|max:255',
            'phone_number_id' => 'required|string|max:255',
            'display_phone_number' => 'nullable|string|max:255',
            'webhook_verify_token' => 'nullable|string',
        ]);

        // Only update sensitive fields if they were provided (not masked)
        foreach (['app_secret', 'access_token', 'webhook_verify_token'] as $field) {
            if (isset($data[$field]) && strpos($data[$field], '********') === false) {
                $account->$field = $data[$field];
            }
            unset($data[$field]);
        }

        $account->fill($data);
        $account->save();

        $this->audit(
            action: 'Updated WhatsApp profile',
            module: 'Settings',
            meta: ['profile_name' => $account->name]
        );

        return response()->json(['message' => 'WhatsApp profile updated successfully.']);
    }

    public function destroy($id)
    {
        $this->authorizeAdmin();

        $account = WhatsappAccount::findOrFail($id);
        $name = $account->name;
        $account->delete();

        $this->audit(
            action: 'Deleted WhatsApp profile',
            module: 'Settings',
            meta: ['profile_name' => $name]
        );

        return response()->json(['message' => 'WhatsApp profile deleted.']);
    }

    public function activate($id)
    {
        $this->authorizeAdmin();

        $account = WhatsappAccount::findOrFail($id);
        $settings = SystemSetting::firstOrCreate([]);

        $settings->meta_app_id = $account->app_id;
        $settings->meta_app_secret = $account->app_secret;
        $settings->meta_access_token = $account->access_token;
        $settings->meta_whatsapp_business_account_id = $account->waba_id;
        $settings->meta_whatsapp_phone_number_id = $account->phone_number_id;
        $settings->meta_whatsapp_display_phone_number = $account->display_phone_number;
        $settings->meta_webhook_verify_token = $account->webhook_verify_token;
        
        $settings->meta_token_last_rotated_at = now();
        $settings->meta_token_rotation_notes = "Activated profile: {$account->name}";
        $settings->save();

        $this->audit(
            action: 'Activated WhatsApp profile',
            module: 'Settings',
            meta: ['profile_name' => $account->name]
        );

        return response()->json(['message' => "Successfully switched active WhatsApp account to {$account->name}."]);
    }
}
