<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Portofolio;
use App\Services\PengunggahGambar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Portofolio pekerjaan instansi (KEP-024). Isinya dikelola sepenuhnya dari
 * panel: pemilik toko yang memutuskan nama instansi mana yang boleh disebut.
 */
class PortofolioController extends Controller
{
    public function index()
    {
        return view('panel.portofolio.index', [
            'daftar' => Portofolio::orderBy('urutan')->orderByDesc('id')->get(),
        ]);
    }

    public function create()
    {
        return view('panel.portofolio.form', [
            'portofolio' => new Portofolio(['aktif' => true, 'tahun' => (int) date('Y')]),
        ]);
    }

    public function store(Request $request, PengunggahGambar $pengunggah): RedirectResponse
    {
        $data = $this->validasi($request);
        $data += $this->simpanGambar($request, $pengunggah);

        $portofolio = Portofolio::create($data);

        ActivityLog::catat('portofolio.dibuat', $portofolio, ['judul' => $portofolio->judul]);

        return redirect()->route('panel.portofolio.index')
            ->with('sukses', 'Portofolio "'.$portofolio->judul.'" ditambahkan.');
    }

    public function edit(Portofolio $portofolio)
    {
        return view('panel.portofolio.form', compact('portofolio'));
    }

    public function update(Request $request, Portofolio $portofolio, PengunggahGambar $pengunggah): RedirectResponse
    {
        $data = $this->validasi($request, $portofolio);
        $baru = $this->simpanGambar($request, $pengunggah);

        /*
         * Contoh yang ISINYA diganti pemilik toko sudah jadi miliknya (KEP-035);
         * tanpa ini, tombol "hapus semua data contoh" ikut menghapus hasil
         * kerjanya. Sekadar mengubah urutan atau menyembunyikan tidak melepas
         * tandanya: isi karangan tidak boleh kehilangan tanda diam-diam.
         */
        $isiDiganti = $baru !== []
            || $data['judul'] !== $portofolio->judul
            || ($data['instansi'] ?? null) !== $portofolio->instansi
            || ($data['ringkasan'] ?? null) !== $portofolio->ringkasan;

        $data['contoh'] = $portofolio->contoh && ! $isiDiganti;

        if ($baru !== []) {
            // Yang lama baru dibuang setelah yang baru tersimpan; kalau unggahan
            // gagal di tengah jalan, halaman depan tetap punya gambar.
            $pengunggah->hapus($portofolio->gambar_path, $portofolio->thumb_path);
            $data += $baru;
        }

        $portofolio->update($data);

        ActivityLog::catat('portofolio.diubah', $portofolio, ['judul' => $portofolio->judul]);

        return redirect()->route('panel.portofolio.index')
            ->with('sukses', 'Portofolio "'.$portofolio->judul.'" disimpan.');
    }

    public function destroy(Portofolio $portofolio, PengunggahGambar $pengunggah): RedirectResponse
    {
        $judul = $portofolio->judul;

        $pengunggah->hapus($portofolio->gambar_path, $portofolio->thumb_path);
        $portofolio->delete();

        ActivityLog::catat('portofolio.dihapus', null, ['judul' => $judul]);

        return redirect()->route('panel.portofolio.index')
            ->with('sukses', 'Portofolio "'.$judul.'" dihapus.');
    }

    /** @return array{gambar_path?: string, thumb_path?: string} */
    private function simpanGambar(Request $request, PengunggahGambar $pengunggah): array
    {
        if (! $request->hasFile('gambar')) {
            return [];
        }

        $hasil = $pengunggah->simpan($request->file('gambar'), 'portofolio');

        return [
            'gambar_path' => $hasil['path'],
            'thumb_path' => $hasil['thumb_path'],
        ];
    }

    private function validasi(Request $request, ?Portofolio $portofolio = null): array
    {
        $data = $request->validate([
            'judul' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:170', 'alpha_dash',
                Rule::unique('portofolios', 'slug')->ignore($portofolio?->id)],
            'instansi' => ['nullable', 'string', 'max:150'],
            'kategori' => ['nullable', 'string', 'max:50'],
            'tahun' => ['nullable', 'integer', 'min:2000', 'max:'.(date('Y') + 1)],
            'jumlah_pcs' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'ringkasan' => ['nullable', 'string', 'max:1000'],
            'urutan' => ['nullable', 'integer', 'min:0', 'max:999'],
            'gambar' => [
                $portofolio?->gambar_path ? 'nullable' : 'required',
                'image', 'mimes:jpg,jpeg,png,webp',
                'max:'.(setting('upload_maks_mb', 10) * 1024),
            ],
        ], [
            'gambar.required' => 'Portofolio tanpa foto tidak meyakinkan siapa pun. Unggah satu gambar.',
        ], [
            'judul' => 'judul pekerjaan',
            'instansi' => 'nama instansi',
            'jumlah_pcs' => 'jumlah pcs',
        ]);

        unset($data['gambar']);

        // validate() hanya mengembalikan kunci yang benar-benar dikirim, jadi
        // slug kosong bisa berarti "tidak ada di formulir", bukan hanya "".
        $data['slug'] = ($data['slug'] ?? null)
            ?: Str::slug($data['judul']).'-'.Str::lower(Str::random(5));
        $data['urutan'] = $data['urutan'] ?? 0;
        $data['aktif'] = $request->boolean('aktif');

        return $data;
    }
}
