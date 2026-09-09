@extends('layouts.panel')
@section('judul', 'Pesanan')

@section('isi')
@php use App\Enums\OrderStatus; @endphp

<div class="kop-halaman">
    <div>
        <h1 class="kop-judul">Pesanan</h1>
        <p class="kop-catatan">Semua pesanan yang masuk lewat web, terbaru di atas.</p>
    </div>
</div>

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
           class="rounded-sm px-3 py-1.5 font-medium {{ $status === $nilai ? 'bg-navy text-white' : 'bg-field hover:bg-navy-50' }}">
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
    <div class="kartu mt-6 p-8 text-center">
        <p class="text-lg font-bold">Tidak ada pesanan di sini</p>
        <p class="mt-2 text-sm text-muted">
            {{ request('q') ? 'Coba kata kunci lain, atau hapus filter status.' : 'Pesanan yang masuk lewat web akan muncul di daftar ini.' }}
        </p>
    </div>
@else
    {{-- Kartu di layar sempit, tabel di layar lebar. Admin memeriksa pesanan
         dari HP, dan tabel enam kolom yang digeser membuat itu menyiksa. --}}
    <div class="mt-5 space-y-3 md:hidden">
        @foreach ($pesanan as $o)
            <a href="{{ route('panel.pesanan.show', $o) }}" class="kartu kartu-tautan block p-4">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <span class="tabular text-sm font-semibold">{{ $o->kode }}</span>
                    <span class="chip {{ $o->status === OrderStatus::SIAP_DIAMBIL ? 'chip-merah' : 'chip-navy' }}">
                        {{ $o->status->label() }}
                    </span>
                </div>

                <p class="mt-2 font-medium">{{ $o->customer->nama }}</p>
                <p class="tabular text-sm text-muted">{{ $o->customer->no_hp }}</p>

                <div class="mt-3 flex flex-wrap items-baseline justify-between gap-2 border-t border-line pt-3">
                    <span class="tabular text-sm text-muted">
                        {{ $o->created_at->translatedFormat('j M, H:i') }}
                        @if ($o->kode_ambil)
                            &middot; ambil {{ $o->kode_ambil }}
                        @endif
                    </span>
                    <span class="text-right">
                        <span class="tabular font-semibold">{{ rupiah($o->total) }}</span>
                        @if ($o->sisa_tagihan > 0)
                            <span class="tabular block text-sm text-merah">
                                sisa {{ rupiah($o->sisa_tagihan) }}
                            </span>
                        @endif
                    </span>
                </div>
            </a>
        @endforeach
    </div>

    <div class="mt-5 hidden overflow-x-auto rounded-[10px] border border-line bg-field md:block">
        <table class="tabel min-w-180">
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Pelanggan</th>
                    <th>Status</th>
                    <th class="text-right">Total</th>
                    <th class="text-right">Sisa</th>
                    <th>Masuk</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($pesanan as $o)
                    <tr>
                        <td>
                            <a href="{{ route('panel.pesanan.show', $o) }}" class="tautan font-medium tabular">
                                {{ $o->kode }}
                            </a>
                            @if ($o->kode_ambil)
                                <div class="text-xs text-muted tabular">Ambil: {{ $o->kode_ambil }}</div>
                            @endif
                        </td>
                        <td>
                            {{ $o->customer->nama }}
                            <div class="text-xs text-muted">{{ $o->customer->no_hp }}</div>
                        </td>
                        <td>
                            <span class="chip {{ $o->status === OrderStatus::SIAP_DIAMBIL ? 'chip-merah' : 'chip-navy' }}">
                                {{ $o->status->label() }}
                            </span>
                        </td>
                        <td class="text-right tabular">{{ rupiah($o->total) }}</td>
                        <td class="text-right tabular {{ $o->sisa_tagihan > 0 ? 'text-merah' : 'text-muted' }}">
                            {{ $o->sisa_tagihan > 0 ? rupiah($o->sisa_tagihan) : 'Lunas' }}
                        </td>
                        <td class="text-muted tabular">{{ $o->created_at->translatedFormat('j M, H:i') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $pesanan->links() }}</div>
@endif
@endsection
