<?php

namespace App\Http\Controllers\Panel;

use App\Enums\ProductionStage;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Pekerja;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Daftar pekerja konveksi berikut PIN dan bagiannya (KEP-047).
 *
 * PIN tidak pernah bisa dibaca ulang dari sini, bahkan oleh pemilik toko: yang
 * tersimpan hanya hash-nya. Kalau pekerja lupa, PIN-nya diganti, bukan dilihat.
 */
class PekerjaController extends Controller
{
    public function index(): View
    {
        return view('panel.pekerja.index', [
            'daftar' => Pekerja::orderBy('nama')->get(),
            'tahap' => ProductionStage::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validasi($request);

        $pekerja = Pekerja::create([
            'nama' => $data['nama'],
            'pin_hash' => Pekerja::hashPin($data['pin']),
            'tahap' => $data['tahap'],
            'catatan' => $data['catatan'] ?? null,
            'aktif' => true,
        ]);

        ActivityLog::catat('pekerja.dibuat', $pekerja, ['nama' => $pekerja->nama]);

        return back()->with('sukses', $pekerja->nama.' ditambahkan. Beri tahu PIN-nya langsung ke orangnya.');
    }

    public function update(Request $request, Pekerja $pekerja): RedirectResponse
    {
        $data = $this->validasi($request, $pekerja);

        $pekerja->update([
            'nama' => $data['nama'],
            'tahap' => $data['tahap'],
            'catatan' => $data['catatan'] ?? null,
        ] + ($data['pin'] ? ['pin_hash' => Pekerja::hashPin($data['pin'])] : []));

        ActivityLog::catat('pekerja.diubah', $pekerja, [
            'nama' => $pekerja->nama,
            'pin_diganti' => (bool) $data['pin'],
        ]);

        return back()->with('sukses', 'Data '.$pekerja->nama.' disimpan.');
    }

    /**
     * Pekerja yang keluar dinonaktifkan, bukan dihapus: pekerjaan yang pernah
     * ia tandai tetap harus bisa ditelusuri namanya.
     */
    public function nonaktif(Pekerja $pekerja): RedirectResponse
    {
        $pekerja->update(['aktif' => ! $pekerja->aktif]);

        ActivityLog::catat('pekerja.aktif', $pekerja, [
            'nama' => $pekerja->nama,
            'aktif' => $pekerja->aktif,
        ]);

        return back()->with('sukses', $pekerja->aktif
            ? $pekerja->nama.' diaktifkan lagi.'
            : $pekerja->nama.' dinonaktifkan. PIN-nya tidak berlaku lagi.');
    }

    /** @return array{nama: string, pin: ?string, tahap: list<string>, catatan: ?string} */
    private function validasi(Request $request, ?Pekerja $pekerja = null): array
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'pin' => [$pekerja ? 'nullable' : 'required', 'digits_between:4,8'],
            'tahap' => ['required', 'array', 'min:1'],
            'tahap.*' => [Rule::in(array_column(ProductionStage::cases(), 'value'))],
            'catatan' => ['nullable', 'string', 'max:200'],
        ], [
            'pin.digits_between' => 'PIN berupa 4 sampai 8 angka.',
            'tahap.required' => 'Pilih minimal satu bagian yang dipegang.',
        ], ['nama' => 'nama', 'pin' => 'PIN', 'tahap' => 'bagian']);

        // Dua orang dengan PIN sama membuat catatan siapa mengerjakan apa jadi
        // tebakan, jadi ditolak di sini, bukan dibiarkan bentrok di database.
        if (($data['pin'] ?? null)) {
            $bentrok = Pekerja::where('pin_hash', Pekerja::hashPin($data['pin']))
                ->when($pekerja, fn ($q) => $q->whereKeyNot($pekerja->id))
                ->exists();

            abort_if($bentrok, 422, 'PIN itu sudah dipakai pekerja lain. Pilih angka lain.');
        }

        $data['pin'] = $data['pin'] ?? null;

        return $data;
    }
}
