<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Parameter alamat (query string) hanya boleh berisi teks (KEP-104).
 *
 * Tidak satu pun halaman memakai array di alamat, tetapi PHP tetap mengubah
 * ?q[]=x menjadi array. Kode yang membaca $request->string('q') atau
 * (string) $request->query('q') lalu berhenti dengan galat 500 "Array to
 * string conversion": katalog, daftar akun, ongkir, dan delapan halaman panel
 * bisa dijatuhkan siapa saja hanya dengan mengubah alamatnya. Ditolak di satu
 * tempat sebagai permintaan yang salah, bukan dijaga satu per satu di setiap
 * controller, supaya halaman yang dibuat nanti ikut terlindungi.
 */
class TolakParameterArray
{
    public function handle(Request $request, Closure $next): Response
    {
        foreach ($request->query() as $nilai) {
            if (is_array($nilai)) {
                abort(400, 'Alamat ini memuat parameter yang tidak dikenali.');
            }
        }

        return $next($request);
    }
}
