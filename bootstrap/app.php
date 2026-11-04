<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth'  => \App\Http\Middleware\Authenticate::class,
            'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
            // [DIGIFEST 2026] Trust Engine interceptor — hanya aktif jika flag shadow=true
            'trust.engine' => \App\Competition\Http\TrustEngineMiddleware::class,
        ]);

        // Pasang middleware ke route absen secara kondisional
        if (config('competition.trust_engine_shadow', false)) {
            $middleware->appendToGroup('web', \App\Competition\Http\TrustEngineMiddleware::class);
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
