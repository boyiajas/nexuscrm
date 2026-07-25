<?php

namespace App\Providers;

use App\Contracts\WhatsAppServiceInterface;
use App\Services\MetaWhatsAppService;
use App\Services\WhatsAppBatchService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(WhatsAppServiceInterface::class, MetaWhatsAppService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('meta-whatsapp-outbound', function () {
            return Limit::perSecond(app(WhatsAppBatchService::class)->enforcedMessagesPerSecond())
                ->by('meta-whatsapp-outbound');
        });
    }
}
