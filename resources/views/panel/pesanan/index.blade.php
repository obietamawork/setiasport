@extends('layouts.panel')
@section('judul', 'Pesanan')

@section('isi')
@php use App\Enums\OrderStatus; @endphp

<h1 class="t-wide text-2xl font-extrabold">Pesanan</h1>

<div class="mt-5 flex flex-wrap gap-1 text-sm">
    @php
        $saringan = [
            '' => 'Semua',
            'aktif' => 'Masih berjalan',
            OrderStatus::MENUNGGU_PEMBAYARAN->value => 'Menunggu pembayaran',
            OrderStatus::DIPROSES->value => 'Diproses',
            OrderStatus::SIAP_DIAMBIL->value => 'Siap diambil',
        ];
    @endphp
    @foreach ($saringan as $nilai => $label)
        <a href="{{ route('panel.pesanan.index', array_filter(['status' => $nilai, 'q' => request('q')])) }}"
           class="rounded-sm px-3 py-1.5 font-medium {{ $status === $nilai ? 'bg-court text-white' : 'bg-field hover:bg-court-soft' }}">
            {{ $label }}
            @if (isset($jumlahStatus[$nilai]) && $jumlahStatus[$nilai] > 0)
                <span class="tabular {{ $status === $nilai ? 'text-white/70' : 'text-muted' }}">
                    {{ $jumlahStatus[$nilai] }}
                </span>
            @endif
        </a>
    @endforeach
</div>

<form method="GET" class="mt-4 flex gap-2">
    <input type="hidden" name="status" value="{{ $status }}">
    <input type="search" name="q" value="{{ request('q') }}"
           placeholder="Kode pesanan, nama, email, atau nomor HP" class="kotak-input max-w-sm">
    <button type="submit" class="tombol tombol-garis">Cari</button>
</form>

@if ($pesanan->isEmpty())
    <div class="mt-6 border border-line bg-field p-8 text-center">
        <p class="t-wide text-lg font-bold">Tidak ada pesanan di sini</p>
        <p class="mt-2 text-sm text-muted">
            {{ request('q') ? 'Coba kata kunci lain, atau hapus filter status.' : 'Pesanan yang masuk lewat web akan muncul di daftar ini.' }}
        </p>
    </div>
@else
    <div class="mt-5 overflow-x-auto border border-line bg-field">
        <table class="w-full min-w-180 text-sm">
            <thead class="border-b border-line bg-paper text-left">
                <tr>
                    <th class="px-4 py-2.5 font-semibold">Kode</th>
                    <th class="px-4 py-2.5 font-semibold">Pelanggan</th>
                    <th class="px-4 py-2.5 font-semibold">Status</th>
                    <th class="px-4 py-2.5 text-right font-semibold">Total</th>
                    <th class="px-4 py-2.5 text-right font-semibold">Sisa</th>
                    <th class="px-4 py-2.5 font-semibold">Masuk</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @foreach ($pesanan as $o)
                    <tr>
                        <td class="px-4 py-3">
                            <a href="{{ route('panel.pesanan.show', $o) }}" class="tautan font-medium tabular">
                                {{ $o->kode }}
                            </a>
                            @if ($o->kode_ambil)
                                <div class="text-xs text-muted tabular">Ambil: {{ $o->kode_ambil }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            {{ $o->customer->nama }}
                            <div class="text-xs text-muted">{{ $o->customer->no_hp }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="chip {{ $o->status === OrderStatus::SIAP_DIAMBIL ? 'chip-kustom' : 'chip-stok' }}">
                                {{ $o->status->label() }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right tabular">{{ rupiah($o->total) }}</td>
                        <td class="px-4 py-3 text-right tabular {{ $o->sisa_tagihan > 0 ? 'text-signal' : 'text-muted' }}">
                            {{ $o->sisa_tagihan > 0 ? rupiah($o->sisa_tagihan) : 'Lunas' }}
                        </td>
                        <td class="px-4 py-3 text-muted tabular">{{ $o->created_at->translatedFormat('j M, H:i') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $pesanan->links() }}</div>
@endif
@endsection
