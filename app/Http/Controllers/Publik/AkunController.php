<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Akun pelanggan, jalur kedua di samping tautan bertoken (KEP-020).
 *
 * Yang tidak mau membuat akun tidak kehilangan apa pun: tautan di email tetap
 * membuka halaman pesanan seperti biasa. Akun hanya menjawab satu kebutuhan,
 * yaitu pelanggan berulang yang ingin melihat semua pesanannya dalam satu
 * tempat tanpa mencari-cari email lama.
 */
class AkunController extends Controller
{
    public function formMasuk()
    {
        return Auth::guard('pelanggan')->check()
            ? redirect()->route('pesanan-saya')
            : view('publik.akun.masuk');
    }

    public function masuk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [], ['email' => 'email', 'password' => 'kata sandi']);

        $kunci = 'masuk-pelanggan:'.$request->ip();

        if (RateLimiter::tooManyAttempts($kunci, 8)) {
            throw ValidationException::withMessages([
                'email' => 'Terlalu banyak percobaan. Coba lagi dalam beberapa menit.',
            ]);
        }

        $pelanggan = Customer::where('email', $data['email'])->first();

        // Pelanggan yang pernah memesan sebagai tamu sudah ada di sistem tapi
        // belum punya kata sandi. Arahkan ke pendaftaran, bukan ke pesan gagal
        // yang membingungkan.
        if ($pelanggan && ! $pelanggan->punyaAkun()) {
            return redirect()->route('daftar', ['email' => $pelanggan->email])
                ->with('sukses', 'Email Anda sudah kami kenal, tinggal buat kata sandinya.');
        }

        if (! Auth::guard('pelanggan')->attempt($data, $request->boolean('ingat'))) {
            RateLimiter::hit($kunci, 300);

            throw ValidationException::withMessages([
                'email' => 'Email atau kata sandi tidak cocok.',
            ]);
        }

        RateLimiter::clear($kunci);
        $request->session()->regenerate();

        Auth::guard('pelanggan')->user()->forceFill(['login_terakhir_pada' => now()])->save();

        return redirect()->intended(route('pesanan-saya'));
    }

    public function formDaftar(Request $request)
    {
        if (Auth::guard('pelanggan')->check()) {
            return redirect()->route('pesanan-saya');
        }

        return view('publik.akun.daftar', ['email' => $request->string('email')->toString()]);
    }

    public function daftar(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
            'no_hp' => ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s]+$/'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'password.confirmed' => 'Ulangi kata sandi belum sama.',
            'no_hp.regex' => 'Nomor HP hanya boleh berisi angka, spasi, tanda plus, dan strip.',
        ], [
            'nama' => 'nama', 'email' => 'email',
            'no_hp' => 'nomor HP', 'password' => 'kata sandi',
        ]);

        $pelanggan = Customer::where('email', $data['email'])->first();

        if ($pelanggan?->punyaAkun()) {
            throw ValidationException::withMessages([
                'email' => 'Email ini sudah punya akun. Silakan masuk.',
            ]);
        }

        // Pemesan tamu yang mendaftar belakangan langsung mewarisi seluruh
        // riwayat pesanannya, karena email adalah kuncinya sejak awal.
        $pelanggan = Customer::updateOrCreate(['email' => $data['email']], [
            'nama' => $data['nama'],
            'no_hp' => $data['no_hp'],
            'password' => Hash::make($data['password']),
            'akun_dibuat_pada' => now(),
        ]);

        Auth::guard('pelanggan')->login($pelanggan);
        $request->session()->regenerate();

        return redirect()->route('pesanan-saya')->with(
            'sukses',
            $pelanggan->orders()->exists()
                ? 'Akun dibuat. Pesanan Anda sebelumnya sudah ikut tercatat di sini.'
                : 'Akun dibuat. Pesanan Anda nanti akan muncul di halaman ini.'
        );
    }

    public function keluar(Request $request): RedirectResponse
    {
        Auth::guard('pelanggan')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('beranda')->with('sukses', 'Anda sudah keluar.');
    }
}
