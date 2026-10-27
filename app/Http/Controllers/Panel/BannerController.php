<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Banner;
use App\Services\PengunggahGambar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Banner promo halaman depan (KEP-092). Satu banner tampil diam; dua atau
 * lebih bisa digeser pengunjung, tidak pernah bergeser sendiri.
 */
class BannerController extends Controller
{
    /** Lebar simpan gambar desktop dan HP; desktop selebar monitor 1920px. */
    private const LEBAR_DESKTOP = 1920;

    private const LEBAR_HP = 900;

    public function index()
    {
        return view('panel.banner.index', [
            'daftar' => Banner::orderBy('urutan')->orderByDesc('id')->get(),
        ]);
    }

    public function create()
    {
        return view('panel.banner.form', [
            'banner' => new Banner(['aktif' => true, 'teks_di_atas' => true]),
        ]);
    }

    public function store(Request $request, PengunggahGambar $pengunggah): RedirectResponse
    {
        $data = $this->validasi($request) + $this->simpanGambar($request, $pengunggah);

        $banner = Banner::create($data);

        ActivityLog::catat('banner.dibuat', $banner, ['judul' => $banner->judul]);

        return redirect()->route('panel.banner.index')
            ->with('sukses', 'Banner "'.$banner->judul.'" ditambahkan.');
    }

    public function edit(Banner $banner)
    {
        return view('panel.banner.form', compact('banner'));
    }

    public function update(Request $request, Banner $banner, PengunggahGambar $pengunggah): RedirectResponse
    {
        $data = $this->validasi($request, $banner);
        $baru = $this->simpanGambar($request, $pengunggah);

        // Yang lama baru dibuang setelah yang baru tersimpan; kalau unggahan
        // gagal di tengah jalan, halaman depan tetap punya gambar.
        if (isset($baru['gambar_path'])) {
            $pengunggah->hapus($banner->gambar_path);
        }

        if (isset($baru['gambar_hp_path']) || $request->boolean('hapus_gambar_hp')) {
            $pengunggah->hapus($banner->gambar_hp_path);
            $data['gambar_hp_path'] = $baru['gambar_hp_path'] ?? null;
        }

        $banner->update($baru + $data);

        ActivityLog::catat('banner.diubah', $banner, ['judul' => $banner->judul]);

        return redirect()->route('panel.banner.index')
            ->with('sukses', 'Banner "'.$banner->judul.'" disimpan.');
    }

    public function destroy(Banner $banner, PengunggahGambar $pengunggah): RedirectResponse
    {
        $judul = $banner->judul;

        $pengunggah->hapus($banner->gambar_path, $banner->gambar_hp_path);
        $banner->delete();

        ActivityLog::catat('banner.dihapus', null, ['judul' => $judul]);

        return redirect()->route('panel.banner.index')->with('sukses', 'Banner "'.$judul.'" dihapus.');
    }

    /** @return array{gambar_path?: string, gambar_hp_path?: string} */
    private function simpanGambar(Request $request, PengunggahGambar $pengunggah): array
    {
        $hasil = [];

        if ($request->hasFile('gambar')) {
            $hasil['gambar_path'] = $pengunggah->simpan(
                $request->file('gambar'), 'banner', denganThumb: false, lebarMaks: self::LEBAR_DESKTOP
            )['path'];
        }

        if ($request->hasFile('gambar_hp')) {
            $hasil['gambar_hp_path'] = $pengunggah->simpan(
                $request->file('gambar_hp'), 'banner', denganThumb: false, lebarMaks: self::LEBAR_HP
            )['path'];
        }

        return $hasil;
    }

    private function validasi(Request $request, ?Banner $banner = null): array
    {
        $gambar = ['image', 'mimes:jpg,jpeg,png,webp', 'max:'.(setting('upload_maks_mb', 10) * 1024)];

        $data = $request->validate([
            'judul' => ['required', 'string', 'max:120'],
            'subjudul' => ['nullable', 'string', 'max:200'],
            'teks_tombol' => ['nullable', 'string', 'max:40'],
            // Alamat di situs ini ("/katalog?jalur=kustom") atau https. Skema lain,
            // termasuk javascript: dan //domain-lain, ditolak: tautan ini tampil
            // di halaman depan dan diklik pengunjung mana pun.
            'tautan' => ['nullable', 'string', 'max:500', 'regex:#^(/(?!/)|https://)#'],
            'gambar' => [$banner?->gambar_path ? 'nullable' : 'required', ...$gambar],
            'gambar_hp' => ['nullable', ...$gambar],
            'mulai_pada' => ['nullable', 'date'],
            'selesai_pada' => ['nullable', 'date', 'after_or_equal:mulai_pada'],
            'urutan' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], [
            'gambar.required' => 'Banner butuh gambar. Unggah satu, ukuran 1920×720 px paling pas.',
            'tautan.regex' => 'Tautan harus alamat di situs ini (diawali "/") atau alamat lengkap yang diawali https://.',
            'selesai_pada.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
        ], [
            'judul' => 'judul',
            'subjudul' => 'teks pendukung',
            'teks_tombol' => 'teks tombol',
            'gambar_hp' => 'gambar untuk HP',
            'mulai_pada' => 'tanggal mulai',
            'selesai_pada' => 'tanggal selesai',
        ]);

        unset($data['gambar'], $data['gambar_hp']);

        $data['urutan'] = $data['urutan'] ?? 0;
        $data['aktif'] = $request->boolean('aktif');
        $data['teks_di_atas'] = $request->boolean('teks_di_atas');

        return $data;
    }
}
