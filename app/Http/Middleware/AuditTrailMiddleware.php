<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditTrailMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Let the request run first
        $response = $next($request);

        // Only log for authenticated users and mutating HTTP verbs
        if (Auth::check() && in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {

            // Don’t log some paths if you want (e.g. health checks)
            if ($request->is('up')) {
                return $response;
            }

            // Route info
            $route      = $request->route();
            $routeName  = $route?->getName();
            $routeUri   = $route?->uri();
            $actionName = $routeName ?: $routeUri ?: $request->path();

            // Derive a "module" name – you can tweak this convention
            $module = 'General';
            if ($routeName && str_contains($routeName, '.')) {
                $module = ucfirst(strtok($routeName, '.')); // e.g. "clients.index" -> "Clients"
            } elseif ($routeUri) {
                $module = ucfirst(explode('/', $routeUri)[0]); // e.g. "clients/5" -> "Clients"
            }

            // Build safe request payload (redact sensitive keys)
            $input = $request->all();
            $sensitive = ['password', 'password_confirmation', 'current_password', 'token', '_token'];
            foreach ($sensitive as $key) {
                if (array_key_exists($key, $input)) {
                    $input[$key] = '******';
                }
            }

            $status = $response->getStatusCode();

            AuditLog::create([
                'user_id'    => Auth::id(),
                'action'     => sprintf(
                    '[%s] %s (%s) -> HTTP %s',
                    $request->method(),
                    $actionName,
                    $module,
                    $status
                ),
                'module'     => $module,
                'ip_address' => $request->ip(),
                'meta'       => [
                    'route_name' => $routeName,
                    'route_uri'  => $routeUri,
                    'method'     => $request->method(),
                    'status'     => $status,
                    'request'    => $input,
                ],
                'logged_at'  => now(),
            ]);
        }

        return $response;
    }
}
