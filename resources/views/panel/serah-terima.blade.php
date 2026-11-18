@extends('layouts.panel')
@section('judul', 'Serah terima')

@section('isi')
<x-kop judul="Serah terima pesanan" ikon="serah-terima" warna="pink"
       catatan="Cari pesanan untuk pengambilan atau penyerahan barang kepada pelanggan. Masukkan kode ambil yang dibawa pelanggan, atau kode pesanannya." />

{{-- Kotak kode dibuat besar dengan sengaja: ini satu-satunya isian di layar
     kasir, diketik sambil berdiri, sering dari layar sentuh, dan kodenya
     dibacakan pelanggan dari HP-nya. Tombol silang mengosongkannya untuk
     pelanggan berikutnya. --}}
<form method="GET" class="flex flex-wrap items-center gap-2" role="search">
    <label class="sr-only" for="kode">Kode ambil atau kode pesanan</label>
    <div class="relative min-w-60 flex-1 sm:max-w-xl">
        <x-ikon nama="cari" class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-navy-400" />
        <input type="text" id="kode" name="kode" value="{{ $kode }}" @if (! $order) autofocus @endif
               placeholder="ABC-1234 atau SS-2609-0042"
               class="kotak-input tabular py-3 pr-12 pl-11 text-lg uppercase">
        @if ($kode !== '')
            <a href="{{ route('panel.serah-terima') }}" aria-label="Hapus pencarian" title="Hapus pencarian"
               class="absolute top-1/2 right-2.5 flex size-8 -translate-y-1/2 items-center justify-center rounded-full border border-line-strong text-muted hover:bg-navy-50 hover:text-ink">
                <x-ikon nama="silang" ukuran="size-4" />
            </a>
        @endif
    </div>
    <button type="submit" class="tombol tombol-navy py-3">
        <x-ikon nama="cari" ukuran="size-4" />
        Cari pesanan
    </button>
</form>

@if ($tidakKetemu)
    <div class="mt-5 border-l-2 border-merah bg-merah-50 px-4 py-3 text-sm">
        Tidak ada pesanan dengan kode <span class="tabular font-semibold">{{ $kode }}</span>.
        Periksa lagi kodenya, atau cari pelanggan lewat halaman Pesanan.
    </div>
@endif

