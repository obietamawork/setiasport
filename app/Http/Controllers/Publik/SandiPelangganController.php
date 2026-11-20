<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\PengirimEmail;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Lupa kata sandi pelanggan (KEP-157).
 *
 * Jawaban halaman selalu sama, apa pun keadaan emailnya, supaya halaman ini
 * tidak bisa dipakai memastikan email siapa yang terdaftar. Tautan berlaku 60
 * menit, sekali pakai, dan hanya hash tokennya yang disimpan. Pemesan tamu
 * yang belum punya kata sandi ikut bisa membuatnya lewat jalur ini, karena
 * membuka tautan di email sudah membuktikan penguasaan email itu.
 */
class SandiPelangganController extends Controller
{
    private const PER_EMAIL_PER_JAM = 3;

    public function __construct(private PengirimEmail $email) {}

    public function form(): View
    {
        return view('publik.akun.lupa-sandi');
    }

    public function kirim(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:150']], [], ['email' => 'email']);

        $kunci = 'lupa-sandi:'.Str::lower($data['email']);

        // Satu email paling banyak tiga tautan per jam, supaya kotak masuk
        // orang tidak bisa dibanjiri dari halaman ini.
        if (! RateLimiter::tooManyAttempts($kunci, self::PER_EMAIL_PER_JAM)) {
            RateLimiter::hit($kunci, 3600);

            $pelanggan = Customer::where('email', $data['email'])->where('aktif', true)->first();

            if ($pelanggan) {
                $this->email->kirimAturUlangSandi($pelanggan);
            }
        }

        return redirect()->route('lupa-sandi')->with('terkirim', $data['email']);
    }

    public function formAtur(string $token): View|RedirectResponse
    {
        $pelanggan = $this->pemilikToken($token);

        if (! $pelanggan) {
            return $this->tautanTidakBerlaku();
        }

        return view('publik.akun.atur-sandi', ['pelanggan' => $pelanggan, 'token' => $token]);
    }

    public function atur(Request $request, string $token): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)],
        ], ['password.confirmed' => 'Ulangi kata sandi belum sama.'], ['password' => 'kata sandi']);

        $pelanggan = $this->pemilikToken($token);

        if (! $pelanggan) {
            return $this->tautanTidakBerlaku();
        }

        $pelanggan->aturUlangSandi($data['password']);

        Auth::guard('pelanggan')->login($pelanggan);
        $request->session()->regenerate();
        $pelanggan->forceFill(['login_terakhir_pada' => now()])->save();

        return redirect()->route('pesanan-saya')->with('sukses', 'Kata sandi baru tersimpan, dan Anda sudah masuk.');
    }

    private function pemilikToken(string $token): ?Customer
    {
        $pelanggan = Customer::where('token_atur_sandi', hash('sha256', $token))->where('aktif', true)->first();

        return $pelanggan?->aturSandiMasihBerlaku() ? $pelanggan : null;
    }

    private function tautanTidakBerlaku(): RedirectResponse
    {
        return redirect()->route('lupa-sandi')->withErrors(
            'Tautan atur ulang kata sandi sudah tidak berlaku atau sudah digunakan. Silakan minta tautan baru.'
        );
    }
}
