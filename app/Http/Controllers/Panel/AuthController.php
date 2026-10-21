<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function form()
    {
        return Auth::check()
            ? redirect()->route('panel.dashboard')
            : view('panel.login');
    }

    public function masuk(Request $request): RedirectResponse
    {
        $kredensial = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [], ['email' => 'email', 'password' => 'kata sandi']);

        /*
         * Login pelanggan sudah dibatasi sejak awal, login staf justru belum --
         * padahal akun staf membuka seluruh data pelanggan, pembayaran, dan
         * pengaturan. Kuncinya email + IP: satu orang yang salah ketik tidak
         * mengunci rekan kerjanya di jaringan toko yang sama.
         */
        $kunci = 'masuk-staf:'.Str::lower($kredensial['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($kunci, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Terlalu banyak percobaan masuk. Coba lagi dalam '
                    .ceil(RateLimiter::availableIn($kunci) / 60).' menit.',
            ]);
        }

        if (! Auth::attempt($kredensial, $request->boolean('ingat'))) {
            RateLimiter::hit($kunci, 300);

            throw ValidationException::withMessages([
                'email' => 'Email atau kata sandi tidak cocok.',
            ]);
        }

        RateLimiter::clear($kunci);

        if (! $request->user()->aktif) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'Akun ini sudah dinonaktifkan. Hubungi pemilik toko.',
            ]);
        }

        $request->session()->regenerate();
        $request->user()->forceFill(['login_terakhir_pada' => now()])->save();

        return redirect()->intended(route('panel.dashboard'));
    }

    public function keluar(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('beranda');
    }
}
