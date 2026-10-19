@extends('layouts.panel')
@section('judul', $bahan->exists ? 'Ubah bahan' : 'Tambah bahan')

@section('isi')
<x-kop :judul="$bahan->exists ? 'Ubah bahan' : 'Tambah bahan'" :kembali="route('panel.bahan.index')" kembali-label="Daftar bahan" />

<form method="POST"
      action="{{ $bahan->exists ? route('panel.bahan.update', $bahan) : route('panel.bahan.store') }}"
      class="kartu mt-6 max-w-xl space-y-4 p-6">
    @csrf
    @if ($bahan->exists) @method('PUT') @endif

    <div>
        <label for="nama" class="label-form">Nama bahan</label>
        <input type="text" id="nama" name="nama" value="{{ old('nama', $bahan->nama) }}"
               class="kotak-input" placeholder="Milano" required autofocus>
        <p class="mt-1 text-xs text-muted">
            Tulis persis seperti nama yang diketik di opsi "Bahan" produk, supaya mudah dikenali saat ditautkan.
        </p>
    </div>

    <div>
        <label for="deskripsi" class="label-form">Deskripsi untuk pelanggan</label>
        <textarea id="deskripsi" name="deskripsi" rows="4" class="kotak-input leading-relaxed"
                  maxlength="1000" placeholder="Contoh: kain breathable, ringan, cepat kering. Cocok untuk latihan dan pertandingan intensif.">{{ old('deskripsi', $bahan->deskripsi) }}</textarea>
        <p class="mt-1 text-xs leading-relaxed text-muted">
            Tampil ke pelanggan begitu bahan ini dipilih saat memesan. Tulis dengan bahasa yang
            dipahami orang awam &mdash; bukan istilah dagang konveksi. Boleh dikosongkan.
        </p>
    </div>

    <div class="flex flex-wrap items-center gap-3 border-t border-line pt-5">
        <button type="submit" class="tombol tombol-navy">Simpan bahan</button>
        <a href="{{ route('panel.bahan.index') }}" class="tautan text-sm">Batal</a>
    </div>
</form>

@if ($bahan->exists)
    <form method="POST" action="{{ route('panel.bahan.destroy', $bahan) }}"
          class="mt-6 max-w-xl" onsubmit="return confirm('Hapus bahan {{ $bahan->nama }}?')">
        @csrf @method('DELETE')
        <button type="submit" class="tautan text-sm text-muted">Hapus bahan ini</button>
    </form>
@endif
@endsection
