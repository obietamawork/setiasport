<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Akun staf panel (KEP-084), dikelola Pemilik.
 *
 * Akun tidak pernah dihapus, hanya dinonaktifkan: riwayat aktivitas dan
 * catatan "siapa memverifikasi pembayaran ini" harus tetap menyebut namanya.
 * Kata sandi yang diatur Pemilik selalu sementara: pemilik akunnya wajib
 * menggantinya sendiri saat pertama masuk, jadi Pemilik tidak pernah memegang
 * kata sandi orang lain yang masih berlaku.
 */
class PenggunaController extends Controller
{
    public function index(): View
    {
        $daftar = User::with('roles')->orderByDesc('aktif')->orderBy('name')->get()
            ->each(fn (User $u) => $u->setAttribute('sandi_bawaan', $u->masihSandiBawaan()));

        return view('panel.pengguna.index', [
            'daftar' => $daftar,
            'peran' => Role::orderByDesc('slug')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'peran' => ['required', Rule::exists('roles', 'slug')],
            'password' => ['required', Password::min(10)->letters()->numbers()],
        ], [], ['name' => 'nama', 'password' => 'kata sandi sementara']);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'aktif' => true,
            'wajib_ganti_sandi' => true,
        ]);
        $user->roles()->sync(Role::where('slug', $data['peran'])->pluck('id'));

        ActivityLog::catat('pengguna.dibuat', $user, ['email' => $user->email, 'peran' => $data['peran']]);

        return back()->with('sukses', $user->name.' ditambahkan. Berikan kata sandi sementaranya langsung ke orangnya; '
            .'ia akan diminta menggantinya saat pertama masuk.');
    }

    public function update(Request $request, User $pengguna): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($pengguna->id)],
            'peran' => ['required', Rule::exists('roles', 'slug')],
        ], [], ['name' => 'nama']);

        if ($pengguna->punyaPeran('owner') && $data['peran'] !== 'owner') {
            $this->pastikanAdaPemilikLain($pengguna, 'diturunkan dari Pemilik');
        }

        $dari = ['name' => $pengguna->name, 'email' => $pengguna->email, 'peran' => $pengguna->roles->pluck('slug')->implode(',')];
        $pengguna->update(['name' => $data['name'], 'email' => $data['email']]);
        $pengguna->roles()->sync(Role::where('slug', $data['peran'])->pluck('id'));

        ActivityLog::catat('pengguna.diubah', $pengguna, [
            'dari' => $dari,
            'ke' => ['name' => $data['name'], 'email' => $data['email'], 'peran' => $data['peran']],
        ]);

        return back()->with('sukses', 'Data '.$pengguna->name.' disimpan.');
    }

    public function aktif(Request $request, User $pengguna): RedirectResponse
    {
        if ($pengguna->is($request->user('web'))) {
            throw ValidationException::withMessages([
                'pengguna' => 'Anda tidak bisa menonaktifkan akun Anda sendiri.',
            ]);
        }

        if ($pengguna->aktif && $pengguna->punyaPeran('owner')) {
            $this->pastikanAdaPemilikLain($pengguna, 'dinonaktifkan');
        }

        $pengguna->update(['aktif' => ! $pengguna->aktif]);

        // Nonaktif berarti terputus SEKARANG, bukan saat sesinya habis sendiri.
        if (! $pengguna->aktif) {
            $pengguna->keluarkanSesiLain();
        }

        ActivityLog::catat('pengguna.aktif', $pengguna, ['email' => $pengguna->email, 'aktif' => $pengguna->aktif]);

        return back()->with('sukses', $pengguna->aktif
            ? $pengguna->name.' diaktifkan lagi.'
            : $pengguna->name.' dinonaktifkan dan langsung dikeluarkan dari panel.');
    }

    public function aturUlangSandi(Request $request, User $pengguna): RedirectResponse
    {
        if ($pengguna->is($request->user('web'))) {
            return redirect()->route('panel.akun')
                ->with('peringatan', 'Kata sandi Anda sendiri diganti di halaman ini.');
        }

        $request->validate([
            'password' => ['required', Password::min(10)->letters()->numbers()],
        ], [], ['password' => 'kata sandi sementara']);

        $pengguna->forceFill([
            'password' => $request->string('password')->toString(),
            'wajib_ganti_sandi' => true,
        ])->save();
        $pengguna->keluarkanSesiLain();

        ActivityLog::catat('pengguna.sandi-diatur-ulang', $pengguna, ['email' => $pengguna->email]);

        return back()->with('sukses', 'Kata sandi sementara '.$pengguna->name.' diatur. Ia sudah dikeluarkan dari '
            .'semua perangkat dan wajib menggantinya saat masuk lagi.');
    }

    /**
     * Toko tidak boleh kehilangan Pemilik aktif terakhirnya: tanpa itu tidak
     * ada lagi yang bisa membuka Pengaturan, Laporan, atau halaman ini.
     */
    private function pastikanAdaPemilikLain(User $pengguna, string $tindakan): void
    {
        $adaLain = User::where('aktif', true)
            ->whereKeyNot($pengguna->id)
            ->whereHas('roles', fn ($q) => $q->where('slug', 'owner'))
            ->exists();

        if (! $adaLain) {
            throw ValidationException::withMessages([
                'pengguna' => $pengguna->name.' adalah satu-satunya Pemilik aktif, jadi tidak bisa '.$tindakan
                    .'. Jadikan akun lain Pemilik lebih dulu.',
            ]);
        }
    }
}
