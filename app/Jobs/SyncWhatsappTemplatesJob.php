<?php

namespace App\Jobs;

use App\Contracts\WhatsAppServiceInterface;
use App\Models\WhatsappTemplateCache;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncWhatsappTemplatesJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const STATUS_CACHE_KEY = 'whatsapp_templates_sync_status';

    public int $uniqueFor = 300;

    public int $tries = 3;

    public int $timeout = 180;

    public function __construct(public ?int $requestedBy = null)
    {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return 'meta-whatsapp-templates';
    }

    public static function markQueued(?int $requestedBy): void
    {
        Cache::put(self::STATUS_CACHE_KEY, [
            'state' => 'queued',
            'message' => 'Waiting for the template sync worker.',
            'count' => null,
            'requested_by' => $requestedBy,
            'requested_at' => now()->toDateTimeString(),
            'started_at' => null,
            'completed_at' => null,
        ], now()->addHour());
    }

    public static function status(): array
    {
        return Cache::get(self::STATUS_CACHE_KEY, [
            'state' => 'idle',
            'message' => 'No template sync is currently running.',
            'count' => WhatsappTemplateCache::query()->count(),
            'completed_at' => WhatsappTemplateCache::query()->max('synced_at'),
        ]);
    }

    public static function isActive(): bool
    {
        return in_array(self::status()['state'] ?? null, ['queued', 'running'], true);
    }

    public function handle(WhatsAppServiceInterface $whatsApp): void
    {
        $status = self::status();
        Cache::put(self::STATUS_CACHE_KEY, array_merge($status, [
            'state' => 'running',
            'message' => 'Downloading templates from Meta.',
            'started_at' => now()->toDateTimeString(),
        ]), now()->addHour());

        try {
            $templates = $whatsApp->getWhatsAppTemplates(false, 100);
            $now = now();

            foreach ($templates as $template) {
                $whatsapp = $template['whatsapp'] ?? [];

                WhatsappTemplateCache::updateOrCreate(
                    ['sid' => $template['sid']],
                    [
                        'meta_id' => $template['meta_id'] ?? null,
                        'friendly_name' => $template['friendly_name'] ?? $template['sid'],
                        'language' => $template['language'] ?? null,
                        'category' => $whatsapp['category'] ?? null,
                        'status' => $whatsapp['status'] ?? null,
                        'body_preview' => $template['preview'] ?? null,
                        'header_format' => $template['header_format'] ?? null,
                        'header_text' => $template['header_text'] ?? null,
                        'footer_text' => $template['footer_text'] ?? null,
                        'variables' => $template['variables'] ?? [],
                        'media_urls' => $template['media'] ?? [],
                        'buttons' => $template['buttons'] ?? [],
                        'raw_whatsapp' => array_merge($whatsapp, ['components' => $template['components'] ?? []]),
                        'synced_at' => $now,
                    ]
                );
            }

            Cache::put(self::STATUS_CACHE_KEY, array_merge($status, [
                'state' => 'completed',
                'message' => 'Templates synced successfully.',
                'count' => count($templates),
                'completed_at' => now()->toDateTimeString(),
            ]), now()->addHour());
        } catch (Throwable $exception) {
            Cache::put(self::STATUS_CACHE_KEY, array_merge($status, [
                'state' => 'failed',
                'message' => 'Template sync failed: ' . $exception->getMessage(),
                'completed_at' => now()->toDateTimeString(),
            ]), now()->addHour());

            Log::error('Queued WhatsApp template sync failed.', [
                'requested_by' => $this->requestedBy,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        if (!in_array(self::status()['state'] ?? null, ['failed', 'completed'], true)) {
            Cache::put(self::STATUS_CACHE_KEY, [
                'state' => 'failed',
                'message' => 'Template sync failed: ' . ($exception?->getMessage() ?? 'Unknown queue error.'),
                'count' => null,
                'completed_at' => now()->toDateTimeString(),
            ], now()->addHour());
        }
    }
}
