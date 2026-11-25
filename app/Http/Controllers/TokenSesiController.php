<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * Token CSRF terkini untuk halaman yang lama terbuka (KEP-159).
 *
 * Sesi berakhir setelah lama didiamkan, lalu "ingat saya" memasukkan staf
 * kembali dengan sesi dan token baru. Halaman yang dibuka sebelumnya masih
 * memegang token lama, jadi setiap kirimannya ditolak 419. app.js meminta
 * token baru di sini saat tab dibuka lagi dan secara berkala, lalu
 * memasangnya di semua formulir halaman itu.
 */
class TokenSesiController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['token' => csrf_token()])
            ->header('Cache-Control', 'no-store, private');
    }
}
