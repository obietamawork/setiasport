<?php

use App\Exceptions\GambarTidakBisaDiproses;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\HeaderKeamanan;
use App\Http\Middleware\PastikanAkunLayak;
use App\Http\Middleware\PastikanPelangganAktif;
use App\Http\Middleware\TolakParameterArray;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
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

        // ?q[]=x dulu menjatuhkan sepuluh halaman dengan galat 500 (KEP-104).
        $middleware->append(TolakParameterArray::class);

        // Akun pelanggan yang dinonaktifkan staf keluar di halaman mana pun (KEP-157).
        $middleware->web(append: [PastikanPelangganAktif::class]);

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

        /*
         * Token CSRF basi (KEP-159): halaman yang lama terbuka, sementara sesinya
         * sudah berganti. Bukan layar "Page Expired" tanpa jalan kembali,
         * melainkan halaman yang sama dimuat ulang dengan token baru, isian
         * teksnya dipertahankan, dan satu kalimat untuk mengulang.
         */
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($e->getStatusCode() !== 419 || $request->expectsJson()) {
                return null;
            }

            // Isian rahasia dikenali dari namanya, bukan daftar manual yang bisa
            // tertinggal saat formulir baru ditambahkan: tidak pernah ditulis ke sesi.
            $aman = Arr::where(
                Arr::except($request->input(), ['_token']),
                fn ($nilai, $kunci) => ! preg_match('/password|sandi|secret|token|api_?key|^pin$|_pin$/i', (string) $kunci),
            );

            return back()
                ->withInput($aman)
                ->withErrors(['sesi' => 'Halaman ini sudah terlalu lama terbuka, jadi kami muat ulang. Silakan ulangi tindakan tadi.']);
        });

        /*
         * Gambar unggahan yang rusak atau terlalu besar (KEP-154) dikembalikan
         * sebagai pesan pada isian berkasnya, bukan halaman galat 500. Jalur
         * publik sudah menolaknya lewat aturan GambarBisaDiproses; ini jaring
         * pengaman untuk semua tempat unggah lain di panel. Bukan galat
         * aplikasi, jadi tidak perlu memenuhi log.
         */
        $exceptions->dontReport(GambarTidakBisaDiproses::class);
        $exceptions->render(function (GambarTidakBisaDiproses $e, Request $request) {
            $isian = array_key_first($request->allFiles()) ?? 'berkas';

            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage(), 'errors' => [$isian => [$e->getMessage()]]], 422);
            }

            return back()->withErrors([$isian => $e->getMessage()])->withInput($request->except(array_keys($request->allFiles())));
        });
    })->create();
