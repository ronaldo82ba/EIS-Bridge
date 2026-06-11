<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'v1',
        then: function () {
            Route::middleware(['api', 'security.headers'])
                ->prefix('api/admin')
                ->group(base_path('routes/admin.php'));

            Route::middleware(['api', 'security.headers'])
                ->prefix('v1')
                ->group(base_path('routes/fleet.php'));

            Route::get('/horizon-health', \App\Http\Controllers\HorizonHealthController::class)
                ->middleware(['security.headers']);
        },
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['middleware' => ['auth:sanctum']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'api_key' => \App\Http\Middleware\ApiKeyMiddleware::class,
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'vendor.ip' => \App\Http\Middleware\EnsureVendorIpAllowed::class,
            'role' => \App\Http\Middleware\EnsureRole::class,
            'support.write' => \App\Http\Middleware\EnsureSupportWriteAction::class,
            'security.headers' => \App\Http\Middleware\SecurityHeadersMiddleware::class,
            'sandbox.api_key' => \App\Http\Middleware\EnsureSandboxApiKey::class,
            'fleet.auth' => \App\Http\Middleware\EnsureFleetAuth::class,
        ]);

        $middleware->api(prepend: [
            \App\Http\Middleware\CorsMiddleware::class,
            \App\Http\Middleware\SecurityHeadersMiddleware::class,
            \App\Http\Middleware\EnsureSandboxApiKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('up')
                || $request->is('v1/*')
                || $request->is('api/*')
                || $request->is('admin/*'),
        );
    })->create();
