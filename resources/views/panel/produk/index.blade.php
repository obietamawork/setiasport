@extends('layouts.panel')
@section('judul', 'Produk')

@section('isi')
<div class="kop-halaman">
    <div>
        <h1 class="kop-judul">Produk</h1>
        <p class="kop-catatan">
            Harga, varian, dan stok diatur di halaman masing-masing produk.
        </p>
    </div>
    <a href="{{ route('panel.produk.create') }}" class="tombol tombol-navy">Tambah produk</a>
</div>

<form method="GET" class="mt-5 flex gap-2">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari nama atau SKU"
           class="kotak-input max-w-xs">
    <button type="submit" class="tombol tombol-garis">Cari</button>
</form>

@if ($produk->isEmpty())
    <div class="kartu mt-6 p-8 text-center">
        <p class="text-lg font-bold">
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
    <div class="kartu mt-5 overflow-x-auto">
        <table class="tabel min-w-160">
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
                    <tr class="{{ $p->aktif ? '' : 'bg-paper text-muted' }}">
                        <td>
                            <a href="{{ route('panel.produk.edit', $p) }}" class="tautan font-medium">{{ $p->nama }}</a>
                            <div class="text-xs text-muted tabular">{{ $p->sku }}</div>
                            @unless ($p->aktif)
                                <span class="chip mt-1 bg-field text-muted">Disembunyikan</span>
                            @endunless
                        </td>
                        <td>{{ $p->category->nama }}</td>
                        <td>{{ $p->fulfillment_type->label() }}</td>
                        <td class="text-right tabular">
                            {{ $p->punyaJalurReadyStock() ? rupiah($p->harga_dasar) : '-' }}
                        </td>
                        <td class="text-right tabular">{{ $p->variants_count }}</td>
                        <td class="text-right">
                            <a href="{{ route('panel.produk.edit', $p) }}" class="tautan">Ubah</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $produk->links() }}</div>
@endif
@endsection
