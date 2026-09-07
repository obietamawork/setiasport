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
            'izin' => \App\Http\Middleware\EnsurePermission::class,
        ]);

        // Staf dan pelanggan punya halaman masuk sendiri; jangan saling melempar.
        $middleware->redirectGuestsTo(
            fn ($request) => $request->is('panel', 'panel/*') ? route('panel.login') : route('masuk')
        );

        $middleware->validateCsrfTokens(except: [
            'webhook/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
