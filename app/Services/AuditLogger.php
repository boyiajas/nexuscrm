<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    /**
     * Write an audit entry.
     *
     * @param  string       $action  Human readable action description.
     * @param  string       $module  Logical module name, e.g. "Clients", "Campaigns".
     * @param  array|null   $meta    Optional extra structured data.
     */
    public static function log(string $action, string $module = 'General', ?array $meta = null): void
    {
        $user = Auth::user();

        AuditLog::create([
            'user_id'    => $user?->id,
            'action'     => $action,
            'module'     => $module,
            'ip_address' => request()?->ip(),
            'meta'       => $meta,
            'logged_at'  => now(),
        ]);
    }
}
