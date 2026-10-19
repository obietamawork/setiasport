<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Material;
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

    public function store(Request $request): RedirectResponse
    {
        $bahan = Material::create($this->validasi($request));

        ActivityLog::catat('bahan.dibuat', $bahan, ['nama' => $bahan->nama]);

        return redirect()->route('panel.bahan.index')
            ->with('sukses', "Bahan \"{$bahan->nama}\" ditambahkan.");
    }

    public function edit(Material $bahan)
    {
        return view('panel.bahan.form', compact('bahan'));
    }

    public function update(Request $request, Material $bahan): RedirectResponse
    {
        $bahan->update($this->validasi($request, $bahan));

        ActivityLog::catat('bahan.diubah', $bahan, ['nama' => $bahan->nama]);

        return redirect()->route('panel.bahan.index')
            ->with('sukses', "Bahan \"{$bahan->nama}\" disimpan.");
    }

    public function destroy(Material $bahan): RedirectResponse
    {
        if ($bahan->optionValues()->exists()) {
            return back()->withErrors(
                'Bahan ini masih dipakai di salah satu produk, jadi belum bisa dihapus. '
                .'Lepaskan dulu tautannya dari opsi produk yang memakainya.'
            );
        }

        $nama = $bahan->nama;
        $bahan->delete();

        ActivityLog::catat('bahan.dihapus', null, ['nama' => $nama]);

        return redirect()->route('panel.bahan.index')->with('sukses', "Bahan \"{$nama}\" dihapus.");
    }

    private function validasi(Request $request, ?Material $bahan = null): array
    {
        return $request->validate([
            'nama' => ['required', 'string', 'max:100', Rule::unique('materials', 'nama')->ignore($bahan?->id)],
            'deskripsi' => ['nullable', 'string', 'max:1000'],
        ], [], ['nama' => 'nama bahan']);
    }
}
