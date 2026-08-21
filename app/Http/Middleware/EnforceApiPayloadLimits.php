<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnforceApiPayloadLimits
{
    public function handle(Request $request, Closure $next)
    {
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH'], true)) {
            return $next($request);
        }

        $contentLength = (int) ($request->server('CONTENT_LENGTH') ?: $request->header('Content-Length', 0));
        if ($contentLength <= 0) {
            return $next($request);
        }

        $limitBytes = $this->resolveLimitBytes($request);
        if ($limitBytes > 0 && $contentLength > $limitBytes) {
            return response()->json([
                'message' => 'Request payload exceeds the allowed size for this endpoint.',
                'limit_kb' => (int) floor($limitBytes / 1024),
            ], 413);
        }

        return $next($request);
    }

    protected function resolveLimitBytes(Request $request): int
    {
        if ($request->is('api/clients/import')) {
            // Keep some headroom above the 10 MB file validation limit for multipart form boundaries.
            return (int) env('IMPORT_MAX_PAYLOAD_KB', 12288) * 1024;
        }

        if ($request->is('api/whatsapp/webhook') || $request->is('api/twilio/webhook/whatsapp')) {
            return (int) env('WHATSAPP_WEBHOOK_MAX_PAYLOAD_KB', 512) * 1024;
        }

        return (int) env('API_MAX_PAYLOAD_KB', 5120) * 1024;
    }
}
