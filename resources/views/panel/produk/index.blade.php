@extends('layouts.panel')
@section('judul', 'Produk')

@section('isi')
<div class="flex items-center justify-between gap-4">
    <h1 class="t-wide text-2xl font-extrabold">Produk</h1>
    <a href="{{ route('panel.produk.create') }}" class="tombol tombol-lapangan">Tambah produk</a>
</div>

<form method="GET" class="mt-5 flex gap-2">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari nama atau SKU"
           class="kotak-input max-w-xs">
    <button type="submit" class="tombol tombol-garis">Cari</button>
</form>

@if ($produk->isEmpty())
    <div class="mt-6 border border-line bg-field p-8 text-center">
        <p class="t-wide text-lg font-bold">
            {{ request('q') ? 'Tidak ada produk yang cocok' : 'Belum ada produk' }}
        </p>
        <p class="mt-2 text-sm text-muted">
            {{ request('q')
                ? 'Coba kata kunci lain, atau tambahkan produk baru.'
                : 'Tambahkan produk pertama, lalu lengkapi varian dan harganya.' }}
        </p>
        <a href="{{ route('panel.produk.create') }}" class="tombol tombol-lapangan mt-5">Tambah produk</a>
    </div>
@else
    <div class="mt-5 overflow-x-auto border border-line bg-field">
        <table class="w-full min-w-160 text-sm">
            <thead class="border-b border-line bg-paper text-left">
                <tr>
                    <th class="px-4 py-2.5 font-semibold">Produk</th>
                    <th class="px-4 py-2.5 font-semibold">Kategori</th>
                    <th class="px-4 py-2.5 font-semibold">Cara beli</th>
                    <th class="px-4 py-2.5 text-right font-semibold">Harga</th>
                    <th class="px-4 py-2.5 text-right font-semibold">Varian</th>
                    <th class="px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @foreach ($produk as $p)
                    <tr class="{{ $p->aktif ? '' : 'bg-paper text-muted' }}">
                        <td class="px-4 py-3">
                            <a href="{{ route('panel.produk.edit', $p) }}" class="tautan font-medium">{{ $p->nama }}</a>
                            <div class="text-xs text-muted tabular">{{ $p->sku }}</div>
                            @unless ($p->aktif)
                                <span class="chip mt-1 bg-field text-muted">Disembunyikan</span>
                            @endunless
                        </td>
                        <td class="px-4 py-3">{{ $p->category->nama }}</td>
                        <td class="px-4 py-3">{{ $p->fulfillment_type->label() }}</td>
                        <td class="px-4 py-3 text-right tabular">
                            {{ $p->punyaJalurReadyStock() ? rupiah($p->harga_dasar) : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right tabular">{{ $p->variants_count }}</td>
                        <td class="px-4 py-3 text-right">
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
