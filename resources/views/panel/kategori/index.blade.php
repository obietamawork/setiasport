@extends('layouts.panel')
@section('judul', 'Kategori')

@section('isi')
<x-kop judul="Kategori"
       catatan="Cara pelanggan menelusuri katalog. Kategori utama boleh punya subkategori.">
    <a href="{{ route('panel.kategori.create') }}" class="tombol tombol-navy tombol-sm">
        <x-ikon nama="tambah" ukuran="size-4" />
        Tambah kategori
    </a>
</x-kop>

@if ($induk->isEmpty())
    <div class="kartu mt-6 p-8 text-center">
        <p class="text-lg font-bold">Belum ada kategori</p>
        <p class="mt-2 text-sm text-muted">Kategori adalah cara pelanggan menelusuri katalog. Mulai dari yang paling besar, misalnya Jersey atau Sepatu.</p>
        <a href="{{ route('panel.kategori.create') }}" class="tombol tombol-navy mt-5">Tambah kategori pertama</a>
    </div>
@else
    <div class="kartu mt-6 divide-y divide-line overflow-hidden">
        @foreach ($induk as $k)
            <div>
                <div class="flex items-center gap-3 px-4 py-3">
                    <span class="font-semibold">{{ $k->nama }}</span>
                    @unless ($k->aktif)
                        <span class="chip chip-netral">Disembunyikan</span>
                    @endunless
                    <span class="ml-auto text-sm text-muted tabular">{{ $k->products_count }} produk</span>
                    <a href="{{ route('panel.kategori.edit', $k) }}" class="tautan text-sm">Ubah</a>
                </div>

                @foreach ($k->children as $anak)
                    <div class="flex items-center gap-3 border-t border-line bg-paper px-4 py-2.5 pl-8">
                        <span class="text-sm">{{ $anak->nama }}</span>
                        @unless ($anak->aktif)
                            <span class="chip chip-netral">Disembunyikan</span>
                        @endunless
                        <span class="ml-auto text-sm text-muted tabular">{{ $anak->products_count }} produk</span>
                        <a href="{{ route('panel.kategori.edit', $anak) }}" class="tautan text-sm">Ubah</a>
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
@endif
@endsection
