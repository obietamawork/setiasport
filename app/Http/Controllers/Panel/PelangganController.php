<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Services\PengirimEmail;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Menu Pelanggan (KEP-157), untuk Pemilik dan Admin (izin pelanggan.kelola).
 *
 * Staf tidak pernah melihat atau mengetik kata sandi pelanggan: yang bisa
 * dilakukan hanya mengirim tautan atur ulang ke email pelanggan itu sendiri.
 * Setiap perubahan tercatat di riwayat aktivitas.
 */
class PelangganController extends Controller
{
    public function index(Request $request): View
    {
        $kata = trim((string) $request->query('q', ''));
        $saring = (string) $request->query('saring', 'semua');

        $daftar = Customer::query()
            ->when($kata !== '', fn ($q) => $q->cari($kata))
            ->when($saring === 'akun', fn ($q) => $q->whereNotNull('password')->whereNotNull('email_terverifikasi_pada'))
            ->when($saring === 'tamu', fn ($q) => $q->where(fn ($w) => $w->whereNull('password')->orWhereNull('email_terverifikasi_pada')))
            ->when($saring === 'nonaktif', fn ($q) => $q->where('aktif', false))
            ->withCount('orders')
            ->withMax('orders', 'created_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('panel.pelanggan.index', compact('daftar', 'kata', 'saring'));
    }

    public function show(Customer $pelanggan): View
    {
        return view('panel.pelanggan.show', [
            'pelanggan' => $pelanggan,
            'daftarAlamat' => $pelanggan->alamat()->get(),
            'pesanan' => $pelanggan->orders()->latest('id')->limit(30)->get(),
            'jumlahPesanan' => $pelanggan->orders()->count(),
        ]);
    }

    public function ubah(Request $request, Customer $pelanggan): RedirectResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('customers', 'email')->ignore($pelanggan->id)],
            'no_hp' => ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s]+$/'],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ], [
            'email.unique' => 'Email ini sudah dipakai pelanggan lain.',
            'no_hp.regex' => 'Nomor HP hanya boleh berisi angka, spasi, tanda plus, dan strip.',
        ], ['no_hp' => 'nomor HP']);

        $sebelum = $pelanggan->only(array_keys($data));
        $pelanggan->update($data);

        ActivityLog::catat('pelanggan.diubah', $pelanggan, [
            'sebelum' => $sebelum,
            'sesudah' => $pelanggan->only(array_keys($data)),
        ]);

        return back()->with('sukses', 'Data pelanggan tersimpan.');
    }

    public function aturSandi(Customer $pelanggan, PengirimEmail $email): RedirectResponse
    {
        if (! $pelanggan->aktif) {
            return back()->withErrors('Akun ini sedang nonaktif. Aktifkan dulu sebelum mengirim tautan.');
        }

        $email->kirimAturUlangSandi($pelanggan);
        ActivityLog::catat('pelanggan.tautan-sandi', $pelanggan, ['email' => $pelanggan->email]);

        return back()->with('sukses', 'Tautan atur ulang kata sandi dikirim ke '.$pelanggan->email.'. Berlaku '.Customer::MENIT_ATUR_SANDI.' menit.');
    }

    public function nonaktifkan(Customer $pelanggan): RedirectResponse
    {
        // Token "ingat saya" diganti supaya perangkat yang masih tersimpan ikut keluar.
        $pelanggan->forceFill(['aktif' => false, 'remember_token' => Str::random(60), 'token_atur_sandi' => null])->save();
        ActivityLog::catat('pelanggan.dinonaktifkan', $pelanggan);

        return back()->with('sukses', 'Akun dinonaktifkan. Pelanggan tidak bisa masuk, tetapi tautan pesanannya tetap berlaku.');
    }

    public function aktifkan(Customer $pelanggan): RedirectResponse
    {
        $pelanggan->forceFill(['aktif' => true])->save();
        ActivityLog::catat('pelanggan.diaktifkan', $pelanggan);

        return back()->with('sukses', 'Akun diaktifkan kembali.');
    }
}
