<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Testimoni;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Testimoni diketik ulang oleh admin dari pesan WhatsApp atau percakapan di
 * toko (KEP-024). Tidak ada formulir publik, jadi tidak ada antrian moderasi.
 */
class TestimoniController extends Controller
{
    public function index()
    {
        return view('panel.testimoni.index', [
            'daftar' => Testimoni::orderBy('urutan')->orderByDesc('id')->get(),
        ]);
    }

    public function create()
    {
        return view('panel.testimoni.form', ['testimoni' => new Testimoni(['aktif' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $testimoni = Testimoni::create($this->validasi($request));

        ActivityLog::catat('testimoni.dibuat', $testimoni, ['nama' => $testimoni->nama]);

        return redirect()->route('panel.testimoni.index')
            ->with('sukses', 'Testimoni dari '.$testimoni->nama.' ditambahkan.');
    }

    public function edit(Testimoni $testimoni)
    {
        return view('panel.testimoni.form', compact('testimoni'));
    }

    public function update(Request $request, Testimoni $testimoni): RedirectResponse
    {
        $data = $this->validasi($request);

        // Tanda contoh lepas hanya kalau isinya diganti, bukan sekadar urutan
        // atau tampil-sembunyi (KEP-035). Lihat PortofolioController::update.
        $isiDiganti = $data['nama'] !== $testimoni->nama || $data['isi'] !== $testimoni->isi;

        $testimoni->update([...$data, 'contoh' => $testimoni->contoh && ! $isiDiganti]);

        ActivityLog::catat('testimoni.diubah', $testimoni, ['nama' => $testimoni->nama]);

        return redirect()->route('panel.testimoni.index')
            ->with('sukses', 'Testimoni dari '.$testimoni->nama.' disimpan.');
    }

    public function destroy(Testimoni $testimoni): RedirectResponse
    {
        $nama = $testimoni->nama;
        $testimoni->delete();

        ActivityLog::catat('testimoni.dihapus', null, ['nama' => $nama]);

        return redirect()->route('panel.testimoni.index')
            ->with('sukses', 'Testimoni dari '.$nama.' dihapus.');
    }

    private function validasi(Request $request): array
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'peran' => ['nullable', 'string', 'max:100'],
            'isi' => ['required', 'string', 'max:600'],
            'bintang' => ['nullable', 'integer', 'min:1', 'max:5'],
            'urutan' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], [], [
            'nama' => 'nama pemberi testimoni',
            'peran' => 'peran atau instansi',
            'isi' => 'isi testimoni',
        ]);

        $data['urutan'] = $data['urutan'] ?? 0;
        $data['aktif'] = $request->boolean('aktif');

        return $data;
    }
}
