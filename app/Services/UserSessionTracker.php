<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserLoginSession;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UserSessionTracker
{
    public static function start(User $user, Request $request, ?int $tokenId = null, string $method = 'password'): UserLoginSession
    {
        return UserLoginSession::create([
            'user_id' => $user->id,
            'personal_access_token_id' => $tokenId,
            'session_uuid' => (string) Str::uuid(),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 2000),
            'authentication_method' => $method,
            'authenticated_at' => now(),
            'last_activity_at' => now(),
        ]);
    }

    public static function touchByToken(?int $tokenId): void
    {
        if (!$tokenId) {
            return;
        }

        UserLoginSession::query()
            ->where('personal_access_token_id', $tokenId)
            ->whereNull('logged_out_at')
            ->update(['last_activity_at' => now()]);
    }

    public static function closeByToken(?int $tokenId, string $reason): void
    {
        if (!$tokenId) {
            return;
        }

        UserLoginSession::query()
            ->where('personal_access_token_id', $tokenId)
            ->whereNull('logged_out_at')
            ->update([
                'logged_out_at' => now(),
                'logout_reason' => $reason,
                'last_activity_at' => now(),
            ]);
    }

    public static function closeAllForUser(User $user, string $reason): void
    {
        UserLoginSession::query()
            ->where('user_id', $user->id)
            ->whereNull('logged_out_at')
            ->update([
                'logged_out_at' => now(),
                'logout_reason' => $reason,
                'last_activity_at' => now(),
            ]);
    }
}
