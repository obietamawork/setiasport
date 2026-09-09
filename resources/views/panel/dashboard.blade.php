@extends('layouts.panel')
@section('judul', 'Dashboard')

@section('isi')
<div class="kop-halaman">
    <div>
        <h1 class="kop-judul">Dashboard</h1>
        <p class="kop-catatan">
            Daftar tindakan, bukan grafik. Kalau bagian bawah kosong, toko sedang sehat.
        </p>
    </div>
</div>

{{-- Pemilik toko membuka halaman ini tiap pagi. Pertanyaan pertamanya selalu
     sama: apa yang terjadi hari ini, dan apa yang sedang menunggu. --}}
<div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
    <div class="kartu p-4">
        <p class="text-xs text-muted">Pesanan masuk hari ini</p>
        <p class="numeral mt-2 text-2xl">{{ $pesananHariIni }}</p>
    </div>
    <div class="kartu p-4">
        <p class="text-xs text-muted">Uang diterima hari ini</p>
        <p class="numeral mt-2 text-2xl">{{ rupiah_ringkas($uangHariIni) }}</p>
    </div>
    <a href="{{ route('panel.pesanan.index', ['status' => 'DIPROSES']) }}" class="kartu kartu-tautan p-4">
        <p class="text-xs text-muted">Sedang dikerjakan</p>
        <p class="numeral mt-2 text-2xl">{{ $sedangDikerjakan }}</p>
    </a>
    <a href="{{ route('panel.serah-terima') }}" class="kartu kartu-tautan p-4">
        <p class="text-xs text-muted">Siap diambil</p>
        <p class="numeral mt-2 text-2xl {{ $siapDiambil > 0 ? 'text-merah' : '' }}">{{ $siapDiambil }}</p>
    </a>
</div>

<h2 class="mt-9 text-lg font-extrabold">Yang perlu ditangani</h2>

@foreach ($masalahSistem as $m)
    <section class="notis notis-galat mt-4">
        <p class="font-bold">{{ $m['judul'] }}</p>
        <p class="kolom-baca mt-1">{{ $m['isi'] }}</p>
    </section>
@endforeach

{{-- Dua kolom di layar lebar. Baris yang meregang sampai seribu piksel memaksa
     mata berjalan jauh dari nama barang ke angkanya. --}}
