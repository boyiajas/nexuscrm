<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use App\Services\UserSessionTracker;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\TransientToken;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

class EnforceAdminIpAllowlist
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user || !$user->requiresAdminIpAllowlist()) {
            return $next($request);
        }

        $settings = SystemSetting::query()->first();
        $allowlist = $settings?->adminIpAllowlistEntries()
            ?: $this->envAllowlist();

        if (empty($allowlist)) {
            return $next($request);
        }

        $ip = (string) $request->ip();
        if (IpUtils::checkIp($ip, $allowlist)) {
            return $next($request);
        }

        $token = $user->currentAccessToken();
        if ($token instanceof TransientToken) {
            Auth::guard('web')->logout();
        } elseif ($token) {
            UserSessionTracker::closeByToken($token->id, 'ip_not_allowlisted');
            $token->delete();
        }

        return response()->json([
            'message' => 'Your current IP address is not allowed to access the admin portal.',
        ], 403);
    }

    protected function envAllowlist(): array
    {
        return collect(preg_split('/[\r\n,;]+/', (string) env('ADMIN_IP_ALLOWLIST', '')))
            ->map(fn ($item) => trim($item))
            ->filter()
            ->values()
            ->all();
    }
}
