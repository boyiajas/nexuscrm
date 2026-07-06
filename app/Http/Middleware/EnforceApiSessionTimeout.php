<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use App\Services\UserSessionTracker;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\TransientToken;
use Symfony\Component\HttpFoundation\Response;

class EnforceApiSessionTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            return $next($request);
        }

        $hasBearerToken = filled($request->bearerToken());
        $token = $hasBearerToken ? $user->currentAccessToken() : null;
        $isTransientToken = !$hasBearerToken
            || !$token
            || $token instanceof TransientToken
            || !isset($token->id);
        $tokenId = $isTransientToken ? null : (int) $token->id;

        if (!$user->isActive()) {
            if ($tokenId) {
                UserSessionTracker::closeByToken($tokenId, 'user_inactive');
                $token->delete();
            } else {
                Auth::guard('web')->logout();
            }

            return response()->json([
                'message' => 'Your account is inactive. Please contact an administrator.',
            ], 403);
        }

        if ((bool) $user->password_reset_required) {
            if ($tokenId) {
                UserSessionTracker::closeByToken($tokenId, 'password_reset_required');
                $token->delete();
            } else {
                Auth::guard('web')->logout();
            }

            return response()->json([
                'message' => 'Your password must be reset before you can continue.',
            ], 401);
        }

        if ($this->passwordExpired($user)) {
            $user->forceFill([
                'password_reset_required' => true,
            ])->save();

            if ($tokenId) {
                UserSessionTracker::closeByToken($tokenId, 'password_expired');
                $token->delete();
            } else {
                Auth::guard('web')->logout();
            }

            return response()->json([
                'message' => 'Your password has expired and must be reset before you can continue.',
            ], 401);
        }

        $timeoutMinutes = (int) ($user->inactivity_timeout ?: 0);
        if ($timeoutMinutes <= 0) {
            if ($tokenId) {
                UserSessionTracker::touchByToken($tokenId);
            }
            return $next($request);
        }

        if ($isTransientToken) {
            // Session-authenticated requests do not have token activity timestamps.
            // Skip token-based inactivity enforcement for this request path.
            return $next($request);
        }

        $lastUsedAt = isset($token->last_used_at) ? $token->last_used_at : null;
        $createdAt = isset($token->created_at) ? $token->created_at : null;
        $lastActivity = $lastUsedAt ?: $createdAt;
        if ($lastActivity && now()->diffInMinutes($lastActivity) >= $timeoutMinutes) {
            UserSessionTracker::closeByToken($tokenId, 'inactivity_timeout');
            $token->delete();

            return response()->json([
                'message' => 'Your session has expired due to inactivity. Please sign in again.',
            ], 401);
        }

        UserSessionTracker::touchByToken($tokenId);

        return $next($request);
    }

    protected function passwordExpired($user): bool
    {
        $settings = SystemSetting::query()->first();
        $maxAgeDays = (int) ($settings?->password_max_age_days ?: env('PASSWORD_MAX_AGE_DAYS', 90));
        if ($maxAgeDays <= 0) {
            return false;
        }

        if (!$user->password_changed_at) {
            return true;
        }

        return $user->password_changed_at->lt(now()->subDays($maxAgeDays));
    }
}
