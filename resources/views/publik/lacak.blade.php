@extends('layouts.publik')
@section('judul', 'Pesanan '.$order->kode)

@section('isi')
@php
    use App\Enums\OrderStatus;
    use App\Enums\OrderItemStatus;
@endphp

<div class="mx-auto max-w-3xl px-5 py-10">

    @if (session('sukses'))
        <p class="mb-6 border-l-2 border-court bg-court-soft px-4 py-3 text-sm">{{ session('sukses') }}</p>
    @endif
    @if ($errors->any())
        <p class="mb-6 border-l-2 border-signal bg-signal-soft px-4 py-3 text-sm">{{ $errors->first() }}</p>
    @endif

    <p class="text-sm text-muted tabular">{{ $order->kode }}</p>
    <h1 class="t-wide mt-1 text-3xl font-extrabold">{{ $order->status->label() }}</h1>
    <p class="mt-2 text-sm text-muted">
        Atas nama {{ $order->customer->nama }} &middot; {{ $order->customer->email_samar }}
    </p>

    {{-- Menunggu pembayaran: instruksi harus jadi hal pertama yang terlihat. --}}
    @if ($menungguBayar)
        <section class="mt-8 border border-line bg-field">
            <h2 class="border-b border-line px-5 py-3 font-semibold">Yang perlu dibayar sekarang</h2>
            <div class="space-y-4 p-5">
                <p class="numeral text-4xl text-signal">{{ rupiah($menungguBayar->jumlah) }}</p>

                @if ($order->kadaluarsa_pada)
                    <p class="text-sm">
                        Batas pembayaran
                        <strong>{{ $order->kadaluarsa_pada->translatedFormat('l, j F Y H:i') }}</strong>.
                        Lewat itu pesanan otomatis dibatalkan dan barangnya kembali dijual.
                    </p>
                @endif

                <div class="border-l-2 border-court bg-court-soft p-4 text-sm leading-relaxed">
                    Transfer ke rekening toko, lalu unggah bukti di bawah. Kami memeriksanya
                    pada jam kerja dan mengabari lewat email begitu masuk.
                </div>

                @if ($menungguBayar->bukti_path)
                    <p class="text-sm">
                        Bukti transfer sudah kami terima dan sedang diperiksa.
                        <a href="{{ asset('storage/'.$menungguBayar->bukti_path) }}" target="_blank"
                           rel="noopener" class="tautan">Lihat berkas yang Anda kirim</a>
                    </p>
                @endif

                <form method="POST" action="{{ route('lacak.bukti', $order->access_token) }}"
                      enctype="multipart/form-data" class="flex flex-wrap items-end gap-3 border-t border-line pt-4">
                    @csrf
                    <div class="min-w-56 flex-1">
                        <label for="bukti" class="label-form">
                            {{ $menungguBayar->bukti_path ? 'Ganti bukti transfer' : 'Unggah bukti transfer' }}
                        </label>
                        <input type="file" id="bukti" name="bukti" accept=".jpg,.jpeg,.png,.pdf"
                               class="kotak-input" required>
                        <p class="mt-1 text-xs text-muted">
                            Foto atau PDF, maksimal {{ setting('upload_maks_mb', 10) }} MB.
                        </p>
                    </div>
                    <button type="submit" class="tombol tombol-utama">Kirim bukti</button>
                </form>
            </div>
        </section>
    @endif

    {{-- Siap diambil: kode ambil dan sisa tagihan adalah dua hal yang dicari
         pelanggan saat berdiri di depan toko. --}}
    @if ($order->status === OrderStatus::SIAP_DIAMBIL)
        <section class="mt-8 border border-line bg-field">
            <h2 class="border-b border-line px-5 py-3 font-semibold">Ambil di toko</h2>
            <div class="grid gap-5 p-5 sm:grid-cols-2">
                <div>
                    <p class="text-sm text-muted">Kode ambil</p>
                    <p class="numeral mt-2 text-4xl">{{ $order->kode_ambil }}</p>
                    <p class="mt-3 text-sm text-muted">
                        Tunjukkan kode ini di kasir. Boleh diwakilkan.
                    </p>
                </div>
                <div>
                    @if ($order->sisa_tagihan > 0)
                        <p class="text-sm text-muted">Dibayar saat mengambil</p>
                        <p class="numeral mt-2 text-4xl text-signal">{{ rupiah($order->sisa_tagihan) }}</p>
                        <p class="mt-3 text-sm text-muted">Tunai maupun non-tunai.</p>
                    @else
                        <p class="text-sm text-muted">Pembayaran</p>
                        <p class="numeral mt-2 text-3xl text-court">Lunas</p>
                        <p class="mt-3 text-sm text-muted">Tinggal diambil.</p>
                    @endif
                </div>
            </div>
            <div class="border-t border-line px-5 py-4 text-sm leading-relaxed text-muted">
                {{ setting('toko_alamat') }}<br>
                Jam buka: {{ setting('toko_jam_buka') }}
            </div>
        </section>
    @endif

    {{-- Timeline per item, bukan satu timeline tunggal: satu order boleh memuat
         barang stok dan barang kustom yang jalannya berbeda (KEP-001). --}}
    <section class="mt-8">
        <h2 class="t-wide text-xl font-extrabold">Progres</h2>

        <div class="mt-4 space-y-4">
            @foreach ($order->items as $item)
                @php
                    $alur = OrderItemStatus::alur($item->fulfillment_type);
                    $posisi = array_search($item->status_item, $alur, true);
                    $batal = $item->status_item === OrderItemStatus::DIBATALKAN;
                @endphp

                <div class="border border-line bg-field">
                    <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-line px-5 py-3">
                        <span class="font-semibold">{{ $item->nama_produk }}</span>
                        <span class="text-sm text-muted tabular">{{ $item->qty }} pcs</span>
                    </div>

                    @if ($batal)
                        <p class="px-5 py-4 text-sm text-muted">Item ini dibatalkan.</p>
                    @else
                        <ol class="px-5 py-4">
                            @foreach ($alur as $i => $tahap)
                                @php
                                    $lewat = $posisi !== false && $i < $posisi;
                                    $kini = $posisi !== false && $i === $posisi;
                                    $waktu = $order->statusLogs
                                        ->where('order_item_id', $item->id)
                                        ->firstWhere('ke', $tahap->value)?->created_at;
                                @endphp
                                <li class="flex gap-3 {{ $loop->last ? '' : 'pb-4' }}">
                                    <div class="flex flex-col items-center">
                                        <span class="mt-1 size-2.5 shrink-0 rounded-full
                                            {{ $kini ? 'bg-signal ring-4 ring-signal-soft' : ($lewat ? 'bg-court' : 'bg-line-strong') }}"></span>
                                        @unless ($loop->last)
                                            <span class="w-px flex-1 {{ $lewat ? 'bg-court' : 'bg-line' }}"></span>
                                        @endunless
                                    </div>
                                    <div class="-mt-0.5 pb-1">
                                        <p class="text-sm {{ $kini ? 'font-bold' : ($lewat ? '' : 'text-muted') }}">
                                            {{ $tahap->label() }}
                                        </p>
                                        @if ($waktu)
                                            <p class="text-xs text-muted tabular">
                                                {{ $waktu->translatedFormat('j M Y, H:i') }}
                                            </p>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </div>
            @endforeach
        </div>
    </section>

    <section class="mt-8 border border-line bg-field">
        <h2 class="border-b border-line px-5 py-3 font-semibold">Rincian biaya</h2>
        <ul class="divide-y divide-line text-sm">
            @foreach ($order->items as $item)
                <li class="px-5 py-3">
                    <div class="flex justify-between gap-3">
                        <span>{{ $item->nama_produk }} <span class="text-muted tabular">&times; {{ $item->qty }}</span></span>
                        <span class="tabular whitespace-nowrap">{{ rupiah($item->subtotal) }}</span>
                    </div>
                    @foreach ($item->options as $opsi)
                        <div class="mt-1 flex justify-between gap-3 text-xs text-muted">
                            <span>{{ $opsi->option_value }}</span>
                            <span class="tabular">
                                {{ $opsi->tambahan_harga < 0 ? '−' : '+' }}{{ rupiah(abs($opsi->tambahan_harga)) }}
                                {{ $opsi->basis->label() }}
                            </span>
                        </div>
                    @endforeach
                </li>
            @endforeach

            {{-- Perubahan pesanan tampil di sini SEBELUM pelanggan datang ke toko.
                 Itulah inti KEP-007: tidak ada kejutan angka di kasir. --}}
            @foreach ($order->adjustments as $p)
                <li class="flex justify-between gap-3 px-5 py-3">
                    <span>{{ $p->deskripsi }}</span>
                    <span class="tabular whitespace-nowrap">
                        {{ $p->jumlah < 0 ? '−' : '' }}{{ rupiah(abs($p->jumlah)) }}
                    </span>
                </li>
            @endforeach
        </ul>

        <dl class="space-y-1.5 border-t border-line px-5 py-4 text-sm">
            <div class="flex justify-between gap-3">
                <dt class="font-semibold">Total</dt>
                <dd class="tabular font-semibold">{{ rupiah($order->total) }}</dd>
            </div>
            @if ($order->sudah_dibayar > 0)
                <div class="flex justify-between gap-3 text-muted">
                    <dt>Sudah dibayar</dt>
                    <dd class="tabular">{{ rupiah($order->sudah_dibayar) }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="font-semibold">Sisa tagihan</dt>
                    <dd class="tabular font-semibold {{ $order->sisa_tagihan > 0 ? 'text-signal' : 'text-court' }}">
                        {{ $order->sisa_tagihan > 0 ? rupiah($order->sisa_tagihan) : 'Lunas' }}
                    </dd>
                </div>
            @endif
        </dl>
    </section>

    @if ($order->statusLogs->isNotEmpty())
        <section class="mt-8">
            <h2 class="t-wide text-xl font-extrabold">Riwayat</h2>
            <ul class="mt-4 divide-y divide-line border border-line bg-field text-sm">
                @foreach ($order->statusLogs->take(20) as $log)
                    <li class="flex flex-wrap justify-between gap-3 px-5 py-2.5">
                        <span>
                            {{ $log->orderItem?->nama_produk ?? 'Pesanan' }} &mdash;
                            {{ \App\Enums\OrderItemStatus::tryFrom($log->ke)?->label()
                                ?? OrderStatus::tryFrom($log->ke)?->label() ?? $log->ke }}
                        </span>
                        <span class="text-muted tabular">{{ $log->created_at?->translatedFormat('j M Y, H:i') }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <p class="mt-8 text-sm leading-relaxed text-muted">
        Simpan tautan halaman ini. Boleh dibagikan ke anggota tim yang ingin ikut
        memantau — tidak ada data pribadi yang tampil di sini.
    </p>
</div>
@endsection
