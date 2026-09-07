<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Penjaga RBAC panel. Dipakai sebagai `izin:katalog.kelola`.
 *
 * Peran diperiksa lewat permission, bukan nama peran — supaya pemilik toko bisa
 * mengubah, menggabung, atau menambah peran tanpa menyentuh kode (docs/04).
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$izin): Response
    {
        $user = $request->user();

        abort_if(! $user || ! $user->aktif, 403, 'Akun Anda tidak aktif.');

        foreach ($izin as $slug) {
            if ($user->punyaIzin($slug)) {
                return $next($request);
            }
        }

        abort(403, 'Peran Anda tidak punya akses ke halaman ini.');
    }
}
