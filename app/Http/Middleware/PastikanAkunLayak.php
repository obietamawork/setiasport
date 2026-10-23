<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dua pemeriksaan pada SETIAP permintaan panel, bukan hanya saat masuk (KEP-084):
 *
 * - Akun yang dinonaktifkan Pemilik langsung terputus. Dulu status aktif hanya
 *   dicek di formulir masuk, jadi staf yang sudah keluar dari toko tetap bisa
 *   memakai panel sampai sesinya habis sendiri.
 * - Akun bersandi sementara (baru dibuat atau diatur ulang Pemilik) hanya boleh
 *   membuka halaman Akun saya sampai sandinya diganti sendiri.
 */
class PastikanAkunLayak
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('web');

        // === false, bukan !: kolom yang belum termuat bernilai null dan
        // tidak boleh dianggap nonaktif (bawaan kolomnya di database true).
        if ($user && $user->aktif === false) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('panel.login')
                ->withErrors(['email' => 'Akun ini sudah dinonaktifkan. Hubungi pemilik toko.']);
        }

        if ($user && $user->wajib_ganti_sandi && ! $request->routeIs('panel.akun', 'panel.akun.*', 'panel.keluar')) {
            return redirect()->route('panel.akun')
                ->with('peringatan', 'Ganti kata sandi sementara Anda dulu sebelum memakai panel.');
        }

        return $next($request);
    }
}
