<?php

namespace App\Concerns;

use App\Models\SystemSetting;
use Illuminate\Validation\ValidationException;

trait EnforcesMetaPermissionHealth
{
    protected function enforceMetaPermissionHealthForProduction(string $operation = 'WhatsApp sending'): void
    {
        $settings = SystemSetting::query()->first();
        $environment = $settings?->meta_environment ?: env('META_ENVIRONMENT', 'production');

        if ($environment !== 'production') {
            return;
        }

        $maxAgeHours = max(1, (int) env('META_PERMISSION_VALIDATION_MAX_AGE_HOURS', 720));
        $checkedAt = $settings?->meta_permissions_last_checked_at;
        $status = $settings?->meta_permissions_status;
        $snapshot = $settings?->meta_permissions_snapshot ?? [];

        if (!$checkedAt) {
            $this->failMetaPermissionHealth($operation, 'Meta token permissions have not been validated yet. Validate permissions in Settings before sending WhatsApp messages.');
        }

        if ($checkedAt->lt(now()->subHours($maxAgeHours))) {
            $ageDisplay = $maxAgeHours >= 24 && $maxAgeHours % 24 === 0 
                ? ($maxAgeHours / 24) . ' days' 
                : $maxAgeHours . ' hours';
            $this->failMetaPermissionHealth($operation, "The last Meta permission validation is older than {$ageDisplay}. Revalidate Meta permissions in Settings before sending WhatsApp messages.");
        }

        if ($status !== 'healthy') {
            $detail = trim((string) ($snapshot['message'] ?? ''));
            $suffix = $detail !== '' ? ' ' . $detail : '';
            $this->failMetaPermissionHealth($operation, 'The current Meta permission validation is not healthy. Revalidate Meta permissions in Settings before sending WhatsApp messages.' . $suffix);
        }
    }

    protected function failMetaPermissionHealth(string $operation, string $message): void
    {
        throw ValidationException::withMessages([
            'meta_permissions' => ["{$operation} is blocked in production. {$message}"],
        ]);
    }
}
