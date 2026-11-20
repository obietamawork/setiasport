<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pelanggan yang akunnya dinonaktifkan staf (KEP-157) langsung keluar pada
 * permintaan berikutnya, di halaman mana pun. Tautan pesanannya tetap
 * berlaku: yang dimatikan hanya akunnya, bukan pesanannya.
 */
class PastikanPelangganAktif
{
    public function handle(Request $request, Closure $next): Response
    {
        $pelanggan = Auth::guard('pelanggan')->user();

        // === false, bukan !: kolom yang belum termuat bernilai null.
        if ($pelanggan && $pelanggan->aktif === false) {
            Auth::guard('pelanggan')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('masuk')
                ->withErrors(['email' => 'Akun ini dinonaktifkan. Hubungi CS kami untuk bantuan.']);
        }

        return $next($request);
    }
}
