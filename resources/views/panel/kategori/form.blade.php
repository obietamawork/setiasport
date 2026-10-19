@extends('layouts.panel')
@section('judul', $kategori->exists ? 'Ubah kategori' : 'Tambah kategori')

@section('isi')
<x-kop :judul="$kategori->exists ? 'Ubah kategori' : 'Tambah kategori'" :kembali="route('panel.kategori.index')" kembali-label="Daftar kategori" />

<form method="POST"
      action="{{ $kategori->exists ? route('panel.kategori.update', $kategori) : route('panel.kategori.store') }}"
      class="kartu mt-6 max-w-xl space-y-4 p-6">
    @csrf
    @if ($kategori->exists) @method('PUT') @endif

    <div>
        <label for="nama" class="label-form">Nama kategori</label>
        <input type="text" id="nama" name="nama" value="{{ old('nama', $kategori->nama) }}"
               class="kotak-input" required autofocus>
    </div>

    <div>
        <label for="parent_id" class="label-form">Kategori induk</label>
        <select id="parent_id" name="parent_id" class="kotak-input">
            <option value="">Tidak ada: ini kategori utama</option>
            @foreach ($pilihanInduk as $p)
                <option value="{{ $p->id }}" @selected(old('parent_id', $kategori->parent_id) == $p->id)>
                    {{ $p->nama }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="slug" class="label-form">Slug URL</label>
        <input type="text" id="slug" name="slug" value="{{ old('slug', $kategori->slug) }}"
               class="kotak-input" placeholder="Dibuat otomatis dari nama">
        <p class="mt-1 text-xs text-muted">
            Bagian alamat halaman. Mengubahnya akan memutus tautan lama yang sudah dibagikan.
        </p>
    </div>

    <div>
        <label for="deskripsi" class="label-form">Deskripsi singkat</label>
        <input type="text" id="deskripsi" name="deskripsi" value="{{ old('deskripsi', $kategori->deskripsi) }}"
               class="kotak-input" maxlength="255">
        <p class="mt-1 text-xs text-muted">Tampil di bawah judul halaman kategori. Boleh dikosongkan.</p>
    </div>

    <div>
        <label for="urutan" class="label-form">Urutan tampil</label>
        <input type="number" id="urutan" name="urutan" value="{{ old('urutan', $kategori->urutan ?? 0) }}"
               class="kotak-input" min="0" max="999">
    </div>

    <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="aktif" value="1" class="size-4 accent-navy"
               @checked(old('aktif', $kategori->aktif ?? true))>
        Tampilkan di katalog
    </label>

    <div class="flex flex-wrap items-center gap-3 border-t border-line pt-5">
        <button type="submit" class="tombol tombol-navy">Simpan kategori</button>
        <a href="{{ route('panel.kategori.index') }}" class="tautan text-sm">Batal</a>
    </div>
</form>

@if ($kategori->exists)
    <form method="POST" action="{{ route('panel.kategori.destroy', $kategori) }}"
          class="mt-6 max-w-xl" onsubmit="return confirm({{ \Illuminate\Support\Js::from('Hapus kategori '.$kategori->nama.'?') }})">
        @csrf @method('DELETE')
        <button type="submit" class="tautan text-sm text-muted">Hapus kategori ini</button>
    </form>
@endif
@endsection
