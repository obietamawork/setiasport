<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\PengirimEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Akun pelanggan, jalur kedua di samping tautan bertoken (KEP-020).
 *
 * Pendaftaran WAJIB dikonfirmasi lewat email sebelum akunnya bisa dipakai
 * (KEP-021). Tanpa itu, siapa pun yang tahu alamat email seorang pelanggan bisa
 * mendaftar memakai alamat tersebut dan langsung mewarisi seluruh riwayat
 * pesanannya, termasuk kode ambil barang. Email pelanggan bukan rahasia.
 */
class AkunController extends Controller
{
    public function __construct(private PengirimEmail $email) {}

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

        if (! Auth::guard('pelanggan')->attempt($data, $request->boolean('ingat'))) {
            RateLimiter::hit($kunci, 300);

            // Pesan yang sama untuk email tidak dikenal maupun kata sandi salah,
            // supaya halaman ini tidak bisa dipakai menebak email pelanggan.
            throw ValidationException::withMessages([
                'email' => 'Email atau kata sandi tidak cocok.',
            ]);
        }

        $pelanggan = Auth::guard('pelanggan')->user();

        // Kata sandinya benar, tapi penguasaan email belum pernah dibuktikan.
        // Diberitahukan hanya setelah kata sandi cocok, jadi bukan celah tebakan.
        if (! $pelanggan->akunAktif()) {
            Auth::guard('pelanggan')->logout();
            $this->kirimKonfirmasi($pelanggan, $request->string('password')->toString());

            throw ValidationException::withMessages([
                'email' => 'Akun ini belum dikonfirmasi. Kami baru saja mengirim ulang '
                    .'tautan konfirmasinya ke email Anda.',
            ]);
        }

        RateLimiter::clear($kunci);
        $request->session()->regenerate();

        $pelanggan->forceFill(['login_terakhir_pada' => now()])->save();

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

        $kunci = 'daftar-pelanggan:'.$request->ip();

        if (RateLimiter::tooManyAttempts($kunci, 6)) {
            throw ValidationException::withMessages([
                'email' => 'Terlalu banyak percobaan. Coba lagi dalam beberapa menit.',
            ]);
        }

        RateLimiter::hit($kunci, 600);

        $pelanggan = Customer::where('email', $data['email'])->first();

        if (! $pelanggan) {
            $pelanggan = Customer::create([
                'nama' => $data['nama'],
                'email' => $data['email'],
                'no_hp' => $data['no_hp'],
            ]);
        }

        /*
         * Akun yang sudah aktif tidak disentuh sama sekali: kata sandinya tidak
         * ditimpa, dan nama serta nomor HP-nya tidak diubah oleh orang lain.
         * Untuk yang belum aktif, kata sandi baru disiapkan sebagai pendaftaran
         * tertunda yang hanya bisa diselesaikan dari email itu sendiri.
         */
        if (! $pelanggan->akunAktif()) {
            $this->kirimKonfirmasi($pelanggan, $data['password']);
        }

        // Jawaban selalu sama, apa pun keadaan emailnya. Halaman ini tidak boleh
        // bisa dipakai memastikan apakah sebuah email terdaftar di toko.
        return redirect()->route('daftar.terkirim');
    }

    public function terkirim()
    {
        return view('publik.akun.terkirim');
    }

    public function konfirmasi(Request $request, string $token): RedirectResponse
    {
        $pelanggan = Customer::where('token_verifikasi', hash('sha256', $token))->first();

        if (! $pelanggan || ! $pelanggan->tokenMasihBerlaku()) {
            return redirect()->route('daftar')->withErrors(
                'Tautan konfirmasi sudah tidak berlaku. Silakan daftar ulang untuk '
                .'mendapatkan tautan baru.'
            );
        }

        $pelanggan->selesaikanKonfirmasi();

        Auth::guard('pelanggan')->login($pelanggan);
        $request->session()->regenerate();

        return redirect()->route('pesanan-saya')->with(
            'sukses',
            $pelanggan->orders()->exists()
                ? 'Akun aktif. Pesanan Anda sebelumnya sudah ikut tercatat di sini.'
                : 'Akun aktif. Pesanan Anda nanti akan muncul di halaman ini.'
        );
    }

    public function keluar(Request $request): RedirectResponse
    {
        Auth::guard('pelanggan')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('beranda')->with('sukses', 'Anda sudah keluar.');
    }

    private function kirimKonfirmasi(Customer $pelanggan, string $password): void
    {
        $token = $pelanggan->siapkanKonfirmasi($password);

        $this->email->kirimKonfirmasiAkun($pelanggan, route('daftar.konfirmasi', $token));
    }
}
