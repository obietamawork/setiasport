@extends('layouts.panel')
@section('judul', $banner->exists ? 'Ubah banner: '.$banner->judul : 'Tambah banner')

@section('isi')
<x-kop :judul="$banner->exists ? 'Ubah banner' : 'Tambah banner'" :kembali="route('panel.banner.index')" kembali-label="Daftar banner" />

<form method="POST" enctype="multipart/form-data"
      action="{{ $banner->exists ? route('panel.banner.update', $banner) : route('panel.banner.store') }}"
      class="kartu mt-6 max-w-2xl space-y-5 p-6"
      x-data="{ teks: @js((bool) old('teks_di_atas', $banner->teks_di_atas ?? true)) }">
    @csrf
    @if ($banner->exists) @method('PUT') @endif

    <div>
        <label for="judul" class="label-form">Judul</label>
        <input type="text" id="judul" name="judul" value="{{ old('judul', $banner->judul) }}"
               class="kotak-input" required autofocus maxlength="120"
               placeholder="Diskon 10% jersey tim sampai akhir November">
        <p class="mt-1 text-xs leading-relaxed text-muted">
            Tetap wajib walau tulisannya sudah ada di gambar: dibacakan pembaca layar dan dipakai mesin pencari.
        </p>
    </div>

    <div>
        <label for="gambar" class="label-form">{{ $banner->gambar_path ? 'Ganti gambar' : 'Gambar' }}</label>
        @if ($banner->gambar_path)
            <img src="{{ asset('storage/'.$banner->gambar_path) }}" alt=""
                 class="mb-3 aspect-[8/3] w-full max-w-md border border-line object-cover">
        @endif
        <input type="file" id="gambar" name="gambar" accept="image/*" class="kotak-input"
               @unless ($banner->gambar_path) required @endunless>
        <p class="mt-1 text-xs leading-relaxed text-muted">
            Paling pas 1920 &times; 720 px (perbandingan 8:3). JPG, PNG, atau WebP, maksimal
            {{ setting('upload_maks_mb', 10) }} MB; dikecilkan otomatis.
        </p>
    </div>

    <div>
        <label for="gambar_hp" class="label-form">Gambar untuk HP <span class="font-normal text-muted">(boleh kosong)</span></label>
        @if ($banner->gambar_hp_path)
            <img src="{{ asset('storage/'.$banner->gambar_hp_path) }}" alt=""
                 class="mb-2 aspect-square w-32 border border-line object-cover">
            <label class="mb-3 flex items-center gap-2 text-sm">
                <input type="checkbox" name="hapus_gambar_hp" value="1" class="size-4 accent-navy">
                Hapus, pakai gambar utama saja
            </label>
        @endif
        <input type="file" id="gambar_hp" name="gambar_hp" accept="image/*" class="kotak-input">
        <p class="mt-1 text-xs leading-relaxed text-muted">
            900 &times; 900 px (persegi). Kalau kosong, di HP gambar utama dipotong bagian tengahnya, jadi
            tulisan di tepi kiri-kanan gambar bisa terpotong.
        </p>
    </div>

    <label class="flex items-start gap-2 text-sm">
        <input type="checkbox" name="teks_di_atas" value="1" class="mt-0.5 size-4 accent-navy" x-model="teks">
        <span>
            Tampilkan judul, teks, dan tombol di atas gambar
            <span class="block text-xs leading-relaxed text-muted">
                Matikan kalau gambarnya sudah memuat tulisan (misalnya desain dari Canva), supaya judulnya tidak dobel.
            </span>
        </span>
    </label>

    <div x-show="teks" class="space-y-5">
        <div>
            <label for="subjudul" class="label-form">Teks pendukung <span class="font-normal text-muted">(boleh kosong)</span></label>
            <input type="text" id="subjudul" name="subjudul" value="{{ old('subjudul', $banner->subjudul) }}"
                   class="kotak-input" maxlength="200" placeholder="Minimal 12 pcs, termasuk nama dan nomor punggung.">
        </div>
        <div>
            <label for="teks_tombol" class="label-form">Teks tombol <span class="font-normal text-muted">(boleh kosong)</span></label>
            <input type="text" id="teks_tombol" name="teks_tombol" value="{{ old('teks_tombol', $banner->teks_tombol) }}"
                   class="kotak-input max-w-xs" maxlength="40" placeholder="Pesan sekarang">
        </div>
    </div>

    <div>
        <label for="tautan" class="label-form">Tautan <span class="font-normal text-muted">(boleh kosong)</span></label>
        <input type="text" id="tautan" name="tautan" value="{{ old('tautan', $banner->tautan) }}"
               class="kotak-input" maxlength="500" placeholder="/katalog?jalur=kustom" inputmode="url">
        <p class="mt-1 text-xs leading-relaxed text-muted">
            Halaman di situs ini diawali "/", misalnya <code>/produk/jersey-polos</code>. Kalau teks di atas
            gambar dimatikan, seluruh banner menjadi tautan ini.
        </p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="mulai_pada" class="label-form">Tayang mulai <span class="font-normal text-muted">(boleh kosong)</span></label>
            <input type="date" id="mulai_pada" name="mulai_pada"
                   value="{{ old('mulai_pada', $banner->mulai_pada?->toDateString()) }}" class="kotak-input">
        </div>
        <div>
            <label for="selesai_pada" class="label-form">Sampai <span class="font-normal text-muted">(boleh kosong)</span></label>
            <input type="date" id="selesai_pada" name="selesai_pada"
                   value="{{ old('selesai_pada', $banner->selesai_pada?->toDateString()) }}" class="kotak-input">
        </div>
        <p class="text-xs leading-relaxed text-muted sm:col-span-2">
            Banner hilang sendiri setelah tanggal selesai lewat, jadi promo yang sudah habis tidak terlanjur dijanjikan.
        </p>
    </div>

    <div>
        <label for="urutan" class="label-form">Urutan</label>
        <input type="number" id="urutan" name="urutan" value="{{ old('urutan', $banner->urutan ?? 0) }}"
               class="kotak-input tabular max-w-32" min="0" max="999">
        <p class="mt-1 text-xs text-muted">Angka kecil tampil lebih dulu. Yang pertama paling banyak dilihat.</p>
    </div>

    <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="aktif" value="1" class="size-4 accent-navy"
               @checked(old('aktif', $banner->aktif ?? true))>
        Tayangkan di halaman depan
    </label>

    <div class="flex flex-wrap items-center gap-3 border-t border-line pt-5">
        <button type="submit" class="tombol tombol-navy">Simpan banner</button>
        <a href="{{ route('panel.banner.index') }}" class="tautan text-sm">Batal</a>
    </div>
</form>

@if ($banner->exists)
    <form method="POST" action="{{ route('panel.banner.destroy', $banner) }}"
          class="mt-6 max-w-2xl" data-konfirmasi="Hapus banner {{ $banner->judul }}?"
          data-konfirmasi-pesan="Gambarnya ikut dihapus." data-konfirmasi-tombol="Hapus banner">
        @csrf @method('DELETE')
        <button type="submit" class="tautan text-sm text-muted">Hapus banner ini</button>
    </form>
@endif
@endsection
