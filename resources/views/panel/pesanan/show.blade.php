@extends('layouts.panel')
@section('judul', 'Pesanan '.$pesanan->kode.($pesanan->customer ? ' · '.$pesanan->customer->nama : ''))

@section('isi')
@php
    use App\Enums\OrderItemStatus;
    use App\Services\AlurPesanan;
    use App\Support\ProgresStaf;

    $izin = auth('web')->user();
    $bolehKelola = $izin->punyaIzin('order.kelola') && ! $pesanan->status->final();
    $bolehCatatBayar = $pesanan->sisa_tagihan > 0 && $izin->punyaIzin('pembayaran.catat') && ! $pesanan->status->final();
    $adaKustom = $pesanan->items->contains(fn ($i) => $i->kustom() && $i->status_item !== OrderItemStatus::DIBATALKAN);

    // Data pop-up Perbarui progres (KEP-124): satu entri per barang yang
    // masih berjalan, dengan pilihan tahap yang sah untuknya.
    $alurPesanan = app(AlurPesanan::class);
    $barangProgres = $pesanan->items
        ->reject(fn ($i) => $i->status_item === OrderItemStatus::DIBATALKAN)
        ->map(fn ($i) => [
            'id' => $i->id,
            'nama' => $i->nama_produk,
            'dari' => $i->status_item->value,
            'maju' => route('panel.pesanan.item.maju', [$pesanan, $i]),
            'mundur' => route('panel.pesanan.item.mundur', [$pesanan, $i]),
            'pilihan' => ProgresStaf::pilihan($i, $alurPesanan),
        ])
        ->values();
@endphp

{{-- Tata letak dari gambar karyawan (KEP-124). Judulnya "Detail pesanan";
     kodenya tampil besar di kartu kepala, tempat ia dicari dan disalin. --}}
<x-kop judul="Detail pesanan">
    <x-slot:bawahJudul>
        <nav aria-label="Remah roti" class="flex items-center gap-1.5 text-sm text-muted">
            <a href="{{ route('panel.pesanan.index') }}" class="area-sentuh hover:text-navy">Pesanan</a>
            <x-ikon nama="lanjut" ukuran="size-3.5" />
            <span class="tabular text-ink">{{ $pesanan->kode }}</span>
        </nav>
    </x-slot:bawahJudul>

    <a href="{{ route('panel.pesanan.worksheet', $pesanan) }}" target="_blank" rel="noopener"
       class="tombol tombol-garis tombol-sm">
        <x-ikon nama="cetak" ukuran="size-4" />
        Lembar kerja
    </a>
    <a href="{{ route('lacak.token', $pesanan->access_token) }}" target="_blank" rel="noopener"
       class="tombol tombol-halus tombol-sm">
        <x-ikon nama="buka-luar" ukuran="size-4" />
        <span class="sr-only sm:not-sr-only">Lihat seperti pelanggan</span>
    </a>
</x-kop>

<div class="space-y-6" x-data>
    @include('panel.pesanan.detail.kepala')

    {{-- Satu kalimat yang menjawab "sekarang saya harus apa". --}}
    <div class="langkah-berikut
                {{ $langkah['nada'] === 'mendesak' ? 'langkah-berikut-mendesak' : '' }}
                {{ $langkah['nada'] === 'selesai' ? 'langkah-berikut-selesai' : '' }}">
        <div class="min-w-0 grow basis-64">
            <p class="font-bold">{{ $langkah['judul'] }}</p>
            <p class="kolom-baca mt-0.5 text-sm leading-relaxed text-muted">{{ $langkah['isi'] }}</p>
        </div>
        @if ($langkah['tuju'])
            <a href="{{ $langkah['tuju'] }}"
               class="tombol tombol-sm {{ $langkah['nada'] === 'mendesak' ? 'tombol-utama' : 'tombol-garis' }}">
                {{ $langkah['label'] }}
                <x-ikon nama="lanjut" ukuran="size-4" />
            </a>
        @endif
    </div>

    {{-- Pengajuan pembatalan (KEP-041) paling atas: selama belum diputuskan,
         produksi tetap berjalan dan kain tetap terpakai. --}}
    @if ($pembatalan)
        @include('panel.pesanan.detail.pembatalan')
    @endif

    <div class="grid gap-6 lg:grid-cols-2 lg:items-start">
        @include('panel.pesanan.detail.pelanggan')
        @include('panel.pesanan.detail.informasi')
    </div>

    <div id="barang" class="scroll-mt-32 space-y-6">
        @foreach ($pesanan->items as $item)
            @include('panel.pesanan.detail.barang', ['item' => $item])
        @endforeach
    </div>

    <div @class(['grid gap-6 lg:items-start', 'lg:grid-cols-2' => $adaKustom])>
        @if ($adaKustom)
            @include('panel.pesanan.detail.desain')
        @endif
        @include('panel.pesanan.detail.pembayaran')
    </div>

    <div class="grid gap-6 lg:grid-cols-2 lg:items-start">
        @include('panel.pesanan.detail.penyesuaian')
        @include('panel.pesanan.detail.ringkasan')
    </div>

    @include('panel.pesanan.detail.pengambilan')

    <div class="grid gap-6 lg:grid-cols-2 lg:items-start">
        @include('panel.pesanan.detail.riwayat')
        @include('panel.pesanan.detail.catatan')
    </div>

    {{-- Bilah tindakan bawah seperti gambar karyawan. --}}
    <div class="kartu-bayar flex flex-wrap items-center justify-between gap-3 py-3 sm:py-3">
        <a href="{{ route('panel.pesanan.index') }}" class="tombol tombol-garis">
            <x-ikon nama="kembali" ukuran="size-4" /> Kembali
        </a>
        <div class="flex flex-wrap gap-2">
            @if ($bolehCatatBayar)
                <button type="button" class="tombol tombol-navy" @click="$dispatch('buka-catat-bayar')">
                    <x-ikon nama="uang" ukuran="size-4" /> Catat pembayaran
                </button>
            @endif
            @if ($langkah['tuju'] && $langkah['label'] !== 'Catat pembayaran')
                <a href="{{ $langkah['tuju'] }}" class="tombol tombol-navy">
                    {{ $langkah['label'] }} <x-ikon nama="lanjut" ukuran="size-4" />
                </a>
            @endif
            <a href="{{ route('panel.pesanan.invoice', $pesanan) }}" target="_blank" rel="noopener" class="tombol tombol-garis">
                <x-ikon nama="cetak" ukuran="size-4" /> Cetak invoice
            </a>
        </div>
    </div>
</div>

@include('panel.pesanan.detail.zona-bahaya')

@if ($bolehCatatBayar)
    @include('panel.pesanan.detail.dialog-bayar')
@endif
@if ($bolehKelola && $barangProgres->isNotEmpty())
    @include('panel.pesanan.detail.dialog-progres')
@endif
@endsection
