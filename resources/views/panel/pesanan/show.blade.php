@extends('layouts.panel')
@section('judul', 'Pesanan '.$pesanan->kode)

@section('isi')
@php
    use App\Enums\OrderItemStatus;
    use App\Enums\OrderStatus;
    use App\Enums\PaymentStatus;
    $izin = auth()->user();
@endphp

<div class="flex flex-wrap items-baseline justify-between gap-3">
    <div>
        <p class="text-sm text-muted tabular">{{ $pesanan->kode }}</p>
        <h1 class="t-wide text-2xl font-extrabold">{{ $pesanan->status->label() }}</h1>
    </div>
    <a href="{{ route('lacak.token', $pesanan->access_token) }}" target="_blank" rel="noopener"
       class="tautan text-sm">Lihat seperti pelanggan</a>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_300px]">
    <div class="space-y-6">

        <section class="border border-line bg-field">
            <h2 class="border-b border-line px-5 py-3 font-semibold">Barang</h2>
            <div class="divide-y divide-line">
                @foreach ($pesanan->items as $item)
                    <div class="flex flex-wrap items-start gap-4 px-5 py-4">
                        <div class="min-w-50 flex-1">
                            <p class="font-medium">{{ $item->nama_produk }}</p>
                            <p class="text-sm text-muted tabular">
                                {{ $item->qty }} pcs &times; {{ rupiah($item->harga_satuan) }}
                            </p>
                            @foreach ($item->options as $opsi)
                                <p class="text-xs text-muted">
                                    {{ $opsi->option_value }}
                                    <span class="tabular">
                                        {{ $opsi->tambahan_harga < 0 ? '−' : '+' }}{{ rupiah(abs($opsi->tambahan_harga)) }}
                                        {{ $opsi->basis->label() }}
                                    </span>
                                </p>
                            @endforeach
                            @if ($item->rosters->isNotEmpty())
                                <p class="mt-1 text-xs text-muted tabular">{{ $item->ringkasanUkuran() }}</p>
                            @endif
                        </div>

                        <div class="text-right">
                            <span class="chip chip-stok">{{ $item->status_item->label() }}</span>
                            <p class="mt-2 tabular font-semibold">{{ rupiah($item->subtotal) }}</p>

                            @php $lanjut = $item->status_item->berikutnya($item->fulfillment_type); @endphp
                            @if ($lanjut && $lanjut !== OrderItemStatus::SELESAI && $izin->punyaIzin('order.kelola'))
                                <form method="POST" action="{{ route('panel.pesanan.item.maju', [$pesanan, $item]) }}" class="mt-2">
                                    @csrf
                                    <button type="submit" class="tombol tombol-garis text-sm">
                                        Tandai {{ $lanjut->label() }}
                                    </button>
                                </form>
                            @elseif ($lanjut === OrderItemStatus::SELESAI)
                                <p class="mt-2 text-xs text-muted">Selesai lewat serah terima kasir.</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="border border-line bg-field">
            <h2 class="border-b border-line px-5 py-3 font-semibold">Pembayaran</h2>
            <div class="divide-y divide-line text-sm">
                @foreach ($pesanan->payments as $bayar)
                    <div class="flex flex-wrap items-center gap-3 px-5 py-3">
                        <div class="min-w-40 flex-1">
                            <p class="tabular font-medium">{{ rupiah($bayar->jumlah) }}</p>
                            <p class="text-xs text-muted">
                                {{ $bayar->jenis->label() }} &middot; {{ $bayar->metode->label() }}
                                @if ($bayar->pencatat) &middot; dicatat {{ $bayar->pencatat->name }} @endif
                                @if ($bayar->verifikator) &middot; diverifikasi {{ $bayar->verifikator->name }} @endif
                            </p>
                        </div>

                        <span class="chip {{ $bayar->status === PaymentStatus::LUNAS ? 'chip-stok' : 'chip-kustom' }}">
                            {{ $bayar->status->label() }}
                        </span>

                        @if ($bayar->bukti_path)
                            <a href="{{ asset('storage/'.$bayar->bukti_path) }}" target="_blank" rel="noopener"
                               class="tautan text-sm">Bukti</a>
                        @endif

                        @if ($bayar->status === PaymentStatus::MENUNGGU && $izin->punyaIzin('pembayaran.verifikasi'))
                            <form method="POST" action="{{ route('panel.pesanan.pembayaran.verifikasi', [$pesanan, $bayar]) }}">
                                @csrf
                                <button type="submit" class="tombol tombol-lapangan text-sm">Verifikasi</button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>

            @if ($pesanan->sisa_tagihan > 0 && $izin->punyaIzin('pembayaran.catat') && ! $pesanan->status->final())
                <form method="POST" action="{{ route('panel.pesanan.pembayaran.catat', $pesanan) }}"
                      class="flex flex-wrap items-end gap-3 border-t border-line bg-paper p-4">
                    @csrf
                    <div class="w-36"><label class="label-form">Nominal</label>
                        <input type="number" name="jumlah" value="{{ $pesanan->sisa_tagihan }}"
                               class="kotak-input tabular" min="1" required></div>
                    <div class="w-40"><label class="label-form">Metode</label>
                        <select name="metode" class="kotak-input">
                            <option value="TUNAI">Tunai</option>
                            <option value="TRANSFER">Transfer bank</option>
                            <option value="QRIS_EDC">QRIS / EDC</option>
                        </select></div>
                    <div class="w-36"><label class="label-form">Jenis</label>
                        <select name="jenis" class="kotak-input">
                            <option value="PELUNASAN">Pelunasan</option>
                            <option value="DP">Uang muka</option>
                            <option value="FULL">Lunas</option>
                        </select></div>
                    <button type="submit" class="tombol tombol-garis">Catat pembayaran</button>
                </form>
            @endif
        </section>

        <section class="border border-line bg-field">
            <h2 class="border-b border-line px-5 py-3 font-semibold">Penyesuaian biaya</h2>

            @if ($pesanan->adjustments->isEmpty())
                <p class="px-5 py-4 text-sm text-muted">
                    Belum ada penyesuaian. Pakai ini untuk perubahan setelah pesanan dibuat —
                    tambah pemain, tambah patch, atau potongan harga. Pelanggan melihatnya di
                    halaman pesanannya sebelum datang ke toko.
                </p>
            @else
                <ul class="divide-y divide-line text-sm">
                    @foreach ($pesanan->adjustments as $p)
                        <li class="flex flex-wrap items-center gap-3 px-5 py-3">
                            <span class="min-w-40 flex-1">
                                {{ $p->deskripsi }}
                                <span class="block text-xs text-muted">
                                    {{ $p->pembuat?->name ?? 'Sistem' }} &middot;
                                    {{ $p->created_at->translatedFormat('j M Y, H:i') }}
                                </span>
                            </span>
                            <span class="tabular font-medium {{ $p->jumlah < 0 ? 'text-court' : '' }}">
                                {{ $p->jumlah < 0 ? '−' : '+' }}{{ rupiah(abs($p->jumlah)) }}
                            </span>
                            @if ($izin->punyaIzin('order.penyesuaian') && ! $pesanan->status->final())
                                <form method="POST" action="{{ route('panel.pesanan.penyesuaian.hapus', [$pesanan, $p]) }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="tautan text-sm text-muted">Hapus</button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($izin->punyaIzin('order.penyesuaian') && ! $pesanan->status->final())
                <form method="POST" action="{{ route('panel.pesanan.penyesuaian.simpan', $pesanan) }}"
                      class="flex flex-wrap items-end gap-3 border-t border-line bg-paper p-4">
                    @csrf
                    <div class="min-w-56 flex-1"><label class="label-form">Deskripsi untuk pelanggan</label>
                        <input type="text" name="deskripsi" class="kotak-input" required
                               placeholder="Tambah 2 pemain"></div>
                    <div class="w-40"><label class="label-form">Nominal</label>
                        <input type="number" name="jumlah" class="kotak-input tabular" required
                               placeholder="240000"></div>
                    <button type="submit" class="tombol tombol-garis">Tambah penyesuaian</button>
                </form>
                <p class="border-t border-line px-4 py-2 text-xs text-muted">
                    Pakai angka negatif untuk potongan harga, misalnya <span class="tabular">-70000</span>.
                </p>
            @endif
        </section>

        <section class="border border-line bg-field">
            <h2 class="border-b border-line px-5 py-3 font-semibold">Riwayat</h2>
            <ul class="divide-y divide-line text-sm">
                @foreach ($pesanan->statusLogs as $log)
                    <li class="flex flex-wrap justify-between gap-3 px-5 py-2.5">
                        <span>
                            {{ $log->orderItem?->nama_produk ?? 'Pesanan' }} &mdash;
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
        </section>
    </div>

    <aside class="space-y-6">
        <div class="border border-line bg-field p-5">
            <h2 class="font-semibold">Pelanggan</h2>
            <p class="mt-2 text-sm">{{ $pesanan->customer->nama }}</p>
            <p class="text-sm text-muted">{{ $pesanan->customer->email }}</p>
            <p class="text-sm text-muted tabular">{{ $pesanan->customer->no_hp }}</p>
            @if ($pesanan->catatan)
                <p class="mt-3 border-l-2 border-line-strong pl-3 text-sm">{{ $pesanan->catatan }}</p>
            @endif
        </div>

        <div class="border border-line bg-field p-5">
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
                <div class="flex justify-between gap-3">
                    <dt class="font-semibold">Sisa</dt>
                    <dd class="tabular font-semibold {{ $pesanan->sisa_tagihan > 0 ? 'text-signal' : 'text-court' }}">
                        {{ $pesanan->sisa_tagihan > 0 ? rupiah($pesanan->sisa_tagihan) : 'Lunas' }}
                    </dd>
                </div>
            </dl>
            @if ($pesanan->kode_ambil)
                <p class="mt-4 border-t border-line pt-3 text-sm text-muted">Kode ambil</p>
                <p class="numeral mt-1 text-2xl">{{ $pesanan->kode_ambil }}</p>
            @endif
        </div>

        {{-- Tanpa WhatsApp API (KEP-004), sistem menyiapkan teksnya dan admin
             yang menekan kirim. Nol biaya, dan realistis untuk pasar ini. --}}
        <div class="border border-line bg-field p-5">
            <h2 class="font-semibold">Kabari lewat WhatsApp</h2>
            <p class="mt-2 text-xs leading-relaxed text-muted">
                Email otomatis sudah dikirim. Pakai ini kalau pelanggan belum merespons.
            </p>
            <textarea readonly rows="5" class="kotak-input mt-3 text-xs leading-relaxed">{{ $pesanWa['teks'] }}</textarea>
            <a href="{{ $pesanWa['url'] }}" target="_blank" rel="noopener"
               class="tombol tombol-garis mt-3 w-full text-sm">Buka WhatsApp</a>
        </div>

        @if ($pesanan->emailLogs->isNotEmpty())
            <div class="border border-line bg-field p-5">
                <h2 class="font-semibold">Email terkirim</h2>
                <ul class="mt-3 space-y-2 text-xs">
                    @foreach ($pesanan->emailLogs as $log)
                        <li class="flex justify-between gap-2">
                            <span>{{ $log->template }}</span>
                            <span class="{{ $log->status->perluTindakLanjut() ? 'text-signal' : 'text-muted' }}">
                                {{ $log->status->label() }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($izin->punyaIzin('order.batal') && ! $pesanan->status->final())
            <form method="POST" action="{{ route('panel.pesanan.batal', $pesanan) }}"
                  class="border border-line bg-field p-5"
                  onsubmit="return confirm('Batalkan pesanan {{ $pesanan->kode }}? Reservasi stoknya dilepas.')">
                @csrf
                <h2 class="font-semibold">Batalkan pesanan</h2>
                <label for="alasan" class="label-form mt-3">Alasan</label>
                <input type="text" id="alasan" name="alasan" class="kotak-input" required
                       placeholder="Dibatalkan pelanggan lewat telepon">
                <button type="submit" class="tombol tombol-garis mt-3 w-full text-sm">Batalkan pesanan</button>
            </form>
        @endif
    </aside>
</div>
@endsection