@if ($order)
    @php
        $lunas = $order->sisa_tagihan <= 0;
        $siapDiserahkan = $lunas && ! $order->dikirim() && $order->bolehDiserahkan();
        $klaimMenunggu = $order->payments
            ->where('status', \App\Enums\PaymentStatus::MENUNGGU)
            ->whereNotNull('bukti_path')
            ->first();
    @endphp

    <section class="kartu mt-6 overflow-hidden">
        <h2 class="kartu-kop flex items-center gap-2.5">
            <x-ikon nama="pesanan" class="text-navy-400" />
            Detail pesanan
        </h2>

        <div class="p-4 sm:p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-3">
                    <a href="{{ route('panel.pesanan.show', $order) }}" class="tabular text-2xl font-extrabold text-navy-700">{{ $order->kode }}</a>
                    <span class="chip {{ $order->punyaKustom() ? 'chip-biru' : 'chip-ok' }} px-3 py-1 text-sm">
                        {{ $order->punyaKustom() ? 'Kustom / konveksi' : 'Stok toko' }}
                    </span>
                </div>
                @if ($siapDiserahkan)
                    <span class="chip chip-ok px-3 py-1.5 text-sm"><x-ikon nama="centang" ukuran="size-4" /> Siap diserahkan</span>
                @else
                    <span class="chip {{ $order->status->warna() }} px-3 py-1.5 text-sm">
                        <x-ikon :nama="$order->status->ikon()" ukuran="size-4" /> {{ $order->status->label() }}
                    </span>
                @endif
            </div>

            <div class="mt-5 grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
                {{-- Siapa dan kapan: yang ditanyakan kasir sebelum menyerahkan
                     barang ke orang yang berdiri di depannya. --}}
                <dl class="space-y-3 rounded-[12px] bg-paper p-4 text-sm">
                    @foreach ([
                        ['orang', 'Pelanggan', $order->customer->nama],
                        ['whatsapp', 'No. HP', null],
                        ['kalender', 'Tanggal pesan', $order->created_at->translatedFormat('j M Y, H:i')],
                        ['jam', 'Target selesai', $order->target_siap_pada?->translatedFormat('j M Y') ?? 'Belum ditentukan'],
                        ['pekerja', 'Dikerjakan oleh', $dikerjakanOleh->isNotEmpty() ? $dikerjakanOleh->implode(', ') : 'Belum tercatat'],
                    ] as [$ikon, $label, $nilai])
                        <div class="grid grid-cols-[1.25rem_7.5rem_minmax(0,1fr)] items-start gap-2">
                            <x-ikon :nama="$ikon" ukuran="size-5" class="{{ $ikon === 'whatsapp' ? 'text-ok' : 'text-navy-400' }}" />
                            <dt class="text-muted">{{ $label }}</dt>
                            <dd class="font-semibold wrap-break-word">
                                @if ($ikon === 'whatsapp')
                                    <a href="https://wa.me/{{ nomor_wa($order->customer->no_hp) }}" target="_blank" rel="noopener"
                                       class="tabular text-biru-700 hover:underline">{{ $order->customer->no_hp }}</a>
                                @else
                                    {{ $nilai }}
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>

                <div class="space-y-4">
                    <div class="rounded-[12px] bg-paper p-4">
                        <h3 class="flex items-center gap-2 text-sm font-bold"><x-ikon nama="stok" ukuran="size-4" class="text-navy-400" /> Rincian pesanan</h3>
                        <ul class="mt-3 space-y-2.5">
                            @foreach ($order->items as $item)
                                @php
                                    $gambar = $item->approvedDesign?->path
                                        ? asset('storage/'.$item->approvedDesign->path)
                                        : $item->product?->images->first()?->thumb_url;
                                    $ukuran = $item->rosters->isNotEmpty() ? $item->ringkasanUkuran() : $item->variant?->label;
                                @endphp
                                <li class="flex items-start gap-3 rounded-[10px] bg-field p-3">
                                    @if ($gambar)
                                        <img src="{{ $gambar }}" alt="" width="64" height="64" loading="lazy"
                                             class="size-16 shrink-0 rounded-[8px] border border-line object-cover">
                                    @else
                                        <span class="flex size-16 shrink-0 items-center justify-center rounded-[8px] border border-dashed border-line-strong text-navy-300">
                                            <x-ikon :nama="$item->kustom() ? 'kaos' : 'produk'" />
                                        </span>
                                    @endif
                                    <div class="min-w-0 text-sm">
                                        <p class="font-bold">{{ $item->nama_produk }}</p>
                                        @if ($ukuran)
                                            <p class="text-muted">Ukuran: {{ $ukuran }}</p>
                                        @endif
                                        <p class="text-muted">Jumlah: <span class="tabular">{{ $item->qty }} pcs</span>
                                            &middot; {{ $item->status_item->labelStaf() }}</p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="rounded-[12px] border border-line p-4 {{ $lunas ? 'bg-ok-50' : 'bg-merah-50' }}">
                        <h3 class="flex items-center gap-2 text-sm font-bold"><x-ikon nama="uang" ukuran="size-4" class="text-navy-400" /> Informasi pembayaran</h3>
                        <div class="mt-3 flex flex-wrap items-end gap-x-6 gap-y-3">
                            <div>
                                <p class="text-xs text-muted">Total pesanan</p>
                                <p class="tabular text-lg font-bold">{{ rupiah($order->total) }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-muted">Sudah dibayar</p>
                                <p class="tabular text-lg font-bold text-ok">{{ rupiah($order->sudah_dibayar) }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-muted">Sisa pembayaran</p>
                                <p class="tabular text-lg font-bold {{ $lunas ? 'text-ink' : 'text-merah' }}">{{ rupiah(max(0, $order->sisa_tagihan)) }}</p>
                            </div>
                            <span class="chip {{ $lunas ? 'chip-ok' : 'chip-merah' }} ml-auto px-3 py-1 text-sm">
                                <x-ikon :nama="$lunas ? 'centang' : 'peringatan'" ukuran="size-4" />
                                {{ $lunas ? 'Lunas' : 'Belum lunas' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

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

        {{-- Kembali di kiri, tindakan di kanan, seperti gambar contoh. Tindakannya
             bergantung pada keadaan: pelunasan dulu, baru serah terima (KEP-006). --}}
        <div class="flex flex-wrap items-end justify-between gap-3 border-t border-line bg-paper p-4 sm:px-5">
            <a href="{{ route('panel.serah-terima') }}" class="tombol tombol-garis" data-kembali>
                <x-ikon nama="kembali" ukuran="size-4" /> Kembali
            </a>

            @if ($order->sisa_tagihan > 0 && ! $klaimMenunggu)
                <form method="POST" action="{{ route('panel.pesanan.pembayaran.catat', $order) }}"
                      class="flex flex-wrap items-end gap-3">
                    @csrf
                    <div class="w-40"><label for="lunas-jumlah" class="label-form">Nominal diterima</label>
                        <input type="number" id="lunas-jumlah" name="jumlah" value="{{ $order->sisa_tagihan }}"
                               class="kotak-input tabular" min="1" required></div>
                    <div class="w-40"><label for="lunas-metode" class="label-form">Metode</label>
                        <select id="lunas-metode" name="metode" class="kotak-input">
                            <option value="TUNAI">Tunai</option>
                            <option value="QRIS">QRIS</option>
                            <option value="TRANSFER">Transfer bank</option>
                        </select></div>
                    <input type="hidden" name="jenis" value="PELUNASAN">
                    <button type="submit" class="tombol tombol-utama">
                        <x-ikon nama="uang" ukuran="size-4" />
                        Catat pelunasan
                    </button>
                </form>
            @elseif ($order->dikirim())
                {{-- Pesanan yang dikirim tidak lewat meja kasir (KEP-026). --}}
                <p class="max-w-md text-sm leading-relaxed">
                    Pesanan ini <strong>dikirim</strong>, tidak diambil di toko. Catat jasa kirim dan
                    nomor resinya di <a href="{{ route('panel.pesanan.show', $order) }}" class="tautan">halaman pesanan</a>.
                </p>
            @elseif ($order->bolehDiserahkan())
                <form method="POST" action="{{ route('panel.pesanan.serah-terima', $order) }}"
                      class="flex flex-wrap items-end gap-3">
                    @csrf
                    <div class="min-w-56">
                        <label for="diambil_oleh" class="label-form">Nama pengambil</label>
                        <input type="text" id="diambil_oleh" name="diambil_oleh" class="kotak-input" required
                               value="{{ $order->customer->nama }}">
                        <p class="mt-1 text-xs text-muted">Ubah kalau diwakilkan orang lain.</p>
                    </div>
                    <button type="submit" class="tombol tombol-navy mb-5 py-3">
                        <x-ikon nama="centang" ukuran="size-4" />
                        Konfirmasi serah terima
                    </button>
                </form>
            @else
                <p class="text-sm text-muted">
                    Pesanan ini belum siap diserahkan. Statusnya sekarang {{ $order->status->label() }}.
                </p>
            @endif
        </div>
    </section>
@endif

@if ($siapHariIni->isNotEmpty())
    <section class="mt-10 max-w-3xl">
        <h2 class="text-lg font-extrabold">Menunggu diambil</h2>
        <ul class="kartu mt-4 divide-y divide-line overflow-hidden text-sm">
            @foreach ($siapHariIni as $o)
                {{-- Seluruh baris jadi sasaran klik: di layar sentuh, kode ambil
                     selebar delapan karakter terlalu kecil untuk jempol. --}}
                <li class="relative flex flex-wrap items-center gap-3 px-4 py-3 hover:bg-navy-50">
                    <a href="{{ route('panel.serah-terima', ['kode' => $o->kode_ambil]) }}"
                       class="tabular font-semibold text-navy-700 before:absolute before:inset-0 before:content-['']">{{ $o->kode_ambil }}</a>
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
