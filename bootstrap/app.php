<?php

use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\HeaderKeamanan;
use App\Http\Middleware\PastikanAkunLayak;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'izin' => EnsurePermission::class,
            'akun.layak' => PastikanAkunLayak::class,
        ]);

        // Header keamanan di setiap respons, termasuk halaman galat (KEP-086).
        $middleware->append(HeaderKeamanan::class);

        // Staf dan pelanggan punya halaman masuk sendiri; jangan saling melempar.
        $middleware->redirectGuestsTo(
            fn ($request) => $request->is('panel', 'panel/*') ? route('panel.login') : route('masuk')
        );

        $middleware->validateCsrfTokens(except: [
            'webhook/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
         * Penjaga alur di seluruh aplikasi menolak tindakan dengan abort(422)
         * atau abort(409) beserta kalimat yang menjelaskan sebabnya (KEP-098).
         * Di produksi kalimat itu dulu tidak pernah terbaca: yang tampil
         * halaman bawaan berbahasa Inggris "Unprocessable Content" tanpa jalan
         * kembali. Orang yang menekan tombol kini dikembalikan ke halaman
         * tempat ia menekannya, dengan kalimat itu di atasnya.
         */
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if (! in_array($e->getStatusCode(), [409, 422], true)
                || $e->getMessage() === ''
                || $request->expectsJson()
                || $request->isMethod('GET')) {
                return null;
            }

            return back()->withErrors(['alur' => $e->getMessage()]);
        });
    })->create();
