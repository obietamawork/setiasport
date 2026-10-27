@extends('layouts.panel')
@section('judul', $testimoni->exists ? 'Ubah testimoni: '.$testimoni->nama : 'Tambah testimoni')

@section('isi')
<x-kop :judul="$testimoni->exists ? 'Ubah testimoni' : 'Tambah testimoni'" :kembali="route('panel.testimoni.index')" kembali-label="Daftar testimoni" />

<form method="POST"
      action="{{ $testimoni->exists ? route('panel.testimoni.update', $testimoni) : route('panel.testimoni.store') }}"
      class="kartu mt-6 max-w-xl space-y-4 p-6">
    @csrf
    @if ($testimoni->exists) @method('PUT') @endif

    <div>
        <label for="nama" class="label-form">Nama</label>
        <input type="text" id="nama" name="nama" value="{{ old('nama', $testimoni->nama) }}"
               class="kotak-input" required autofocus maxlength="100">
        <p class="mt-1 text-xs leading-relaxed text-muted">
            Tulis nama seseorang hanya kalau ia sudah mengizinkan. Nama depan saja juga cukup.
        </p>
    </div>

    <div>
        <label for="peran" class="label-form">Peran atau instansi</label>
        <input type="text" id="peran" name="peran" value="{{ old('peran', $testimoni->peran) }}"
               class="kotak-input" maxlength="100" placeholder="Pelatih SSB Garuda">
    </div>

    <div>
        <label for="isi" class="label-form">Isi testimoni</label>
        <textarea id="isi" name="isi" rows="5" class="kotak-input leading-relaxed"
                  required maxlength="600">{{ old('isi', $testimoni->isi) }}</textarea>
        <p class="mt-1 text-xs leading-relaxed text-muted">
            Salin apa adanya. Kalimat yang terlalu rapi justru terbaca seperti karangan.
        </p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="bintang" class="label-form">Bintang</label>
            <select id="bintang" name="bintang" class="kotak-input">
                <option value="">Tidak ditampilkan</option>
                @foreach (range(5, 1) as $b)
                    <option value="{{ $b }}" @selected(old('bintang', $testimoni->bintang) == $b)>
                        {{ $b }} dari 5
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="urutan" class="label-form">Urutan tampil</label>
            <input type="number" id="urutan" name="urutan" value="{{ old('urutan', $testimoni->urutan ?? 0) }}"
                   class="kotak-input tabular" min="0" max="999">
        </div>
    </div>

    <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="aktif" value="1" class="size-4 accent-navy"
               @checked(old('aktif', $testimoni->aktif ?? true))>
        Tampilkan di halaman depan
    </label>

    <div class="flex flex-wrap items-center gap-3 border-t border-line pt-5">
        <button type="submit" class="tombol tombol-navy">Simpan testimoni</button>
        <a href="{{ route('panel.testimoni.index') }}" class="tautan text-sm">Batal</a>
    </div>
</form>

@if ($testimoni->exists)
    <form method="POST" action="{{ route('panel.testimoni.destroy', $testimoni) }}"
          class="mt-6 max-w-xl" data-konfirmasi="Hapus testimoni dari {{ $testimoni->nama }}?" data-konfirmasi-tombol="Hapus">
        @csrf @method('DELETE')
        <button type="submit" class="tautan text-sm text-muted">Hapus testimoni ini</button>
    </form>
@endif
@endsection
