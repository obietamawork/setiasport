@extends('layouts.panel')
@section('judul', 'Dashboard')

@section('isi')
<h1 class="text-2xl font-extrabold">Yang perlu ditangani</h1>
<p class="mt-1 text-sm text-muted">
    Daftar tindakan, bukan grafik. Kalau bagian ini kosong, katalog sedang sehat.
</p>

<div class="mt-6 space-y-4">

    @if ($perluVerifikasi->isNotEmpty())
        <section class="border-l-2 border-merah bg-field p-5">
            <h2 class="font-bold">Bukti transfer menunggu diperiksa
                <span class="tabular font-normal text-muted">{{ $perluVerifikasi->count() }}</span></h2>
            <p class="mt-1 text-sm text-muted">
                Pelanggan sudah mengirim bukti. Pesanan belum dikerjakan sampai ini diverifikasi.
            </p>
            <ul class="mt-3 space-y-1 text-sm">
                @foreach ($perluVerifikasi as $o)
                    <li class="flex flex-wrap justify-between gap-3">
                        <a href="{{ route('panel.pesanan.show', $o) }}" class="tautan font-medium tabular">{{ $o->kode }}</a>
                        <span class="text-muted">{{ $o->customer->nama }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($lamaBelumDiambil->isNotEmpty())
        <section class="border-l-2 border-kuning bg-field p-5">
            <h2 class="font-bold">Siap tapi belum diambil &gt; {{ $peringatanHari }} hari
                <span class="tabular font-normal text-muted">{{ $lamaBelumDiambil->count() }}</span></h2>
            <p class="mt-1 text-sm text-muted">Barang ini memakan rak toko. Hubungi pelanggannya.</p>
            <ul class="mt-3 space-y-1 text-sm">
                @foreach ($lamaBelumDiambil as $o)
                    <li class="flex flex-wrap justify-between gap-3">
                        <a href="{{ route('panel.pesanan.show', $o) }}" class="tautan font-medium tabular">{{ $o->kode }}</a>
                        <span class="text-muted">
                            {{ $o->customer->nama }} &middot; siap {{ $o->siap_pada?->diffForHumans(short: true) }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($emailBermasalah->isNotEmpty())
        <section class="border-l-2 border-kuning bg-field p-5">
            <h2 class="font-bold">Email tidak sampai</h2>
            <p class="mt-1 text-sm text-muted">
                Tanpa WhatsApp otomatis, pesanan ini perlu ditindaklanjuti manual.
            </p>
            <ul class="mt-3 space-y-1 text-sm">
                @foreach ($emailBermasalah as $log)
                    <li class="flex flex-wrap justify-between gap-3">
                        @if ($log->order)
                            <a href="{{ route('panel.pesanan.show', $log->order) }}" class="tautan tabular">{{ $log->order->kode }}</a>
                        @else
                            <span class="tabular text-muted">-</span>
                        @endif
                        <span class="text-muted">{{ $log->ke }} &middot; {{ $log->status->label() }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($tanpaTier->isNotEmpty())
        <section class="border-l-2 border-merah bg-field p-5">
            <h2 class="font-bold">Harga belum lengkap</h2>
            <p class="mt-1 text-sm text-muted">
                Produk kustom ini tayang tapi belum punya tier harga, jadi pelanggan
                tidak bisa menghitung biayanya sendiri dan akan bertanya ke admin.
            </p>
            <ul class="mt-3 space-y-1 text-sm">
                @foreach ($tanpaTier as $p)
                    <li>
                        <a href="{{ route('panel.produk.edit', $p) }}" class="tautan font-medium">{{ $p->nama }}</a>
                        <span class="text-muted tabular">{{ $p->sku }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($stokHabis->isNotEmpty())
        <section class="border-l-2 border-kuning bg-field p-5">
            <h2 class="font-bold">Stok habis <span class="tabular font-normal text-muted">{{ $stokHabis->count() }}</span></h2>
            <ul class="mt-3 grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                @foreach ($stokHabis->take(10) as $v)
                    <li class="flex justify-between gap-3">
                        <span>{{ $v->product->nama }} <span class="text-muted">{{ $v->label }}</span></span>
                    </li>
                @endforeach
            </ul>
            @if ($stokHabis->count() > 10)
                <a href="{{ route('panel.stok.index') }}" class="tautan mt-3 inline-block text-sm">
                    Lihat semua di halaman stok
                </a>
            @endif
        </section>
    @endif

    @if ($stokMenipis->isNotEmpty())
        <section class="border-l-2 border-line-strong bg-field p-5">
            <h2 class="font-bold">Stok menipis <span class="tabular font-normal text-muted">{{ $stokMenipis->count() }}</span></h2>
            <ul class="mt-3 grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                @foreach ($stokMenipis->take(10) as $v)
                    <li class="flex justify-between gap-3">
                        <span>{{ $v->product->nama }} <span class="text-muted">{{ $v->label }}</span></span>
                        <span class="tabular text-muted">sisa {{ $v->stok_tersedia }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($tanpaTier->isEmpty() && $stokHabis->isEmpty() && $stokMenipis->isEmpty()
         && $perluVerifikasi->isEmpty() && $lamaBelumDiambil->isEmpty() && $emailBermasalah->isEmpty())
        <section class="border border-line bg-field p-8 text-center">
            <p class="text-lg font-bold">Tidak ada yang perlu ditangani</p>
            <p class="mt-2 text-sm text-muted">
                Harga lengkap dan stok masih aman. Waktu yang baik untuk menambah produk.
            </p>
            <a href="{{ route('panel.produk.create') }}" class="tombol tombol-navy mt-5">Tambah produk</a>
        </section>
    @endif
</div>

<h2 class="mt-10 text-lg font-extrabold">Ringkasan katalog</h2>
<dl class="mt-4 grid gap-px border border-line bg-line sm:grid-cols-3">
    <div class="bg-field p-5">
        <dt class="text-sm text-muted">Produk tayang</dt>
        <dd class="numeral mt-2 text-3xl">{{ $produkAktif }}</dd>
    </div>
    <div class="bg-field p-5">
        <dt class="text-sm text-muted">Produk disembunyikan</dt>
        <dd class="numeral mt-2 text-3xl">{{ $produkNonaktif }}</dd>
    </div>
    <div class="bg-field p-5">
        <dt class="text-sm text-muted">Tayang tanpa foto</dt>
        <dd class="numeral mt-2 text-3xl">{{ $tanpaFoto }}</dd>
    </div>
</dl>
@endsection
