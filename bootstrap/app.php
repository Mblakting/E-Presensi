<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            if (config('competition.trust_engine_enforce', false)) {
                require app_path('Competition/Routes/web.php');
            }
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth'  => \App\Http\Middleware\Authenticate::class,
            'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
            'trust.engine' => \App\Competition\Http\TrustEngineMiddleware::class,
            'competition.enforce' => \App\Competition\Http\EnforceMiddleware::class,
        ]);

        // [DIGIFEST 2026] Interceptor hanya aktif jika shadow mode diaktifkan.
        if (config('competition.trust_engine_shadow', false)) {
            $middleware->appendToGroup('web', \App\Competition\Http\TrustEngineMiddleware::class);
        }

        if (config('competition.trust_engine_enforce', false)) {
            $middleware->appendToGroup('web', \App\Competition\Http\EnforceMiddleware::class);
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
