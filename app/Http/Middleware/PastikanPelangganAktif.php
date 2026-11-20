<?php

namespace App\Http\Middleware;

use App\Models\Customer;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sesi pelanggan hanya berlaku selama akunnya masih sama (KEP-157):
 *
 * - Akun yang dinonaktifkan staf langsung keluar pada permintaan berikutnya,
 *   di halaman mana pun. Tautan pesanannya tetap berlaku: yang dimatikan hanya
 *   akunnya, bukan pesanannya.
 * - Setelah kata sandi diganti, sesi di perangkat lain ikut keluar. Pelanggan
 *   yang mengganti sandi karena curiga ada orang lain ikut masuk harus benar-benar
 *   mengusir orang itu. Perangkat yang mengganti sandi tetap masuk.
 */
class PastikanPelangganAktif
{
    private const KUNCI_SIDIK = 'pelanggan_sidik_sandi';

    public function handle(Request $request, Closure $next): Response
    {
        $penjaga = Auth::guard('pelanggan');
        $pelanggan = $penjaga->user();

        if ($pelanggan instanceof Customer) {
            // === false, bukan !: kolom yang belum termuat bernilai null.
            if ($pelanggan->aktif === false) {
                return $this->keluarkan($request, 'Akun ini dinonaktifkan. Hubungi CS kami untuk bantuan.');
            }

            // Sidik akun lain di sesi yang sama (berganti akun) bukan tanda sandi diganti.
            $tersimpan = $request->session()->get(self::KUNCI_SIDIK);

            if (is_array($tersimpan) && ($tersimpan['id'] ?? null) === $pelanggan->id
                && ! hash_equals((string) ($tersimpan['sidik'] ?? ''), $this->sidik($pelanggan))) {
                return $this->keluarkan($request, 'Kata sandi akun ini baru saja diganti. Silakan masuk dengan kata sandi yang baru.');
            }
        }

        $respons = $next($request);

        // Dicatat sesudah permintaan, termasuk yang baru saja masuk atau mengganti
        // sandi: perangkat itu langsung memegang sidik yang baru.
        $pelanggan = $penjaga->user();

        if ($pelanggan instanceof Customer) {
            $request->session()->put(self::KUNCI_SIDIK, ['id' => $pelanggan->id, 'sidik' => $this->sidik($pelanggan)]);
        }

        return $respons;
    }

    /** Turunan hash kata sandi; hash aslinya tidak ikut disimpan di sesi. */
    private function sidik(Customer $pelanggan): string
    {
        return hash_hmac('sha256', (string) $pelanggan->getAuthPassword(), (string) config('app.key'));
    }

    private function keluarkan(Request $request, string $pesan): RedirectResponse
    {
        Auth::guard('pelanggan')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('masuk')->withErrors(['email' => $pesan]);
    }
}
