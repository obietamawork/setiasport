<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Material;
use App\Services\PengunggahGambar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Kamus bahan lintas produk (KEP-072): nama dan penjelasannya ditulis sekali
 * di sini, lalu dipakai ulang oleh opsi "Bahan" di produk mana pun yang
 * memakai bahan yang sama -- bukan diketik ulang tiap kali.
 */
class BahanController extends Controller
{
    public function index()
    {
        return view('panel.bahan.index', [
            'daftar' => Material::withCount('optionValues')->orderBy('nama')->get(),
        ]);
    }

    public function create()
    {
        return view('panel.bahan.form', ['bahan' => new Material]);
    }

    public function store(Request $request, PengunggahGambar $pengunggah): RedirectResponse
    {
        $bahan = new Material($this->validasi($request));
        $bahan->forceFill($this->simpanGambar($request, $pengunggah))->save();

        ActivityLog::catat('bahan.dibuat', $bahan, ['nama' => $bahan->nama]);

        return redirect()->route('panel.bahan.index')
            ->with('sukses', "Bahan \"{$bahan->nama}\" ditambahkan.");
    }

    public function edit(Material $bahan)
    {
        return view('panel.bahan.form', compact('bahan'));
    }

    public function update(Request $request, Material $bahan, PengunggahGambar $pengunggah): RedirectResponse
    {
        $data = $this->validasi($request, $bahan);
        $gambarLama = [$bahan->gambar_path, $bahan->thumb_path];
        $gambarBaru = $this->simpanGambar($request, $pengunggah);

        $bahan->fill($data)->forceFill($gambarBaru)->save();

        // Berkas lama baru dihapus setelah yang baru tersimpan: kalau
        // penyimpanan gagal di tengah, bahan tidak kehilangan fotonya.
        if ($gambarBaru !== []) {
            $pengunggah->hapus(...$gambarLama);
        }

        ActivityLog::catat('bahan.diubah', $bahan, ['nama' => $bahan->nama]);

        return redirect()->route('panel.bahan.index')
            ->with('sukses', "Bahan \"{$bahan->nama}\" disimpan.");
    }

    public function destroy(Material $bahan, PengunggahGambar $pengunggah): RedirectResponse
    {
        if ($bahan->optionValues()->exists()) {
            return back()->withErrors(
                'Bahan ini masih dipakai di salah satu produk, jadi belum bisa dihapus. '
                .'Lepaskan dulu tautannya dari opsi produk yang memakainya.'
            );
        }

        $nama = $bahan->nama;
        $bahan->delete();
        $pengunggah->hapus($bahan->gambar_path, $bahan->thumb_path);

        ActivityLog::catat('bahan.dihapus', null, ['nama' => $nama]);

        return redirect()->route('panel.bahan.index')->with('sukses', "Bahan \"{$nama}\" dihapus.");
    }

    /**
     * Foto bahan wajib (KEP-110). Bahan lama yang belum berfoto ikut ditagih
     * begitu diubah, supaya kamusnya lengkap sedikit demi sedikit tanpa
     * memblokir apa pun yang sudah tayang.
     *
     * @return array{nama: string, deskripsi: ?string}
     */
    private function validasi(Request $request, ?Material $bahan = null): array
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100', Rule::unique('materials', 'nama')->ignore($bahan?->id)],
            'deskripsi' => ['nullable', 'string', 'max:1000'],
            'gambar' => [
                $bahan?->gambar_path ? 'nullable' : 'required',
                'image', 'mimes:jpg,jpeg,png,webp',
                'max:'.(setting('upload_maks_mb', 10) * 1024),
            ],
        ], [
            'gambar.required' => 'Unggah foto close-up bahannya. Emboss dan teksturnya yang membedakan bahan ini dari yang lain.',
        ], ['nama' => 'nama bahan', 'gambar' => 'foto bahan']);

        unset($data['gambar']);

        return $data;
    }

    /** @return array{gambar_path?: string, thumb_path?: ?string} */
    private function simpanGambar(Request $request, PengunggahGambar $pengunggah): array
    {
        if (! $request->hasFile('gambar')) {
            return [];
        }

        $hasil = $pengunggah->simpan($request->file('gambar'), 'bahan');

        return ['gambar_path' => $hasil['path'], 'thumb_path' => $hasil['thumb_path']];
    }
}
