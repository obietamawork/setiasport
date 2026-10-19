@extends('layouts.panel')
@section('judul', 'Bahan')

@section('isi')
<x-kop judul="Bahan"
       catatan="Kamus bahan yang dipakai lintas produk. Tulis penjelasannya sekali di sini, lalu tautkan ke opsi 'Bahan' di produk mana pun yang memakai bahan yang sama — pelanggan awam akan melihat penjelasannya saat memilih.">
    <a href="{{ route('panel.bahan.create') }}" class="tombol tombol-navy tombol-sm">
        <x-ikon nama="tambah" ukuran="size-4" />
        Tambah bahan
    </a>
</x-kop>

@if ($daftar->isEmpty())
    <div class="kartu mt-6 p-8 text-center">
        <x-ikon nama="bahan" ukuran="size-9" class="mx-auto text-navy-300" />
        <p class="mt-3 text-lg font-bold">Belum ada bahan di kamus</p>
        <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-muted">
            Mulai dari bahan yang paling sering dipakai, misalnya Milano atau Dryfit Serena.
            Begitu ditambahkan di sini, bisa langsung ditautkan ke opsi "Bahan" produk mana pun.
        </p>
        <a href="{{ route('panel.bahan.create') }}" class="tombol tombol-navy mt-5">Tambah bahan pertama</a>
    </div>
@else
    <div class="mt-5 kartu divide-y divide-line overflow-hidden">
        @foreach ($daftar as $b)
            <div class="relative flex flex-wrap items-start gap-x-3 gap-y-1 px-4 py-3 hover:bg-navy-50">
                <div class="min-w-0">
                    <a href="{{ route('panel.bahan.edit', $b) }}"
                       class="font-semibold before:absolute before:inset-0 before:content-['']">
                        {{ $b->nama }}
                    </a>
                    @if ($b->deskripsi)
                        <p class="mt-0.5 max-w-xl text-sm leading-relaxed text-muted">{{ Str::limit($b->deskripsi, 140) }}</p>
                    @else
                        <p class="mt-0.5 text-sm text-muted italic">Belum ada deskripsi</p>
                    @endif
                </div>

                <span class="ml-auto shrink-0 text-sm text-muted tabular">
                    @if ($b->option_values_count > 0)
                        dipakai {{ $b->option_values_count }}×
                    @else
                        belum dipakai
                    @endif
                </span>
            </div>
        @endforeach
    </div>

    <p class="kolom-baca mt-5 text-sm leading-relaxed text-muted">
        Bahan yang masih ditautkan ke opsi produk tidak bisa dihapus. Lepaskan dulu tautannya
        dari produk yang memakainya kalau memang sudah tidak relevan.
    </p>
@endif
@endsection
