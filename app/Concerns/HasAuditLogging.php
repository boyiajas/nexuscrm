<?php

namespace App\Concerns;

use App\Services\AuditLogger;

trait HasAuditLogging
{
    protected function audit(string $action, string $module = 'General', ?array $meta = null): void
    {
        AuditLogger::log($action, $module, $meta);
    }
}
