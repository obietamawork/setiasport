<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Akun saya (KEP-084): setiap staf mengurus nama, email, dan kata sandinya
 * sendiri. Sebelumnya tidak ada halaman ini sama sekali, sehingga kata sandi
 * bawaan yang tertulis di dokumentasi hanya bisa diganti lewat server.
 */
class AkunSayaController extends Controller
{
    public function show(Request $request): View
    {
        return view('panel.akun', ['user' => $request->user('web')]);
    }

    public function updateProfil(Request $request): RedirectResponse
    {
        $user = $request->user('web');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'no_hp' => ['nullable', 'string', 'max:20'],
            // Email adalah nama masuk: menggantinya butuh bukti pemilik akun.
            'password_sekarang' => [
                Rule::requiredIf(fn () => $request->input('email') !== $user->email),
                'nullable', 'current_password:web',
            ],
        ], [
            'password_sekarang.required' => 'Masukkan kata sandi sekarang untuk mengganti email.',
            'password_sekarang.current_password' => 'Kata sandi sekarang tidak cocok.',
        ], ['name' => 'nama', 'no_hp' => 'nomor HP']);

        $dari = $user->only(['name', 'email', 'no_hp']);
        $user->update(collect($data)->only(['name', 'email', 'no_hp'])->all());

        ActivityLog::catat('akun.profil-diubah', $user, [
            'dari' => $dari,
            'ke' => $user->only(['name', 'email', 'no_hp']),
        ]);

        return back()->with('sukses', 'Profil disimpan.');
    }

    public function updateSandi(Request $request): RedirectResponse
    {
        $user = $request->user('web');

        // Bag sendiri: halaman ini punya dua formulir, dan galat kata sandi tidak
        // boleh ikut tampil di formulir profil (yang juga punya isian sandi).
        $request->validateWithBag('sandi', [
            'password_sekarang' => ['required', 'current_password:web'],
            'password' => [
                'required', 'confirmed', 'different:password_sekarang',
                Password::min(10)->letters()->numbers(),
            ],
        ], [
            'password_sekarang.current_password' => 'Kata sandi sekarang tidak cocok.',
            'password.different' => 'Kata sandi baru harus berbeda dari yang sekarang.',
            'password.confirmed' => 'Ulangi kata sandi baru dengan persis sama.',
        ], [
            'password_sekarang' => 'kata sandi sekarang',
            'password' => 'kata sandi baru',
        ]);

        $user->forceFill([
            'password' => $request->string('password')->toString(),
            'wajib_ganti_sandi' => false,
        ])->save();

        // Siapa pun yang mungkin tahu sandi lama terputus dari perangkatnya.
        $user->keluarkanSesiLain($request->session()->getId());
        $request->session()->regenerate();

        ActivityLog::catat('akun.sandi-diganti', $user);

        return redirect()->route('panel.akun')
            ->with('sukses', 'Kata sandi diganti. Perangkat lain yang masih masuk dengan akun ini sudah dikeluarkan.');
    }
}
