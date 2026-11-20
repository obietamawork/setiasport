<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use App\Models\AlamatPelanggan;
use App\Models\Customer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Akun saya (KEP-157): profil, ganti kata sandi, dan buku alamat.
 *
 * Email tidak bisa diubah di sini: email yang mengikat pesanan ke akun
 * (KEP-020). Penggantiannya lewat CS, yang mengubahnya dari menu Pelanggan.
 */
class AkunSayaController extends Controller
{
    public function show(): View
    {
        $pelanggan = $this->pelanggan();

        return view('publik.akun.saya', [
            'pelanggan' => $pelanggan,
            'daftarAlamat' => $pelanggan->alamat()->get(),
        ]);
    }

    public function profil(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'no_hp' => ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s]+$/'],
        ], ['no_hp.regex' => 'Nomor HP hanya boleh berisi angka, spasi, tanda plus, dan strip.'], ['nama' => 'nama', 'no_hp' => 'nomor HP']);

        $this->pelanggan()->update($data);

        return back()->with('sukses', 'Profil tersimpan.');
    }

    public function sandi(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sandi_lama' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], ['password.confirmed' => 'Ulangi kata sandi baru belum sama.'], ['sandi_lama' => 'kata sandi saat ini', 'password' => 'kata sandi baru']);

        $pelanggan = $this->pelanggan();

        if (! Hash::check($data['sandi_lama'], (string) $pelanggan->password)) {
            throw ValidationException::withMessages(['sandi_lama' => 'Kata sandi saat ini tidak cocok.']);
        }

        // Token "ingat saya" diputar dan sesi diperbarui; sesi di perangkat lain
        // keluar sendiri lewat PastikanPelangganAktif.
        $pelanggan->forceFill(['password' => $data['password'], 'remember_token' => Str::random(60)])->save();
        $request->session()->regenerate();

        return back()->with('sukses', 'Kata sandi baru tersimpan. Perangkat lain yang masuk ke akun ini sudah dikeluarkan.');
    }

    public function simpanAlamat(Request $request): RedirectResponse
    {
        if ($this->pelanggan()->tambahAlamat($this->dataAlamat($request)) === null) {
            throw ValidationException::withMessages([
                'alamat' => 'Buku alamat sudah berisi '.AlamatPelanggan::BATAS.' alamat. Hapus salah satu sebelum menambah yang baru.',
            ]);
        }

        return back()->with('sukses', 'Alamat tersimpan.');
    }

    public function ubahAlamat(Request $request, int $alamat): RedirectResponse
    {
        $this->alamatMilik($alamat)->update($this->dataAlamat($request));

        return back()->with('sukses', 'Alamat diperbarui.');
    }

    public function hapusAlamat(int $alamat): RedirectResponse
    {
        $hapus = $this->alamatMilik($alamat);

        DB::transaction(function () use ($hapus) {
            $hapus->delete();

            // Alamat utama yang dihapus digantikan alamat tersisa yang paling lama.
            if ($hapus->utama) {
                $this->pelanggan()->alamat()->reorder()->orderBy('id')->first()?->update(['utama' => true]);
            }
        });

        return back()->with('sukses', 'Alamat dihapus.');
    }

    public function jadikanUtama(int $alamat): RedirectResponse
    {
        $utama = $this->alamatMilik($alamat);

        DB::transaction(function () use ($utama) {
            $this->pelanggan()->alamat()->update(['utama' => false]);
            $utama->update(['utama' => true]);
        });

        return back()->with('sukses', 'Alamat utama diganti.');
    }

    /** @return array<string, mixed> */
    private function dataAlamat(Request $request): array
    {
        return $request->validate([
            'label' => ['required', 'string', 'max:40'],
            'alamat' => ['required', 'string', 'max:500'],
            'area_kode' => ['nullable', 'string', 'max:60'],
            'area_nama' => ['nullable', 'string', 'max:150'],
            'kode_pos' => ['nullable', 'string', 'max:10', 'regex:/^\d+$/'],
            'tautan_lokasi' => ['nullable', 'url:https', 'max:500'],
        ], [
            'tautan_lokasi.url' => 'Tautan lokasinya sepertinya tidak valid. Tempel tautan dari tombol Bagikan di Google Maps.',
        ], ['label' => 'nama alamat', 'alamat' => 'alamat lengkap', 'kode_pos' => 'kode pos', 'tautan_lokasi' => 'tautan lokasi']);
    }

    /** Alamat milik pelanggan lain sama dengan alamat yang tidak ada: 404. */
    private function alamatMilik(int $id): AlamatPelanggan
    {
        return $this->pelanggan()->alamat()->findOrFail($id);
    }

    private function pelanggan(): Customer
    {
        /** @var Customer */
        return Auth::guard('pelanggan')->user();
    }
}
