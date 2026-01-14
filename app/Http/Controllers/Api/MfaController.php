<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MfaController extends Controller
{
    public function status()
    {
        $user = Auth::user();

        return [
            'mfa_enabled' => (bool) $user->mfa_enabled,
            'mfa_type'    => $user->mfa_type,
        ];
    }

    public function setupEmail(Request $request)
    {
        $user = $request->user();

        // generate a random 6-digit code, send via email
        $code = random_int(100000, 999999);
        cache()->put("mfa_email_{$user->id}", $code, now()->addMinutes(10));

        // TODO: send email notification here

        return ['message' => 'Verification code sent to your email.'];
    }

    public function verifyEmail(Request $request)
    {
        $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $user = $request->user();
        $cached = cache()->get("mfa_email_{$user->id}");

        if ($cached && $cached == $request->code) {
            $user->update([
                'mfa_enabled' => true,
                'mfa_type'    => 'email',
                'mfa_secret'  => null,
            ]);
            cache()->forget("mfa_email_{$user->id}");

            return ['message' => 'Email MFA enabled.'];
        }

        return response()->json(['message' => 'Invalid or expired code'], 422);
    }

    public function disable(Request $request)
    {
        $user = $request->user();
        $user->update([
            'mfa_enabled' => false,
            'mfa_type'    => null,
            'mfa_secret'  => null,
        ]);

        return ['message' => 'MFA disabled'];
    }
}
