<?php

use App\Http\Middleware\AuditTrailMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;


return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',   // 👈 add this
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /**
         * Here you can customise middleware for web/api.
         * We prepend Sanctum's stateful middleware to the API group
         * so SPA requests (Vue) using cookies are treated as authenticated.
         */

        $middleware->api(
            prepend: [
                EnsureFrontendRequestsAreStateful::class,
            ],
            append: [
                AuditTrailMiddleware::class, // 👈 our audit trail
            ],
        );

        // If later you want to tweak web group too, you can do e.g.:
        // $middleware->web(append: [
        //     \App\Http\Middleware\SomeWebMiddleware::class,
        // ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
