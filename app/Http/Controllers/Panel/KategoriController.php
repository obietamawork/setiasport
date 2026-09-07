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
