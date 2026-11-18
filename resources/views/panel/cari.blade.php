@extends('layouts.panel')
@section('judul', $kata !== '' ? 'Cari "'.Str::limit($kata, 30).'"' : 'Cari')

@section('isi')
<x-kop judul="Cari"
       catatan="Satu kotak untuk kode pesanan, kode ambil, nama atau nomor HP pelanggan, dan nama atau SKU produk. Kode yang tepat langsung membuka pesanannya." />

{{-- Kotak cari sendiri di halaman ini: di HP bilah atas tidak muat kotak cari,
     hanya ikonnya, dan ikon itu membawa orang ke sini. --}}
<form method="GET" action="{{ route('panel.cari') }}" class="flex max-w-2xl flex-wrap gap-2" role="search">
    <label for="cari-halaman" class="sr-only">Kata kunci</label>
    <div class="relative min-w-0 flex-1 basis-60">
        <x-ikon nama="cari" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-navy-300" />
        <input type="search" id="cari-halaman" name="q" value="{{ $kata }}" autofocus
               placeholder="SS-2611-0002, Titan, 0823..., Jersey"
               class="kotak-input py-2.5 pl-10">
    </div>
    <button type="submit" class="tombol tombol-navy">Cari</button>
</form>

@if ($kata === '')
    <div class="kartu mt-6 max-w-2xl p-8 text-center">
        <x-ikon nama="cari" ukuran="size-9" class="mx-auto text-navy-300" />
        <p class="mt-3 font-bold">Ketik kode pesanan, nama pelanggan, atau nama produk</p>
        <p class="mx-auto mt-2 max-w-sm text-sm text-muted">
            Nomor HP boleh diketik dengan cara apa pun: 0812, +62 812, atau 812-3456.
        </p>
    </div>
@elseif ($pesanan->isEmpty() && $pelanggan->isEmpty() && $produk->isEmpty())
    <div class="kartu mt-6 max-w-2xl p-8 text-center">
        <x-ikon nama="cari" ukuran="size-9" class="mx-auto text-navy-300" />
        <p class="mt-3 font-bold">Tidak ada yang cocok dengan "{{ $kata }}"</p>
        <p class="mx-auto mt-2 max-w-sm text-sm text-muted">
            Periksa ejaannya, atau cari dengan potongan yang lebih pendek, misalnya empat angka terakhir nomor HP.
        </p>
    </div>
@else
    <div class="mt-6 grid items-start gap-4 xl:grid-cols-2">
        @if ($pesanan->isNotEmpty())
            <section class="kartu overflow-hidden xl:col-span-2">
                <h2 class="kartu-kop flex items-center gap-2">
                    <span class="lencana-bagian warna-biru"><x-ikon nama="pesanan" ukuran="size-4" /></span>
                    Pesanan
                    <span class="ml-auto text-sm font-normal text-muted tabular">{{ $pesanan->count() }}</span>
                </h2>
                <ul class="divide-y divide-line text-sm">
                    @foreach ($pesanan as $o)
                        <li>
                            <a href="{{ route('panel.pesanan.show', $o) }}"
                               class="flex flex-wrap items-center gap-x-4 gap-y-1 px-5 py-3 hover:bg-navy-50">
                                <span class="tabular font-semibold text-navy-700">{{ $o->kode }}</span>
                                <span class="min-w-0 flex-1">{{ $o->customer->nama }}
                                    <span class="tabular text-muted">{{ $o->customer->no_hp }}</span></span>
                                <span class="chip {{ $o->status->warna() }}">{{ $o->status->label() }}</span>
                                <span class="tabular text-muted">{{ $o->created_at->translatedFormat('j M Y') }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($pelanggan->isNotEmpty())
            <section class="kartu overflow-hidden">
                <h2 class="kartu-kop flex items-center gap-2">
                    <span class="lencana-bagian warna-hijau"><x-ikon nama="orang" ukuran="size-4" /></span>
                    Pelanggan
                    <span class="ml-auto text-sm font-normal text-muted tabular">{{ $pelanggan->count() }}</span>
                </h2>
                <ul class="divide-y divide-line text-sm">
                    @foreach ($pelanggan as $c)
                        <li>
                            {{-- Pelanggan tidak punya halaman sendiri; yang dicari darinya
                                 hampir selalu pesanannya. --}}
                            <a href="{{ route('panel.pesanan.index', ['q' => $c->no_hp]) }}"
                               class="flex items-center justify-between gap-4 px-5 py-3 hover:bg-navy-50">
                                <span class="min-w-0">
                                    <span class="block font-medium">{{ $c->nama }}</span>
                                    <span class="tabular text-muted">{{ $c->no_hp }}</span>
                                </span>
                                <span class="shrink-0 text-muted tabular">{{ $c->orders_count }} pesanan</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($produk->isNotEmpty())
            <section class="kartu overflow-hidden">
                <h2 class="kartu-kop flex items-center gap-2">
                    <span class="lencana-bagian warna-ungu"><x-ikon nama="produk" ukuran="size-4" /></span>
                    Produk
                    <span class="ml-auto text-sm font-normal text-muted tabular">{{ $produk->count() }}</span>
                </h2>
                <ul class="divide-y divide-line text-sm">
                    @foreach ($produk as $p)
                        <li>
                            <a href="{{ route('panel.produk.edit', $p) }}"
                               class="flex items-center gap-3 px-5 py-3 hover:bg-navy-50">
                                @if ($p->images->first())
                                    <img src="{{ $p->images->first()->thumb_url }}" alt="" width="40" height="40" loading="lazy"
                                         class="size-10 shrink-0 rounded-[6px] border border-line object-cover">
                                @else
                                    <span class="flex size-10 shrink-0 items-center justify-center rounded-[6px] border border-dashed border-line-strong text-navy-300">
                                        <x-ikon nama="gambar" ukuran="size-4" />
                                    </span>
                                @endif
                                <span class="min-w-0 flex-1">
                                    <span class="block font-medium">{{ $p->nama }}</span>
                                    <span class="tabular text-xs text-muted">{{ $p->sku }} &middot; {{ $p->category->nama }}</span>
                                </span>
                                @unless ($p->aktif)
                                    <span class="chip chip-netral">Disembunyikan</span>
                                @endunless
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
@endif
@endsection
