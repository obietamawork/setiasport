@extends('layouts.panel')
@section('judul', $portofolio->exists ? 'Ubah portofolio' : 'Tambah portofolio')

@section('isi')
<x-kop :judul="$portofolio->exists ? 'Ubah portofolio' : 'Tambah portofolio'" :kembali="route('panel.portofolio.index')" kembali-label="Daftar portofolio" />

<form method="POST" enctype="multipart/form-data"
      action="{{ $portofolio->exists ? route('panel.portofolio.update', $portofolio) : route('panel.portofolio.store') }}"
      class="kartu mt-6 max-w-xl space-y-4 p-6">
    @csrf
    @if ($portofolio->exists) @method('PUT') @endif

    <div>
        <label for="judul" class="label-form">Judul pekerjaan</label>
        <input type="text" id="judul" name="judul" value="{{ old('judul', $portofolio->judul) }}"
               class="kotak-input" required autofocus maxlength="150"
               placeholder="Jersey tim futsal SMAN 1 Wonogiri">
    </div>

    {{-- Nama instansi diketik manual, bukan diambil dari pesanan: tidak semua
         pemesan bersedia disebut namanya di halaman publik. --}}
    <div>
        <label for="instansi" class="label-form">Nama instansi</label>
        <input type="text" id="instansi" name="instansi" value="{{ old('instansi', $portofolio->instansi) }}"
               class="kotak-input" maxlength="150">
        <p class="mt-1 text-xs leading-relaxed text-muted">
            Tulis hanya kalau pemesannya sudah mengizinkan namanya dipakai. Boleh dikosongkan.
        </p>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <div>
            <label for="kategori" class="label-form">Jenis pekerjaan</label>
            <input type="text" id="kategori" name="kategori" value="{{ old('kategori', $portofolio->kategori) }}"
                   class="kotak-input" maxlength="50" placeholder="Jersey bola">
        </div>
        <div>
            <label for="tahun" class="label-form">Tahun</label>
            <input type="number" id="tahun" name="tahun" value="{{ old('tahun', $portofolio->tahun) }}"
                   class="kotak-input tabular" min="2000" max="{{ date('Y') + 1 }}">
        </div>
        <div>
            <label for="jumlah_pcs" class="label-form">Jumlah pcs</label>
            <input type="number" id="jumlah_pcs" name="jumlah_pcs"
                   value="{{ old('jumlah_pcs', $portofolio->jumlah_pcs) }}"
                   class="kotak-input tabular" min="1" max="100000">
        </div>
    </div>

    <div>
        <label for="ringkasan" class="label-form">Ceritanya</label>
        <textarea id="ringkasan" name="ringkasan" rows="4" class="kotak-input leading-relaxed"
                  maxlength="1000">{{ old('ringkasan', $portofolio->ringkasan) }}</textarea>
        <p class="mt-1 text-xs leading-relaxed text-muted">
            Satu atau dua kalimat: apa yang diminta, apa yang dikerjakan. Bukan iklan.
        </p>
    </div>

    <div>
        <label for="gambar" class="label-form">
            {{ $portofolio->gambar_path ? 'Ganti foto' : 'Foto hasil pekerjaan' }}
        </label>

        @if ($portofolio->gambar_path)
            <img src="{{ asset('storage/'.$portofolio->gambar_path) }}" alt=""
                 class="mb-3 w-48 border border-line object-cover">
        @endif

        <input type="file" id="gambar" name="gambar" accept="image/*" class="kotak-input"
               @unless ($portofolio->gambar_path) required @endunless>
        <p class="mt-1 text-xs text-muted">
            JPG, PNG, atau WebP, maksimal {{ setting('upload_maks_mb', 10) }} MB. Dikecilkan otomatis.
        </p>
    </div>

    <div>
        <label for="urutan" class="label-form">Urutan tampil</label>
        <input type="number" id="urutan" name="urutan" value="{{ old('urutan', $portofolio->urutan ?? 0) }}"
               class="kotak-input tabular max-w-32" min="0" max="999">
        <p class="mt-1 text-xs text-muted">Angka kecil tampil lebih dulu.</p>
    </div>

    <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="aktif" value="1" class="size-4 accent-navy"
               @checked(old('aktif', $portofolio->aktif ?? true))>
        Tampilkan di halaman depan
    </label>

    <div class="flex flex-wrap items-center gap-3 border-t border-line pt-5">
        <button type="submit" class="tombol tombol-navy">Simpan portofolio</button>
        <a href="{{ route('panel.portofolio.index') }}" class="tautan text-sm">Batal</a>
    </div>
</form>

@if ($portofolio->exists)
    <form method="POST" action="{{ route('panel.portofolio.destroy', $portofolio) }}"
          class="mt-6 max-w-xl" onsubmit="return confirm('Hapus portofolio ini beserta fotonya?')">
        @csrf @method('DELETE')
        <button type="submit" class="tautan text-sm text-muted">Hapus portofolio ini</button>
    </form>
@endif
@endsection
