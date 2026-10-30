<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\PengirimEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
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

        /*
         * Dua kunci (KEP-096). Per email+IP untuk salah ketik biasa, dikosongkan
         * begitu berhasil. Per IP saja, yang TIDAK dikosongkan login berhasil:
         * dulu hanya ada satu kunci per IP yang dikosongkan saat berhasil, jadi
         * siapa pun yang punya akun bisa login ke akunnya sendiri tiap beberapa
         * tebakan, lalu menebak sandi akun orang lain tanpa batas.
         */
        $kunci = 'masuk-pelanggan:'.Str::lower($data['email']).'|'.$request->ip();
        $kunciIp = 'masuk-pelanggan-ip:'.$request->ip();

        if (RateLimiter::tooManyAttempts($kunci, 8) || RateLimiter::tooManyAttempts($kunciIp, 30)) {
            throw ValidationException::withMessages([
                'email' => 'Terlalu banyak percobaan. Coba lagi dalam beberapa menit.',
            ]);
        }

        if (! Auth::guard('pelanggan')->attempt($data, $request->boolean('ingat'))) {
            RateLimiter::hit($kunci, 300);
            RateLimiter::hit($kunciIp, 3600);

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

    /**
     * Membuka tautan konfirmasi belum mengaktifkan apa pun (KEP-096).
     *
     * Siapa saja bisa mendaftar memakai email orang lain dengan kata sandi
     * pilihannya sendiri. Kalau satu klik di tautan email langsung
     * mengaktifkan akun, pemilik email yang penasaran mengaktifkan akun
     * bersandi milik orang itu, dan orang itu lalu bisa melihat semua pesanan
     * yang tercatat atas email tersebut. Karena itu kata sandinya diminta di
     * sini: hanya yang memegang email DAN membuat kata sandinya yang bisa
     * menyelesaikan pendaftaran.
     */
    public function konfirmasi(string $token)
    {
        $pelanggan = $this->pendaftaranTertunda($token);

        if (! $pelanggan) {
            return $this->tautanTidakBerlaku();
        }

        return view('publik.akun.konfirmasi', ['pelanggan' => $pelanggan, 'token' => $token]);
    }

    public function konfirmasiProses(Request $request, string $token): RedirectResponse
    {
        $data = $request->validate(['password' => ['required', 'string']], [], ['password' => 'kata sandi']);

        $pelanggan = $this->pendaftaranTertunda($token);

        if (! $pelanggan) {
            return $this->tautanTidakBerlaku();
        }

        if (! Hash::check($data['password'], (string) $pelanggan->password)) {
            throw ValidationException::withMessages([
                'password' => 'Kata sandinya tidak cocok dengan yang dibuat saat mendaftar.',
            ]);
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

    private function pendaftaranTertunda(string $token): ?Customer
    {
        $pelanggan = Customer::where('token_verifikasi', hash('sha256', $token))->first();

        return $pelanggan?->tokenMasihBerlaku() ? $pelanggan : null;
    }

    private function tautanTidakBerlaku(): RedirectResponse
    {
        return redirect()->route('daftar')->withErrors(
            'Tautan konfirmasi sudah tidak berlaku. Silakan daftar ulang untuk '
            .'mendapatkan tautan baru.'
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
