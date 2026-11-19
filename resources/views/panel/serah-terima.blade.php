@extends('layouts.panel')
@section('judul', 'Serah terima')

@section('isi')
<x-kop judul="Serah terima" ikon="serah-terima" warna="pink"
       catatan="Catat penyerahan pesanan yang sudah selesai kepada pelanggan. Pindai atau ketik kode ambil yang dibawa pelanggan, atau kode pesanannya." />

{{-- Kotak kode dibuat besar dengan sengaja: ini satu-satunya isian di layar
     kasir, diketik sambil berdiri, sering dari layar sentuh, dan kodenya
     dibacakan pelanggan dari HP-nya. Tombol silang mengosongkannya untuk
     pelanggan berikutnya. --}}
<form method="GET" class="flex flex-wrap items-center gap-2" role="search">
    <label class="sr-only" for="kode">Kode ambil atau kode pesanan</label>
    <div class="relative min-w-60 flex-1 sm:max-w-xl">
        <x-ikon nama="qr" class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-navy-400" />
        <input type="text" id="kode" name="kode" value="{{ $kode }}" @if (! $order) autofocus @endif
               placeholder="Scan / masukkan no. pesanan..."
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

{{-- Daftar bertab seperti gambar klien (KEP-130). Centang menyerahkan
     beberapa pesanan lunas sekaligus ke satu pengambil, misalnya reseller
     yang mengambil tiga pesanan. Yang belum lunas tidak bisa dicentang. --}}
<section class="mt-8" x-data="{ terpilih: 0 }">
    <nav class="flex flex-wrap gap-2" aria-label="Daftar serah terima">
        @foreach ([
            'siap' => ['serah-terima', 'Siap diserahkan ('.$jumlahSiap.')'],
            'sudah' => ['centang', 'Sudah diserahkan'],
            'semua' => ['daftar', 'Semua'],
        ] as $kunci => [$ikon, $label])
            <a href="{{ route('panel.serah-terima', array_filter(['tab' => $kunci === 'siap' ? null : $kunci, 'kode' => $kode ?: null])) }}"
               @class(['tombol tombol-sm', 'tombol-navy' => $tab === $kunci, 'tombol-garis' => $tab !== $kunci])
               @if ($tab === $kunci) aria-current="page" @endif>
                <x-ikon :nama="$ikon" ukuran="size-4" /> {{ $label }}
            </a>
        @endforeach
    </nav>

    <form method="POST" action="{{ route('panel.serah-terima.massal') }}" id="serah-massal"
          class="mt-4 flex flex-wrap items-end gap-3 rounded-[12px] border border-biru-100 bg-biru-50 p-4"
          x-show="terpilih > 0" x-cloak>
        @csrf
        <div class="min-w-56 flex-1">
            <label for="massal-pengambil" class="label-form">Nama pengambil untuk <span x-text="terpilih"></span> pesanan</label>
            <input type="text" id="massal-pengambil" name="diambil_oleh" maxlength="100" required class="kotak-input bg-field"
                   placeholder="Misal: nama reseller atau perwakilan tim">
        </div>
        <button type="submit" class="tombol tombol-navy">
            <x-ikon nama="serah-terima" ukuran="size-4" /> Serahkan terpilih (<span x-text="terpilih"></span>)
        </button>
    </form>

    <div class="kartu mt-4 overflow-hidden">
        @if ($daftar->isEmpty())
            <p class="px-5 py-8 text-center text-sm text-muted">
                {{ match ($tab) {
                    'sudah' => 'Belum ada pesanan yang diserahkan.',
                    'semua' => 'Belum ada pesanan yang siap atau sudah diserahkan.',
                    default => 'Tidak ada pesanan yang menunggu diambil.',
                } }}
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="tabel min-w-[44rem] text-sm">
                    <thead>
                        <tr>
                            <th class="w-10"><span class="sr-only">Pilih</span></th>
                            <th>No. pesanan</th>
                            <th>Penerima</th>
                            <th>Keterangan</th>
                            <th>Status</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($daftar as $o)
                            @php
                                $sudah = $o->status === \App\Enums\OrderStatus::SELESAI;
                                $kirim = ! $sudah && $o->dikirim();
                                $bisa = $o->bolehDiserahkan();
                                $keterangan = $o->items->map(fn ($i) => $i->nama_produk.' ('.$i->qty.' pcs)')->implode(', ');
                            @endphp
                            <tr>
                                <td>
                                    @if (! $sudah)
                                        <input type="checkbox" name="pesanan[]" value="{{ $o->id }}" form="serah-massal"
                                               class="kotak-centang" @disabled(! $bisa)
                                               title="{{ $bisa ? 'Pilih untuk diserahkan sekaligus' : ($kirim ? 'Dikirim lewat kurir, catat resinya di halaman pesanan' : 'Belum lunas, catat pelunasannya dulu') }}"
                                               @change="terpilih += $event.target.checked ? 1 : -1"
                                               aria-label="Pilih pesanan {{ $o->kode }}">
                                    @endif
                                </td>
                                <td class="tabular font-bold">
                                    <a href="{{ route('panel.pesanan.show', $o) }}" class="hover:underline">{{ $o->kode }}</a>
                                    @if (! $sudah && $o->kode_ambil)
                                        <span class="block text-xs font-normal text-muted">Kode ambil {{ $o->kode_ambil }}</span>
                                    @endif
                                </td>
                                <td>{{ $sudah ? $o->diambil_oleh : $o->customer->nama }}</td>
                                <td class="max-w-64">{{ $keterangan }}</td>
                                <td>
                                    @if ($sudah)
                                        <span class="chip chip-netral"><x-ikon nama="centang" ukuran="size-3.5" /> Diserahkan {{ $o->diambil_pada?->translatedFormat('j M') }}</span>
                                    @elseif ($kirim)
                                        <span class="chip chip-pink"><x-ikon nama="kirim" ukuran="size-3.5" /> Siap dikirim</span>
                                    @elseif ($bisa)
                                        <span class="chip chip-ok"><x-ikon nama="centang" ukuran="size-3.5" /> Selesai, lunas</span>
                                    @else
                                        <span class="chip chip-kuning"><x-ikon nama="dompet" ukuran="size-3.5" /> Belum lunas</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    @if ($sudah)
                                        <a href="{{ route('panel.pesanan.show', $o) }}" class="tombol tombol-garis tombol-sm">Lihat</a>
                                    @elseif ($kirim)
                                        <a href="{{ route('panel.pesanan.show', $o) }}#pengambilan" class="tombol tombol-garis tombol-sm">
                                            <x-ikon nama="kirim" ukuran="size-4" /> Catat resi
                                        </a>
                                    @else
                                        <a href="{{ route('panel.serah-terima', ['kode' => $o->kode_ambil ?? $o->kode]) }}" class="tombol tombol-navy tombol-sm">
                                            <x-ikon nama="serah-terima" ukuran="size-4" /> Serah terima
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @include('panel.komponen.halaman', ['daftar' => $daftar, 'benda' => 'pesanan'])
        @endif
    </div>
</section>
@endsection
