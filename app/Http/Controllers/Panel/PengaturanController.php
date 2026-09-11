<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Setting;
use App\Services\PengunggahGambar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PengaturanController extends Controller
{
    public function index()
    {
        return view('panel.pengaturan.index', [
            'grup' => Setting::orderBy('grup')->orderBy('key')->get()->groupBy('grup'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $masuk = $request->input('setting', []);
        $diubah = [];

        foreach (Setting::whereIn('key', array_keys($masuk))->get() as $baris) {
            // Path berkas hanya boleh berubah lewat jalur unggahnya sendiri;
            // kalau tidak, satu POST bisa menunjuk gambar QRIS ke berkas lain.
            if ($baris->tipe === 'berkas') {
                continue;
            }

            $nilai = $masuk[$baris->key];

            $aturan = match ($baris->tipe) {
                'int' => ['required', 'integer', 'min:0', 'max:1000000'],
                'bool' => ['nullable'],
                // Profil usaha ditulis berparagraf, tidak muat di batas satu baris.
                'teks' => ['nullable', 'string', 'max:5000'],
                default => ['nullable', 'string', 'max:500'],
            };

            validator(
                ['nilai' => $nilai],
                ['nilai' => $aturan],
                [],
                ['nilai' => $baris->label]
            )->validate();

            if ((string) $baris->value !== (string) $nilai) {
                $diubah[$baris->key] = ['dari' => $baris->value, 'ke' => $nilai];
                Setting::simpan($baris->key, $nilai);
            }
        }

        if ($diubah !== []) {
            ActivityLog::catat('pengaturan.diubah', null, $diubah);
        }

        return back()->with('sukses', $diubah === []
            ? 'Tidak ada pengaturan yang berubah.'
            : count($diubah).' pengaturan disimpan.');
    }

    /**
     * Gambar QRIS statis toko (KEP-023). Dipisahkan dari formulir pengaturan
     * biasa karena berupa berkas, bukan teks, dan karena salah unggah di sini
     * berarti uang pelanggan masuk ke rekening orang lain: yang lama dihapus
     * hanya setelah yang baru tersimpan, dan penggantinya selalu tercatat.
     */
    public function unggahQris(Request $request, PengunggahGambar $pengunggah): RedirectResponse
    {
        $request->validate([
            'qris' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.(setting('upload_maks_mb', 10) * 1024)],
        ], [], ['qris' => 'gambar QRIS']);

        $lama = (string) setting('qris_path', '');
        // QRIS harus terbaca utuh oleh kamera; versi kecilnya tidak dipakai.
        $hasil = $pengunggah->simpan($request->file('qris'), 'qris', denganThumb: false);

        Setting::simpan('qris_path', $hasil['path']);
        $pengunggah->hapus($lama);

        ActivityLog::catat('pengaturan.qris', null, ['dari' => $lama, 'ke' => $hasil['path']]);

        return back()->with('sukses', 'Gambar QRIS diperbarui. Periksa tampilannya di halaman pesanan pelanggan.');
    }

    public function hapusQris(PengunggahGambar $pengunggah): RedirectResponse
    {
        $lama = (string) setting('qris_path', '');

        Setting::simpan('qris_path', '');
        $pengunggah->hapus($lama);

        ActivityLog::catat('pengaturan.qris', null, ['dari' => $lama, 'ke' => '']);

        return back()->with('sukses', 'Gambar QRIS dihapus. Pelanggan kini hanya melihat rekening bank.');
    }
}
