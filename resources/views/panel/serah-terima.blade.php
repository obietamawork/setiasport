@extends('layouts.panel')
@section('judul', 'Serah terima')

@section('isi')
<div class="kop-halaman">
    <div>
        <h1 class="kop-judul">Serah terima</h1>
        <p class="kop-catatan">Pelunasan dan pengambilan diselesaikan di layar ini. Masukkan kode ambil yang
    dibawa pelanggan, atau kode pesanannya.</p>
    </div>
</div>

<form method="GET" class="mt-5 flex flex-wrap gap-2">
    <label class="sr-only" for="kode">Kode ambil atau kode pesanan</label>
    <input type="text" id="kode" name="kode" value="{{ $kode }}" autofocus
           placeholder="ABC-1234 atau SS-2609-0042"
           class="kotak-input max-w-xs tabular uppercase">
    <button type="submit" class="tombol tombol-navy">Cari pesanan</button>
</form>

@if ($tidakKetemu)
    <div class="mt-5 border-l-2 border-merah bg-merah-50 px-4 py-3 text-sm">
        Tidak ada pesanan dengan kode <span class="tabular font-semibold">{{ $kode }}</span>.
        Periksa lagi kodenya, atau cari pelanggan lewat halaman Pesanan.
    </div>
@endif

@if ($order)
    <section class="kartu mt-6 max-w-2xl overflow-hidden">
        <div class="border-b border-line px-5 py-4">
            <p class="text-sm text-muted tabular">{{ $order->kode }}</p>
            <h2 class="text-xl font-extrabold">{{ $order->customer->nama }}</h2>
            <p class="text-sm text-muted">{{ $order->status->label() }}</p>
        </div>

        <ul class="divide-y divide-line text-sm">
            @foreach ($order->items as $item)
                <li class="flex justify-between gap-3 px-5 py-2.5">
                    <span>{{ $item->nama_produk }} <span class="text-muted tabular">&times; {{ $item->qty }}</span></span>
                    <span class="text-muted">{{ $item->status_item->labelStaf() }}</span>
                </li>
            @endforeach
        </ul>

        <div class="border-t border-line px-5 py-4">
            <div class="flex items-baseline justify-between gap-3">
                <span class="text-sm text-muted">Sisa yang harus dibayar</span>
                <span class="numeral text-3xl {{ $order->sisa_tagihan > 0 ? 'text-merah' : 'text-ok' }}">
                    {{ $order->sisa_tagihan > 0 ? rupiah($order->sisa_tagihan) : 'Lunas' }}
                </span>
            </div>
        </div>

        @php
            $klaimMenunggu = $order->payments
                ->where('status', \App\Enums\PaymentStatus::MENUNGGU)
                ->whereNotNull('bukti_path')
                ->first();
        @endphp

        @if ($klaimMenunggu)
            {{-- Pelanggan yang sudah transfer lalu datang ke toko adalah jalan
                 tercepat menuju pembayaran tercatat dua kali (KEP-029). --}}
            <div class="border-t border-line bg-kuning-50 px-5 py-4 text-sm leading-relaxed">
                <strong>Pelanggan sudah mengunggah bukti transfer {{ rupiah($klaimMenunggu->jumlah) }}.</strong>
                Cocokkan dengan mutasi lalu verifikasi di halaman pesanan. Jangan dicatat ulang
                di sini, nanti pembayarannya terhitung dua kali.
                <a href="{{ route('panel.pesanan.show', $order) }}" class="tautan">Buka halaman pesanan</a>
            </div>
        @endif

        @if ($order->sisa_tagihan > 0 && ! $klaimMenunggu)
            {{-- Pelunasan dulu, baru serah terima. Order tidak boleh Selesai
                 selama sisa tagihan masih ada (KEP-006). --}}
            <form method="POST" action="{{ route('panel.pesanan.pembayaran.catat', $order) }}"
                  class="flex flex-wrap items-end gap-3 border-t border-line bg-paper p-4">
                @csrf
                <div class="w-40"><label class="label-form">Nominal diterima</label>
                    <input type="number" name="jumlah" value="{{ $order->sisa_tagihan }}"
                           class="kotak-input tabular" min="1" required></div>
                <div class="w-40"><label class="label-form">Metode</label>
                    <select name="metode" class="kotak-input">
                        <option value="TUNAI">Tunai</option>
                        <option value="QRIS">QRIS</option>
                        <option value="TRANSFER">Transfer bank</option>
                    </select></div>
                <input type="hidden" name="jenis" value="PELUNASAN">
                <button type="submit" class="tombol tombol-utama">Catat pelunasan</button>
            </form>
        @elseif ($order->dikirim())
            {{-- Pesanan yang dikirim tidak lewat meja kasir (KEP-026). Resinya
                 dicatat di halaman pesanan, dan menyediakan tombol "serahkan"
                 di sini justru membuka jalan barang keluar tanpa resi. --}}
            <div class="border-t border-line bg-kuning-50 px-5 py-4 text-sm leading-relaxed">
                Pesanan ini <strong>dikirim</strong>, tidak diambil di toko.
                Catat jasa kirim dan nomor resinya di halaman pesanan.
                <a href="{{ route('panel.pesanan.show', $order) }}" class="tautan">Buka halaman pesanan</a>
            </div>
        @elseif ($order->bolehDiserahkan())
            <form method="POST" action="{{ route('panel.pesanan.serah-terima', $order) }}"
                  class="flex flex-wrap items-end gap-3 border-t border-line bg-paper p-4">
                @csrf
                <div class="min-w-56 flex-1">
                    <label for="diambil_oleh" class="label-form">Nama pengambil</label>
                    <input type="text" id="diambil_oleh" name="diambil_oleh" class="kotak-input" required
                           value="{{ $order->customer->nama }}">
                    <p class="mt-1 text-xs text-muted">Ubah kalau diwakilkan orang lain.</p>
                </div>
                <button type="submit" class="tombol tombol-navy">Serahkan barang</button>
            </form>
        @else
            <p class="border-t border-line px-5 py-4 text-sm text-muted">
                Pesanan ini belum siap diserahkan. Statusnya sekarang {{ $order->status->label() }}.
            </p>
        @endif

        <div class="border-t border-line px-5 py-3">
            <a href="{{ route('panel.pesanan.show', $order) }}" class="tautan text-sm">Buka detail pesanan</a>
        </div>
    </section>
@endif

@if ($siapHariIni->isNotEmpty())
    <section class="mt-10 max-w-2xl">
        <h2 class="text-lg font-extrabold">Menunggu diambil</h2>
        <ul class="kartu mt-4 divide-y divide-line overflow-hidden text-sm">
            @foreach ($siapHariIni as $o)
                <li class="flex flex-wrap items-center gap-3 px-4 py-2.5">
                    <a href="{{ route('panel.serah-terima', ['kode' => $o->kode_ambil]) }}"
                       class="tautan tabular font-medium">{{ $o->kode_ambil }}</a>
                    <span class="min-w-32 flex-1">
                        {{ $o->customer->nama }}
                        @if ($o->dikirim())
                            <span class="chip chip-kuning">Dikirim</span>
                        @endif
                    </span>
                    <span class="text-muted tabular">
                        siap {{ $o->siap_pada?->diffForHumans(short: true) }}
                    </span>
                </li>
            @endforeach
        </ul>
    </section>
@endif
@endsection
