<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\PengunggahGambar;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class GambarProdukController extends Controller
{
    public function __construct(private PengunggahGambar $pengunggah) {}

    public function store(Request $request, Product $produk): RedirectResponse
    {
        $maks = (int) setting('upload_maks_mb', 10) * 1024;

        // Daftar warna yang boleh ditandai dibatasi ke warna varian yang
        // benar-benar ada (KEP-066), supaya staf memilih dari kotak, bukan
        // mengetik, dan salah ketik tidak diam-diam membuat gambar tidak
        // pernah cocok dengan tombol warna di halaman produk.
        $warnaTersedia = $produk->variants->pluck('warna')->filter()->unique()->values();

        $request->validate([
            'berkas' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', "max:{$maks}"],
            'external_url' => ['nullable', 'url', 'max:500'],
            'alt' => ['nullable', 'string', 'max:150'],
            'warna' => ['nullable', 'string', Rule::in($warnaTersedia)],
        ], [
            'berkas.mimes' => 'Gambar harus JPG, PNG, atau WebP.',
            'berkas.max' => "Ukuran gambar maksimal {$maks} KB.",
            'external_url.url' => 'Tautan gambar harus lengkap, diawali https://',
            'warna.in' => 'Warna itu bukan salah satu varian produk ini.',
        ], ['berkas' => 'berkas gambar', 'external_url' => 'tautan gambar', 'alt' => 'keterangan gambar']);

        if (! $request->hasFile('berkas') && ! $request->filled('external_url')) {
            return back()->withErrors(
                'Unggah berkas gambar, atau tempelkan tautan gambar dari CDN. Pilih salah satu.'
            );
        }

        $data = [
            'alt' => $request->input('alt') ?: $produk->nama,
            'urutan' => (int) $produk->images()->max('urutan') + 1,
            'warna' => $request->input('warna') ?: null,
        ];

        if ($request->hasFile('berkas')) {
            try {
                $data += $this->pengunggah->simpan($request->file('berkas'));
            } catch (RuntimeException $e) {
                return back()->withErrors($e->getMessage());
            }
        } else {
            $data['external_url'] = $request->input('external_url');
        }

        $gambar = $produk->images()->create($data);

        ActivityLog::catat('gambar.ditambah', $produk, [
            'sumber' => $gambar->dariCdn() ? 'cdn' : 'unggah',
        ]);

        return back()->with('sukses', $gambar->dariCdn()
            ? 'Gambar dari CDN ditambahkan.'
            : 'Gambar diunggah, dikecilkan, dan dikonversi ke WebP.');
    }

    public function utama(Product $produk, ProductImage $gambar): RedirectResponse
    {
        abort_unless($gambar->product_id === $produk->id, 404);

        // Urutan menentukan mana yang tampil di kartu katalog dan halaman produk.
        $produk->images()->update(['urutan' => DB::raw('urutan + 1')]);
        $gambar->update(['urutan' => 0]);

        return back()->with('sukses', 'Gambar utama diganti.');
    }

    public function destroy(Product $produk, ProductImage $gambar): RedirectResponse
    {
        abort_unless($gambar->product_id === $produk->id, 404);

        $this->pengunggah->hapus($gambar->path, $gambar->thumb_path);
        $gambar->delete();

        ActivityLog::catat('gambar.dihapus', $produk);

        return back()->with('sukses', 'Gambar dihapus.');
    }
}
