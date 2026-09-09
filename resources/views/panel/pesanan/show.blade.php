@extends('layouts.panel')
@section('judul', 'Pesanan '.$pesanan->kode)

@section('isi')
@php
    use App\Enums\OrderItemStatus;
    use App\Enums\OrderStatus;
    use App\Enums\PaymentStatus;
    $izin = auth('web')->user();
@endphp

<a href="{{ route('panel.pesanan.index') }}" class="tautan text-sm text-muted">Kembali ke daftar pesanan</a>

<div class="kop-halaman mt-3">
    <div>
        <p class="tabular text-sm text-muted">{{ $pesanan->kode }}</p>
        <h1 class="kop-judul mt-0.5">{{ $pesanan->status->label() }}</h1>
        <p class="kop-catatan">
            Dibuat {{ $pesanan->created_at->translatedFormat('l, j F Y, H:i') }}
            @if ($pesanan->target_siap_pada)
                , target siap {{ $pesanan->target_siap_pada->translatedFormat('j F Y') }}
            @endif
        </p>
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <a href="{{ route('panel.pesanan.worksheet', $pesanan) }}" target="_blank" rel="noopener"
           class="tombol tombol-garis tombol-sm">Worksheet</a>
        <a href="{{ route('lacak.token', $pesanan->access_token) }}" target="_blank" rel="noopener"
           class="tautan text-sm">Lihat seperti pelanggan</a>
    </div>
</div>

{{-- Tumpukan biasa di layar sempit, grid baru mulai lg. Trek grid "auto"
     melar mengikuti max-content isinya dan mendorong halaman ke samping. --}}
