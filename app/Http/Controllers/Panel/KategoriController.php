<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class KategoriController extends Controller
{
    public function index()
    {
        return view('panel.kategori.index', [
            'induk' => Category::with(['children' => fn ($q) => $q->withCount('products')])
                ->withCount('products')
                ->whereNull('parent_id')
                ->orderBy('urutan')
                ->get(),
        ]);
    }

    public function create()
    {
        return view('panel.kategori.form', [
            'kategori' => new Category(['aktif' => true]),
            'pilihanInduk' => Category::whereNull('parent_id')->orderBy('nama')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validasi($request);
        $kategori = Category::create($data);

        ActivityLog::catat('kategori.dibuat', $kategori, ['nama' => $kategori->nama]);

        return redirect()->route('panel.kategori.index')
            ->with('sukses', "Kategori \"{$kategori->nama}\" ditambahkan.");
    }

    public function edit(Category $kategori)
    {
        return view('panel.kategori.form', [
            'kategori' => $kategori,
            'pilihanInduk' => Category::whereNull('parent_id')
                ->where('id', '!=', $kategori->id)
                ->orderBy('nama')
                ->get(),
        ]);
    }

    public function update(Request $request, Category $kategori): RedirectResponse
    {
        $kategori->update($this->validasi($request, $kategori));

        ActivityLog::catat('kategori.diubah', $kategori, ['nama' => $kategori->nama]);

        return redirect()->route('panel.kategori.index')
            ->with('sukses', "Kategori \"{$kategori->nama}\" disimpan.");
    }

    public function destroy(Category $kategori): RedirectResponse
    {
        if ($kategori->products()->exists() || $kategori->children()->exists()) {
            return back()->withErrors(
                'Kategori ini masih dipakai produk atau punya subkategori, jadi belum bisa dihapus. '
                .'Pindahkan isinya lebih dulu.'
            );
        }

        $nama = $kategori->nama;
        $kategori->delete();

        ActivityLog::catat('kategori.dihapus', null, ['nama' => $nama]);

        return redirect()->route('panel.kategori.index')->with('sukses', "Kategori \"{$nama}\" dihapus.");
    }

    /**
     * Menggeser urutan kategori satu langkah, naik atau turun.
     *
     * Urutannya dulu hanya bisa diubah dengan mengetik angka di formulir, dan
     * angka itu tidak berarti apa-apa sampai seluruh daftarnya dibuka lagi untuk
     * dilihat hasilnya. Yang dilakukan orang di sini sebenarnya sederhana:
     * "yang ini naikkan satu".
     */
    public function pindah(Request $request, Category $kategori): RedirectResponse
    {
        $arah = $request->string('arah')->toString();

        abort_unless(in_array($arah, ['naik', 'turun'], true), 422);

        $saudara = Category::where('parent_id', $kategori->parent_id)
            ->orderBy('urutan')
            ->orderBy('nama')
            ->get();

        /*
         * Urutannya dirapikan dulu jadi 0,1,2,... Data lama banyak yang urutannya
         * nol semua, dan menukar dua angka nol tidak mengubah apa pun sehingga
         * tombolnya terasa rusak.
         */
        $saudara->each(function (Category $k, int $i) {
            if ($k->urutan !== $i) {
                $k->update(['urutan' => $i]);
            }
        });

        $posisi = $saudara->search(fn (Category $k) => $k->is($kategori));
        $tujuan = $arah === 'naik' ? $posisi - 1 : $posisi + 1;

        // Sudah di ujung: tidak ada yang perlu dikerjakan, dan itu bukan galat.
        if ($tujuan < 0 || $tujuan >= $saudara->count()) {
            return back();
        }

        $tetangga = $saudara[$tujuan];

        $kategori->update(['urutan' => $tujuan]);
        $tetangga->update(['urutan' => $posisi]);

        ActivityLog::catat('kategori.diurutkan', $kategori, ['arah' => $arah]);

        return back()->with('sukses', "Urutan \"{$kategori->nama}\" diubah.");
    }

    private function validasi(Request $request, ?Category $kategori = null): array
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:120', 'alpha_dash',
                Rule::unique('categories', 'slug')->ignore($kategori?->id)],
            'parent_id' => ['nullable', 'exists:categories,id'],
            'deskripsi' => ['nullable', 'string', 'max:255'],
            'urutan' => ['nullable', 'integer', 'min:0', 'max:999'],
            'aktif' => ['boolean'],
        ], [], [
            'nama' => 'nama kategori',
            'parent_id' => 'kategori induk',
        ]);

        $data['slug'] = $data['slug'] ?: Str::slug($data['nama']);
        $data['urutan'] = $data['urutan'] ?? 0;
        $data['aktif'] = $request->boolean('aktif');

        return $data;
    }
}