<div class="mt-4 grid items-start gap-3 xl:grid-cols-2">

    @if ($perluVerifikasi->isNotEmpty())
        <section class="kartu overflow-hidden">
            <div class="kartu-kop flex items-center gap-2">
                <span class="tahap-titik tahap-titik-kini"></span>
                Bukti transfer menunggu diperiksa
                <span class="chip chip-merah ml-auto">{{ $perluVerifikasi->count() }}</span>
            </div>
            <p class="border-b border-line px-5 py-2.5 text-sm text-muted">
                Pesanan belum dikerjakan sampai ini diverifikasi.
            </p>
            <ul class="divide-y divide-line text-sm">
                @foreach ($perluVerifikasi as $o)
                    <li>
                        <a href="{{ route('panel.pesanan.show', $o) }}"
                           class="flex items-center justify-between gap-4 px-5 py-2.5 hover:bg-navy-50">
                            <span class="tabular font-medium">{{ $o->kode }}</span>
                            <span class="text-muted">{{ $o->customer->nama }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($lamaBelumDiambil->isNotEmpty())
        <section class="kartu overflow-hidden">
            <div class="kartu-kop flex items-center gap-2">
                Siap tapi belum diambil lebih dari {{ $peringatanHari }} hari
                <span class="chip chip-kuning ml-auto">{{ $lamaBelumDiambil->count() }}</span>
            </div>
            <p class="border-b border-line px-5 py-2.5 text-sm text-muted">
                Barang ini memakan rak toko. Hubungi pelanggannya.
            </p>
            <ul class="divide-y divide-line text-sm">
                @foreach ($lamaBelumDiambil as $o)
                    <li>
                        <a href="{{ route('panel.pesanan.show', $o) }}"
                           class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 px-5 py-2.5 hover:bg-navy-50">
                            <span class="tabular font-medium">{{ $o->kode }}</span>
                            <span class="text-muted">
                                {{ $o->customer->nama }}, siap {{ $o->siap_pada?->diffForHumans(short: true) }}
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($tanpaTier->isNotEmpty())
        <section class="kartu overflow-hidden">
            <div class="kartu-kop">Harga belum lengkap</div>
            <p class="border-b border-line px-5 py-2.5 text-sm text-muted">
                Produk kustom ini tayang tapi belum punya tier harga, jadi pelanggan
                tidak bisa menghitung biayanya sendiri dan akan bertanya ke admin.
            </p>
            <ul class="divide-y divide-line text-sm">
                @foreach ($tanpaTier as $p)
                    <li>
                        <a href="{{ route('panel.produk.edit', $p) }}"
                           class="flex items-center justify-between gap-4 px-5 py-2.5 hover:bg-navy-50">
                            <span class="font-medium">{{ $p->nama }}</span>
                            <span class="tabular text-muted">{{ $p->sku }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($emailBermasalah->isNotEmpty())
        <section class="kartu overflow-hidden">
            <div class="kartu-kop">Email tidak sampai</div>
            <p class="border-b border-line px-5 py-2.5 text-sm text-muted">
                Tanpa WhatsApp otomatis, pesanan ini perlu ditindaklanjuti manual.
            </p>
            <ul class="divide-y divide-line text-sm">
                @foreach ($emailBermasalah as $log)
                    <li class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 px-5 py-2.5">
                        @if ($log->order)
                            <a href="{{ route('panel.pesanan.show', $log->order) }}"
                               class="tautan tabular font-medium">{{ $log->order->kode }}</a>
                        @else
                            <span class="tabular text-muted">tanpa pesanan</span>
                        @endif
                        <span class="text-muted">{{ $log->ke }}, {{ $log->status->label() }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($stokHabis->isNotEmpty() || $stokMenipis->isNotEmpty())
        <section class="kartu overflow-hidden">
            <div class="kartu-kop flex items-center gap-2">
                Stok perlu diisi
                <a href="{{ route('panel.stok.index') }}" class="tautan ml-auto text-sm font-normal">
                    Buka halaman stok
                </a>
            </div>
            <ul class="divide-y divide-line text-sm">
                @foreach ($stokHabis->take(6) as $v)
                    <li class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 px-5 py-2.5">
                        <span>{{ $v->product->nama }} <span class="text-muted">{{ $v->label }}</span></span>
                        <span class="chip chip-merah">Habis</span>
                    </li>
                @endforeach
                @foreach ($stokMenipis->take(6) as $v)
                    <li class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 px-5 py-2.5">
                        <span>{{ $v->product->nama }} <span class="text-muted">{{ $v->label }}</span></span>
                        <span class="tabular text-muted">sisa {{ $v->stok_tersedia }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if (empty($masalahSistem) && $tanpaTier->isEmpty() && $stokHabis->isEmpty() && $stokMenipis->isEmpty()
         && $perluVerifikasi->isEmpty() && $lamaBelumDiambil->isEmpty() && $emailBermasalah->isEmpty())
        <section class="kartu p-8 text-center">
            <p class="text-lg font-bold">Tidak ada yang perlu ditangani</p>
            <p class="kolom-baca mx-auto mt-2 text-sm text-muted">
                Harga lengkap, stok aman, dan semua email sampai. Waktu yang baik untuk
                menambah produk atau merapikan katalog.
            </p>
            <a href="{{ route('panel.produk.create') }}" class="tombol tombol-navy mt-5">Tambah produk</a>
        </section>
    @endif
</div>

<p class="tabular mt-8 text-sm text-muted">
    Katalog: {{ $produkAktif }} produk tayang, {{ $produkNonaktif }} disembunyikan,
    {{ $tanpaFoto }} tayang tanpa foto.
</p>
@endsection
