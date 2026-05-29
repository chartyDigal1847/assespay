<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'portal.session' => \App\Http\Middleware\EnsurePortalSession::class,
            'assesspay.role' => \App\Http\Middleware\EnsureAssessPayRole::class,
            'api.log' => \App\Http\Middleware\LogApiAccess::class,
        ]);

        $middleware->appendToGroup('web', \App\Http\Middleware\ClearLegacyWebAuth::class);
        $middleware->prependToGroup('web', \App\Http\Middleware\ForceSessionCookies::class);
        $middleware->prependToGroup('api', \App\Http\Middleware\ForceSessionCookies::class);
        $middleware->append(\App\Http\Middleware\ModuleCspMiddleware::class);

        $middleware->validateCsrfTokens(except: [
            'sso/exchange',
            'sso/*',
            'api/sso/*',
            'api/v1/*',
        ]);

        // env() only — config() is not available during early bootstrap (e.g. composer package:discover)
        $middleware->throttleApi((int) env('ASSESSPAY_API_RATE_LIMIT', 120));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
