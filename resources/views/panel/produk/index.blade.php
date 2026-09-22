@extends('layouts.panel')
@section('judul', 'Produk')

@section('isi')
<x-kop judul="Produk" catatan="Harga, varian, dan stok diatur di halaman masing-masing produk.">
    <form method="GET" class="relative">
        <label for="cari-produk" class="sr-only">Cari produk</label>
        <x-ikon nama="cari" ukuran="size-4"
                class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-navy-300" />
        <input type="search" id="cari-produk" name="q" value="{{ request('q') }}"
               placeholder="Nama atau SKU&hellip;" class="kotak-input w-44 pl-9 sm:w-56">
    </form>

    <a href="{{ route('panel.produk.create') }}" class="tombol tombol-navy tombol-sm">
        <x-ikon nama="tambah" ukuran="size-4" />
        <span class="hidden sm:inline">Tambah produk</span>
    </a>
</x-kop>

@if ($produk->isEmpty())
    <div class="kartu mt-6 p-8 text-center">
        <x-ikon nama="produk" ukuran="size-9" class="mx-auto text-navy-300" />
        <p class="mt-3 text-lg font-bold">
            {{ request('q') ? 'Tidak ada produk yang cocok' : 'Belum ada produk' }}
        </p>
        <p class="mt-2 text-sm text-muted">
            {{ request('q')
                ? 'Coba kata kunci lain, atau tambahkan produk baru.'
                : 'Tambahkan produk pertama, lalu lengkapi varian dan harganya.' }}
        </p>
        <a href="{{ route('panel.produk.create') }}" class="tombol tombol-navy mt-5">Tambah produk</a>
    </div>
@else
    {{-- Di bawah 48rem tabelnya berganti jadi kartu lewat .tabel-hp: enam kolom
         yang digeser ke samping praktis tidak terbaca di HP. --}}
    <div class="kartu-md punya-kop-lekat mt-5">
        <table class="tabel tabel-hp tabel-lekat">
            <thead>
                <tr>
                    <th>Produk</th>
                    <th>Kategori</th>
                    <th>Cara beli</th>
                    <th class="text-right">Harga</th>
                    <th class="text-right">Varian</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($produk as $p)
                    <tr class="group relative hover:bg-navy-50 {{ $p->aktif ? '' : 'text-muted' }}">
                        <td data-utama>
                            <a href="{{ route('panel.produk.edit', $p) }}"
                               class="font-semibold text-navy-700 before:absolute before:inset-0 before:content-['']">
                                {{ $p->nama }}
                            </a>
                            <div class="text-xs text-muted tabular">{{ $p->sku }}</div>
                            @unless ($p->aktif)
                                <span class="chip chip-netral mt-1">
                                    <x-ikon nama="silang" ukuran="size-3.5" />
                                    Disembunyikan
                                </span>
                            @endunless
                        </td>
                        <td data-kolom="Kategori">{{ $p->category->nama }}</td>
                        <td data-kolom="Cara beli">{{ $p->fulfillment_type->label() }}</td>
                        <td data-kolom="Harga" class="text-right tabular">
                            {{ $p->punyaJalurReadyStock() ? rupiah($p->harga_dasar) : '-' }}
                        </td>
                        <td data-kolom="Varian" class="text-right tabular">{{ $p->variants_count }}</td>
                        <td class="hidden text-right md:table-cell">
                            <span class="text-sm text-navy-400 group-hover:text-navy">Ubah</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $produk->links() }}</div>
@endif
@endsection