<div class="flex flex-col gap-6 lg:grid lg:grid-cols-[minmax(0,1fr)_296px] lg:items-start">
    <div class="min-w-0 space-y-6">

        <section class="kartu overflow-hidden">
            <h2 class="kartu-kop">Barang</h2>
            <div class="divide-y divide-line">
                @foreach ($pesanan->items as $item)
                    @php
                        $alur = OrderItemStatus::alur($item->fulfillment_type);
                        $posisi = array_search($item->status_item, $alur, true);
                        $lanjut = $item->status_item->berikutnya($item->fulfillment_type);
                        $menungguPelanggan = $item->status_item === OrderItemStatus::MENUNGGU_PERSETUJUAN;
                        $batal = $item->status_item === OrderItemStatus::DIBATALKAN;
                    @endphp

                    <div class="p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-semibold">{{ $item->nama_produk }}</p>
                                <p class="tabular mt-0.5 text-sm text-muted">
                                    {{ $item->qty }} pcs &times; {{ rupiah($item->harga_satuan) }}
                                </p>
                            </div>
                            <p class="tabular font-semibold whitespace-nowrap">{{ rupiah($item->subtotal) }}</p>
                        </div>

                        @if ($item->options->isNotEmpty())
                            <div class="mt-3 flex flex-wrap gap-1.5">
                                @foreach ($item->options as $opsi)
                                    <span class="chip {{ $opsi->basis === \App\Enums\PriceBasis::PER_ITEM ? 'chip-kuning' : 'chip-netral' }}">
                                        {{ $opsi->option_value }}
                                        <span class="tabular font-normal">
                                            {{ $opsi->tambahan_harga < 0 ? '−' : '+' }}{{ rupiah_ringkas(abs($opsi->tambahan_harga)) }}
                                        </span>
                                    </span>
                                @endforeach
                            </div>
                        @endif

                        {{-- Rincian ukuran ditonjolkan: inilah satu-satunya angka di
                             kartu ini yang dipakai orang produksi saat memotong kain. --}}
                        @if ($item->rosters->isNotEmpty())
                            <p class="tabular mt-3 rounded-[7px] bg-navy-50 px-3 py-2 text-sm font-semibold">
                                {{ $item->ringkasanUkuran() }}
                                <span class="font-normal text-muted">
                                    ({{ $item->rosters->count() }} dari {{ $item->qty }} nama terisi)
                                </span>
                            </p>
                        @elseif ($item->product->roster_wajib)
                            <p class="mt-3 rounded-[7px] bg-merah-50 px-3 py-2 text-sm">
                                Daftar nama dan nomor punggung belum diisi pelanggan.
                            </p>
                        @endif

                        @unless ($batal)
                            <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-line pt-4">
                                <div class="flex items-center gap-1.5" title="{{ $item->status_item->label() }}">
                                    @foreach ($alur as $i => $tahap)
                                        <span class="tahap-titik
                                            {{ $posisi !== false && $i < $posisi ? 'tahap-titik-lewat' : '' }}
                                            {{ $posisi === $i ? 'tahap-titik-kini' : '' }}"></span>
                                    @endforeach
                                </div>

                                <span class="text-sm font-medium">{{ $item->status_item->label() }}</span>

                                @if ($menungguPelanggan)
                                    <span class="ml-auto text-sm text-muted">
                                        Hanya pelanggan yang bisa menyetujui desain
                                    </span>
                                @elseif ($lanjut && $lanjut !== OrderItemStatus::SELESAI && $izin->punyaIzin('order.kelola'))
                                    <form method="POST" action="{{ route('panel.pesanan.item.maju', [$pesanan, $item]) }}"
                                          class="ml-auto">
                                        @csrf
                                        <button type="submit" class="tombol tombol-garis tombol-sm">
                                            Tandai {{ $lanjut->label() }}
                                        </button>
                                    </form>
                                @elseif ($lanjut === OrderItemStatus::SELESAI)
                                    <span class="ml-auto text-sm text-muted">Selesai lewat serah terima</span>
                                @endif
                            </div>
                        @else
                            <p class="mt-3 text-sm text-muted">Item ini dibatalkan.</p>
                        @endunless
                    </div>
                @endforeach
            </div>
        </section>

        <section class="kartu overflow-hidden">
            <h2 class="kartu-kop">Pembayaran</h2>

            <ul class="divide-y divide-line text-sm">
                @foreach ($pesanan->payments as $bayar)
                    <li class="flex flex-wrap items-center gap-3 px-5 py-3">
                        <div class="min-w-40 flex-1">
                            <p class="tabular font-semibold">{{ rupiah($bayar->jumlah) }}</p>
                            <p class="mt-0.5 text-xs text-muted">
                                @php
                                    $jejak = array_filter([
                                        $bayar->jenis->label(),
                                        $bayar->metode->label(),
                                        $bayar->pencatat ? 'dicatat '.$bayar->pencatat->name : null,
                                        $bayar->verifikator ? 'diverifikasi '.$bayar->verifikator->name : null,
                                    ]);
                                @endphp
                                {{ implode(', ', $jejak) }}
                            </p>
                        </div>

                        <span class="chip {{ $bayar->status === PaymentStatus::LUNAS ? 'chip-ok' : 'chip-kuning' }}">
                            {{ $bayar->status->label() }}
                        </span>

                        @if ($bayar->bukti_path)
                            <a href="{{ asset('storage/'.$bayar->bukti_path) }}" target="_blank" rel="noopener"
                               class="tautan">Bukti</a>
                        @endif

                        @if ($bayar->status === PaymentStatus::MENUNGGU && $izin->punyaIzin('pembayaran.verifikasi'))
                            <form method="POST" action="{{ route('panel.pesanan.pembayaran.verifikasi', [$pesanan, $bayar]) }}">
                                @csrf
                                <button type="submit" class="tombol tombol-navy tombol-sm">Verifikasi</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>

            {{-- Formulir yang jarang dipakai dilipat. Kotak isian yang menganggur
                 membuat layar terlihat penuh padahal tidak ada yang dikerjakan. --}}
            @if ($pesanan->sisa_tagihan > 0 && $izin->punyaIzin('pembayaran.catat') && ! $pesanan->status->final())
                <details class="lipatan border-t border-line">
                    <summary>Catat pembayaran diterima di toko</summary>
                    <form method="POST" action="{{ route('panel.pesanan.pembayaran.catat', $pesanan) }}"
                          class="grid gap-4 bg-navy-50 p-4 sm:grid-cols-3">
                        @csrf
                        <div>
                            <label for="jumlah" class="label-form">Nominal</label>
                            <input type="number" id="jumlah" name="jumlah" value="{{ $pesanan->sisa_tagihan }}"
                                   class="kotak-input tabular" min="1" required>
                        </div>
                        <div>
                            <label for="metode" class="label-form">Metode</label>
                            <select id="metode" name="metode" class="kotak-input">
                                <option value="TUNAI">Tunai</option>
                                <option value="QRIS_EDC">QRIS / EDC</option>
                                <option value="TRANSFER">Transfer bank</option>
                            </select>
                        </div>
                        <div>
                            <label for="jenis" class="label-form">Jenis</label>
                            <select id="jenis" name="jenis" class="kotak-input">
                                <option value="PELUNASAN">Pelunasan</option>
                                <option value="DP">Uang muka</option>
                                <option value="FULL">Lunas</option>
                            </select>
                        </div>
                        <div class="sm:col-span-3">
                            <button type="submit" class="tombol tombol-navy">Catat pembayaran</button>
                        </div>
                    </form>
                </details>
            @endif
        </section>

        <section class="kartu overflow-hidden">
            <h2 class="kartu-kop">Penyesuaian biaya</h2>

            @if ($pesanan->adjustments->isEmpty())
                <p class="px-5 py-4 text-sm text-muted">
                    Belum ada. Pakai ini untuk perubahan setelah pesanan dibuat, misalnya tambah
                    pemain atau potongan harga. Pelanggan melihatnya di halaman pesanannya
                    sebelum datang ke toko.
                </p>
            @else
                <ul class="divide-y divide-line text-sm">
                    @foreach ($pesanan->adjustments as $p)
                        <li class="flex flex-wrap items-center gap-3 px-5 py-3">
                            <span class="min-w-40 flex-1">
                                {{ $p->deskripsi }}
                                <span class="mt-0.5 block text-xs text-muted">
                                    {{ $p->pembuat?->name ?? 'Sistem' }},
                                    {{ $p->created_at->translatedFormat('j M Y, H:i') }}
                                </span>
                            </span>
                            <span class="tabular font-semibold {{ $p->jumlah < 0 ? 'text-ok' : '' }}">
                                {{ $p->jumlah < 0 ? '−' : '+' }}{{ rupiah(abs($p->jumlah)) }}
                            </span>
                            @if ($izin->punyaIzin('order.penyesuaian') && ! $pesanan->status->final())
                                <form method="POST" action="{{ route('panel.pesanan.penyesuaian.hapus', [$pesanan, $p]) }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="tautan text-muted">Hapus</button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($izin->punyaIzin('order.penyesuaian') && ! $pesanan->status->final())
                <details class="lipatan border-t border-line">
                    <summary>Tambah penyesuaian biaya</summary>
                    <form method="POST" action="{{ route('panel.pesanan.penyesuaian.simpan', $pesanan) }}"
                          class="grid gap-4 bg-navy-50 p-4 sm:grid-cols-[minmax(0,1fr)_10rem]">
                        @csrf
                        <div>
                            <label for="deskripsi" class="label-form">Deskripsi untuk pelanggan</label>
                            <input type="text" id="deskripsi" name="deskripsi" class="kotak-input" required
                                   placeholder="Tambah 2 pemain">
                        </div>
                        <div>
                            <label for="nominal" class="label-form">Nominal</label>
                            <input type="number" id="nominal" name="jumlah" class="kotak-input tabular" required
                                   placeholder="240000">
                        </div>
                        <div class="sm:col-span-2">
                            <button type="submit" class="tombol tombol-navy">Tambah penyesuaian</button>
                            <span class="bantuan-form inline-block">
                                Pakai angka negatif untuk potongan harga, misalnya -70000.
                            </span>
                        </div>
                    </form>
                </details>
            @endif
        </section>

        {{-- Riwayat adalah rujukan, bukan pekerjaan. Dilipat supaya tidak
             sebobot Barang dan Pembayaran. --}}
        <details class="kartu lipatan overflow-hidden">
            <summary>Riwayat perubahan ({{ $pesanan->statusLogs->count() }})</summary>
            <ul class="divide-y divide-line border-t border-line text-sm">
                @foreach ($pesanan->statusLogs as $log)
                    <li class="flex flex-wrap justify-between gap-3 px-5 py-2.5">
                        <span>
                            {{ $log->orderItem?->nama_produk ?? 'Pesanan' }}:
                            {{ OrderItemStatus::tryFrom($log->ke)?->label() ?? OrderStatus::tryFrom($log->ke)?->label() ?? $log->ke }}
                            @if ($log->catatan)
                                <span class="block text-xs text-muted">{{ $log->catatan }}</span>
                            @endif
                        </span>
                        <span class="text-right text-muted">
                            <span class="tabular">{{ $log->created_at?->translatedFormat('j M, H:i') }}</span>
                            <span class="block text-xs">{{ $log->pelaku?->name ?? 'Pelanggan' }}</span>
                        </span>
                    </li>
                @endforeach
            </ul>
        </details>
    </div>

    <aside class="space-y-4">
        {{-- Uang didahulukan: itu yang paling sering dilihat kasir. --}}
        <div class="kartu p-5">
            <h2 class="font-semibold">Uang</h2>
            <dl class="mt-3 space-y-1.5 text-sm">
                <div class="flex justify-between gap-3">
                    <dt class="text-muted">Total</dt>
                    <dd class="tabular font-semibold">{{ rupiah($pesanan->total) }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-muted">Sudah dibayar</dt>
                    <dd class="tabular">{{ rupiah($pesanan->sudah_dibayar) }}</dd>
                </div>
                <div class="flex justify-between gap-3 border-t border-line pt-1.5">
                    <dt class="font-semibold">Sisa</dt>
                    <dd class="tabular font-semibold {{ $pesanan->sisa_tagihan > 0 ? 'text-merah' : 'text-ok' }}">
                        {{ $pesanan->sisa_tagihan > 0 ? rupiah($pesanan->sisa_tagihan) : 'Lunas' }}
                    </dd>
                </div>
            </dl>

            @if ($pesanan->kode_ambil)
                <div class="mt-4 border-t border-line pt-3">
                    <p class="text-sm text-muted">Kode ambil</p>
                    <p class="numeral mt-1 text-2xl">{{ $pesanan->kode_ambil }}</p>
                </div>
            @endif
        </div>

        <div class="kartu p-5">
            <h2 class="font-semibold">Pelanggan</h2>
            <p class="mt-2 text-sm font-medium">{{ $pesanan->customer->nama }}</p>
            <p class="text-sm break-all text-muted">{{ $pesanan->customer->email }}</p>
            <p class="tabular text-sm text-muted">{{ $pesanan->customer->no_hp }}</p>
            @if ($pesanan->catatan)
                <p class="mt-3 border-l-2 border-kuning bg-kuning-50 py-2 pr-2 pl-3 text-sm">
                    {{ $pesanan->catatan }}
                </p>
            @endif
        </div>

        {{-- Tanpa WhatsApp API (KEP-004), sistem menyiapkan teksnya dan admin
             yang menekan kirim. --}}
        <div class="kartu p-5" x-data="{ tersalin: false }">
            <h2 class="font-semibold">Kabari lewat WhatsApp</h2>
            <p class="mt-1 text-xs leading-relaxed text-muted">
                Email otomatis sudah dikirim. Pakai ini kalau pelanggan belum merespons.
            </p>

            <p class="mt-3 max-h-24 overflow-y-auto rounded-[7px] bg-navy-50 p-3 text-xs leading-relaxed"
               x-ref="pesan">{{ $pesanWa['teks'] }}</p>

            <div class="mt-3 grid grid-cols-2 gap-2">
                <button type="button" class="tombol tombol-garis tombol-sm"
                        @click="navigator.clipboard.writeText($refs.pesan.textContent.trim()); tersalin = true; setTimeout(() => tersalin = false, 2000)">
                    <span x-text="tersalin ? 'Tersalin' : 'Salin teks'">Salin teks</span>
                </button>
                <a href="{{ $pesanWa['url'] }}" target="_blank" rel="noopener"
                   class="tombol tombol-navy tombol-sm">Buka WhatsApp</a>
            </div>
        </div>

        @if ($pesanan->emailLogs->isNotEmpty())
            <div class="kartu p-5">
                <h2 class="font-semibold">Email terkirim</h2>
                <ul class="mt-3 space-y-1.5 text-xs">
                    @foreach ($pesanan->emailLogs as $log)
                        <li class="flex justify-between gap-2">
                            <span>{{ $log->template }}</span>
                            <span class="{{ $log->status->perluTindakLanjut() ? 'text-merah' : 'text-muted' }}">
                                {{ $log->status->label() }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </aside>
</div>

{{-- Tindakan berisiko dipisah dan diredam. Membatalkan pesanan tidak boleh
     sebobot kartu informasi di sebelahnya. --}}
@if ($izin->punyaIzin('order.batal') && ! $pesanan->status->final())
    <div class="zona-bahaya" x-data="{ buka: false }">
        <button type="button" @click="buka = !buka" class="tautan text-sm text-muted">
            Batalkan pesanan ini
        </button>

        <form method="POST" action="{{ route('panel.pesanan.batal', $pesanan) }}"
              x-show="buka" x-cloak class="notis notis-galat mt-3 max-w-xl"
              onsubmit="return confirm('Batalkan pesanan {{ $pesanan->kode }}? Reservasi stoknya dilepas.')">
            @csrf
            <p class="font-semibold">Pesanan akan dibatalkan dan reservasi stoknya dilepas.</p>
            <label for="alasan" class="label-form mt-3">Alasan, akan masuk riwayat pesanan</label>
            <input type="text" id="alasan" name="alasan" class="kotak-input" required
                   placeholder="Dibatalkan pelanggan lewat telepon">
            <button type="submit" class="tombol tombol-utama tombol-sm mt-3">Ya, batalkan</button>
        </form>
    </div>
@endif
@endsection
