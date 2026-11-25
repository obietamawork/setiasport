@extends('layouts.panel')
@section('judul', $bahan->exists ? 'Ubah bahan: '.$bahan->nama : 'Tambah bahan')

@section('isi')
<x-kop :judul="$bahan->exists ? 'Ubah bahan: '.$bahan->nama : 'Tambah bahan'" :kembali="route('panel.bahan.index')" kembali-label="Daftar bahan" />

<form method="POST"
      action="{{ $bahan->exists ? route('panel.bahan.update', $bahan) : route('panel.bahan.store') }}"
      enctype="multipart/form-data"
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

    {{-- Foto wajib (KEP-110): emboss dan tekstur tiap bahan berbeda, dan
         itu yang paling sulit dijelaskan lewat kalimat. Pratinjaunya muncul
         sebelum disimpan, supaya foto yang buram atau salah pilih ketahuan. --}}
    <div x-data="{ pratinjau: @js($bahan->thumb_url) }">
        <label for="gambar" class="label-form">Foto bahan</label>
        <div class="flex flex-wrap items-start gap-4">
            <div class="flex size-28 shrink-0 items-center justify-center overflow-hidden rounded-[10px] border border-line bg-paper">
                <img x-show="pratinjau" :src="pratinjau" alt="Pratinjau foto bahan" class="size-full object-cover"
                     @if (! $bahan->thumb_url) x-cloak @endif
                     @if ($bahan->thumb_url) src="{{ $bahan->thumb_url }}" @endif>
                <x-ikon nama="bahan" ukuran="size-8" class="text-navy-300" x-show="! pratinjau" />
            </div>
            <div class="min-w-0 flex-1 basis-56">
                <input type="file" id="gambar" name="gambar" accept="image/*" class="kotak-input"
                       @change="pratinjau = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : @js($bahan->thumb_url)"
                       @unless ($bahan->gambar_path) required @endunless>
                <x-keterangan-unggah rasio="1:1 (persegi)" ukuran="minimal 600 × 600 px">
                    Foto dekat permukaan kain di cahaya terang, supaya emboss dan teksturnya terlihat.
                </x-keterangan-unggah>
                @if ($bahan->exists && ! $bahan->gambar_path)
                    <strong class="mt-1 block text-xs text-warn">Bahan ini belum berfoto. Fotonya wajib diisi sebelum disimpan.</strong>
                @endif
            </div>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-3 border-t border-line pt-5">
        <button type="submit" class="tombol tombol-navy">Simpan bahan</button>
        <a href="{{ route('panel.bahan.index') }}" class="tautan text-sm">Batal</a>
    </div>
</form>

@if ($bahan->exists)
    <form method="POST" action="{{ route('panel.bahan.destroy', $bahan) }}"
          class="mt-6 max-w-xl" data-konfirmasi="Hapus bahan {{ $bahan->nama }}?" data-konfirmasi-tombol="Hapus">
        @csrf @method('DELETE')
        <button type="submit" class="tautan text-sm text-muted">Hapus bahan ini</button>
    </form>
@endif
@endsection
