@extends('layouts.publik')
@section('judul', 'Pesanan '.$order->kode)
@section('subjudul', 'Pesanan '.$order->kode)
@section('subcatatan', $order->status->label())

@section('isi')
@php
    use App\Enums\OrderStatus;
    use App\Enums\OrderItemStatus;
@endphp

<div class="mx-auto max-w-3xl px-5 py-10">

    @if (session('sukses'))
        <p class="mb-6 border-l-2 border-navy bg-navy-50 px-4 py-3 text-sm">{{ session('sukses') }}</p>
    @endif
    @if ($errors->any())
        <p class="mb-6 border-l-2 border-merah bg-merah-50 px-4 py-3 text-sm">{{ $errors->first() }}</p>
    @endif

    <p class="text-sm text-muted tabular">{{ $order->kode }}</p>
    <h1 class="judul-tampil mt-1 text-[2.5rem]">{{ $order->status->label() }}</h1>
    @if ($tagihan && $tagihan['jenis'] === 'awal')
        <p class="mt-1 text-muted">Selesaikan pembayaran untuk melanjutkan proses pesanan Anda.</p>
    @endif
    <p class="mt-2 text-sm text-muted">
        Atas nama {{ $order->customer->nama }} &middot; {{ $order->customer->email_samar }}
    </p>

    {{-- Tautan halaman ini adalah satu-satunya kunci pesanan (KEP-008), dan
         pelanggan sering perlu mengirimkannya ke bendahara tim atau menyimpannya
         sendiri. Menyalin dari bilah alamat di HP jauh lebih repot daripada
         satu tombol. --}}
    {{-- Bagikan lewat WhatsApp (KEP-145): tanpa nomor tujuan, jadi pelanggan
         sendiri yang memilih kontak atau grup timnya. --}}
    @php
        $tautanPesanan = route('lacak.token', $order->access_token);
        $bagikanWa = 'https://wa.me/?text='.rawurlencode('Pesanan '.$order->kode.' di '.setting('toko_nama', 'Setia Sport').': '.$tautanPesanan);
    @endphp
    <div class="mt-4" x-data="{ tersalin: false }">
        <div class="flex flex-wrap gap-2">
            <button type="button"
                    @click="navigator.clipboard.writeText(@js($tautanPesanan))
                            .then(() => { tersalin = true; setTimeout(() => tersalin = false, 2500) })"
                    class="tombol tombol-garis tombol-sm">
                <span x-show="!tersalin">Salin tautan pesanan</span>
                <span x-show="tersalin" x-cloak>Tautan tersalin</span>
            </button>
            <a href="{{ $bagikanWa }}" target="_blank" rel="noopener" class="tombol tombol-garis tombol-sm">
                <x-ikon nama="whatsapp" ukuran="size-4" class="text-ok" />
                Bagikan lewat WhatsApp
            </a>
        </div>
        <p class="mt-1.5 text-xs leading-relaxed text-muted">
            {{-- Teks klien (KEP-148). --}}
            Bagikan tautan ini kepada anggota tim untuk memantau pesanan bersama. Informasi pribadi tetap terlindungi.
        </p>
    </div>

    {{-- Chat yang dibuka dari halaman ini membawa tautan pesanannya, jadi CS
         tahu pesanan mana yang dibicarakan dan bahwa penanya memang pemegangnya. --}}
    @if (cs_aktif())
        <button type="button" x-data @click="$dispatch('buka-cs', {})" class="tautan area-sentuh mt-3 text-sm">
            Ada pertanyaan atau komplain soal pesanan ini? Chat dengan CS
        </button>
    @elseif ($waPesanan = tautan_wa('Halo '.setting('toko_nama', 'Setia Sport').', saya mau tanya soal pesanan '.$order->kode.'.'))
        <a href="{{ $waPesanan }}" target="_blank" rel="noopener" class="tautan area-sentuh mt-3 inline-block text-sm">
            Ada pertanyaan atau komplain soal pesanan ini? Hubungi lewat WhatsApp
        </a>
    @endif

    {{-- Tagihan yang sedang harus dibayar: instruksinya jadi hal pertama yang
         terlihat. Berlaku untuk DP di awal maupun pelunasan setelah barang jadi
         (KEP-028). Tidak ada gerbang pembayaran (KEP-023) dan tidak ada jalur
         WhatsApp (KEP-029): pelanggan transfer ke rekening yang tertera di sini,
         mengunggah buktinya di sini, dan admin mencocokkannya dengan mutasi. --}}
    @if ($tagihan)
        @include('publik.lacak.bayar', ['order' => $order, 'tagihan' => $tagihan])
    @endif

    {{-- Kedaluwarsa (KEP-097). Tanpa ini pembeli hanya membaca "Item ini dibatalkan"
         tanpa tahu sebabnya, dan yang sudah terlanjur transfer tidak tahu harus
         menghubungi siapa. --}}
    @if ($order->status === OrderStatus::KADALUARSA)
        <section class="mt-8 border border-line bg-field p-5">
            <h2 class="font-bold">Pesanan ini dibatalkan otomatis</h2>
            <p class="mt-1 text-sm leading-relaxed">
                Pembayarannya belum kami terima sampai batas waktu, jadi pesanan dibatalkan dan
                barangnya kembali dijual. Tidak ada tagihan yang perlu dibayar. Kalau Anda sudah
                terlanjur transfer, hubungi kami lewat {{ kanal_kontak() }} dan sebutkan kode
                {{ $order->kode }}.
            </p>
            <a href="{{ route('katalog') }}" class="tombol tombol-garis mt-4">Pesan lagi</a>
        </section>
    @endif

    {{-- Sudah dikirim: nomor resi adalah satu-satunya hal yang dicari pelanggan
         di halaman ini, jadi ia yang paling besar (KEP-026). --}}
    @if ($order->dikirim_pada)
        <section class="mt-8 border border-line bg-field">
            <h2 class="border-b border-line px-5 py-3 font-semibold">
                {{ $order->diterima_pada ? 'Sudah sampai' : 'Sedang dikirim' }}
            </h2>
            <div class="space-y-4 p-5">
                <div>
                    <p class="text-sm text-muted">{{ $order->jasa_kirim }}</p>
                    <p class="numeral mt-2 text-4xl break-all">{{ $order->no_resi }}</p>
                    <p class="mt-3 text-sm text-muted">
                        Diserahkan ke kurir {{ $order->dikirim_pada->translatedFormat('l, j F Y, H:i') }}.
                    </p>
                </div>

                {{-- Posisi paket menurut kurir (KEP-061). Nomor resi bukan jawaban;
                     "sudah sampai Solo kemarin sore" baru jawaban. Kalau kurirnya
                     belum menjawab, bagian ini memang tidak muncul, dan nomor resi
                     di atas tetap bisa disalin ke situs kurir seperti biasa. --}}
                @if ($order->lacak_status)
                    <div class="border-t border-line pt-4">
                        <p class="flex flex-wrap items-baseline gap-x-2 font-semibold">
                            {{ $order->lacak_catatan ?: 'Posisi terakhir' }}
                            @if ($order->lacak_pada)
                                <span class="text-sm font-normal text-muted">
                                    diperbarui {{ $order->lacak_pada->diffForHumans() }}
                                </span>
                            @endif
                        </p>

                        @if (! empty($order->lacak_riwayat))
                            <ol class="mt-3 space-y-2 text-sm">
                                @foreach ($order->lacak_riwayat as $baris)
                                    <li class="flex gap-3">
                                        <span class="tahap-titik {{ $loop->first ? 'tahap-titik-kini' : 'tahap-titik-lewat' }} mt-1.5 shrink-0"></span>
                                        <span>
                                            {{ $baris['keterangan'] ?? '' }}
                                            @if (! empty($baris['waktu']))
                                                <span class="block text-xs text-muted">
                                                    {{ \Illuminate\Support\Carbon::parse($baris['waktu'])->translatedFormat('j M Y, H:i') }}
                                                </span>
                                            @endif
                                        </span>
                                    </li>
                                @endforeach
                            </ol>
                        @endif
                    </div>
                @endif

                @if (setting('panduan_resi'))
                    <p class="border-l-2 border-navy bg-navy-50 p-4 text-sm leading-relaxed">
                        {{ setting('panduan_resi') }}
                    </p>
                @endif

                @if ($order->alamat_kirim)
                    <div class="border-t border-line pt-4 text-sm">
                        <p class="text-muted">Dikirim ke</p>
                        <p class="mt-1 leading-relaxed">{{ $order->alamat_kirim }}</p>
                    </div>
                @endif

                {{-- Konfirmasi oleh pelanggan sendiri (KEP-131). Email "pesanan
                     dikirim" mengarah ke sini; tombolnya meminta konfirmasi kedua
                     supaya tidak tertekan tanpa sengaja. --}}
                @if ($order->status === OrderStatus::DIKIRIM && ! $order->diterima_pada)
                    <div id="konfirmasi-diterima" class="scroll-mt-32 rounded-[12px] border border-ok bg-ok-50 p-4"
                         x-data="{ yakin: false }">
                        <p class="font-bold">Paket sudah sampai di tangan Anda?</p>
                        <p class="mt-1 text-sm leading-relaxed">
                            Tekan tombol di bawah setelah paketnya Anda terima dan periksa. Pesanan lalu kami tutup.
                        </p>
                        <button type="button" class="tombol tombol-navy mt-3" x-show="! yakin" @click="yakin = true">
                            <x-ikon nama="centang" ukuran="size-4" /> Pesanan sudah saya terima
                        </button>
                        <form method="POST" action="{{ route('lacak.diterima', $order->access_token) }}"
                              class="mt-3 flex flex-wrap items-center gap-2" x-show="yakin" x-cloak>
                            @csrf
                            <span class="w-full text-sm font-semibold">Yakin paketnya sudah diterima?</span>
                            <button type="submit" class="tombol tombol-navy" data-teks-proses="Menyimpan...">
                                Ya, sudah saya terima
                            </button>
                            <button type="button" class="tombol tombol-garis" @click="yakin = false">Belum</button>
                        </form>
                    </div>
                @endif
            </div>
        </section>

    {{-- Siap diambil: kode ambil dan sisa tagihan adalah dua hal yang dicari
         pelanggan saat berdiri di depan toko. --}}
    @elseif ($order->status === OrderStatus::SIAP_DIAMBIL && ! $order->dikirim())
        <section class="mt-8 border border-line bg-field" id="ambil-di-toko">
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
                        <p class="text-sm text-muted">Sisa pembayaran</p>
                        <p class="numeral mt-2 text-4xl text-ink">{{ rupiah($order->sisa_tagihan) }}</p>
                        <p class="mt-3 text-sm text-muted">Bayar di kasir saat mengambil, atau transfer lewat bagian Pelunasan di atas.</p>
                    @else
                        <p class="text-sm text-muted">Pembayaran</p>
                        <p class="numeral mt-2 text-3xl text-ok">Lunas</p>
                        <p class="mt-3 text-sm text-muted">Tinggal diambil.</p>
                    @endif
                </div>
            </div>
            {{-- Batas pengambilan disebut di tempat pelanggan pasti membacanya,
                 bukan di halaman syarat terpisah (KEP-043). --}}
            @if ($order->siap_pada)
                <p class="border-t border-line px-5 py-3 text-sm leading-relaxed">
                    Diambil paling lambat
                    <strong>{{ $order->siap_pada->copy()->addDays((int) setting('hangus_hari', 30))->translatedFormat('j F Y') }}</strong>.
                    Lewat dari itu pesanan dianggap hangus dan pembayaran tidak dikembalikan.
                    Kalau berhalangan, kabari kami sebelum tanggal tersebut.
                </p>
            @endif

            <div class="border-t border-line px-5 py-4 text-sm leading-relaxed text-muted">
                <span class="font-medium text-ink">Toko {{ setting('toko_nama', 'Setia Sport') }}</span><br>
                {{ setting('toko_alamat') }}<br>
                <span class="mt-2 inline-block">{{ baris_html(setting('toko_jam_buka')) }}</span><br>
                <a href="{{ setting('maps_embed') ?: tautan_peta((string) setting('toko_alamat')) }}" target="_blank" rel="noopener"
                   class="tautan mt-2 inline-block">Buka di Google Maps</a>
            </div>
        </section>

    {{-- Sudah siap tapi akan dikirim: yang ditunggu bukan kode ambil,
         melainkan nomor resi yang belum terbit. --}}
    @elseif ($order->status === OrderStatus::SIAP_DIAMBIL && $order->dikirim())
        <section class="mt-8 border border-line bg-field">
            <h2 class="border-b border-line px-5 py-3 font-semibold">Menunggu dikirim</h2>
            <div class="space-y-3 p-5 text-sm leading-relaxed">
                <p>
                    Barangnya sudah selesai dan sedang kami siapkan untuk dikirim.
                    Nomor resinya muncul di halaman ini, dan kami kirim juga lewat email,
                    begitu paket diserahkan ke kurir.
                </p>
                @if ($order->sisa_tagihan > 0)
                    <p class="border-l-2 border-navy bg-navy-50 p-4">
                        Masih ada sisa <strong>{{ rupiah($order->sisa_tagihan) }}</strong>.
                        Paket berangkat setelah pelunasannya kami terima. Rekening dan tempat
                        mengunggah buktinya ada di bagian Pelunasan di atas.
                    </p>
                @endif
                @if ($order->alamat_kirim)
                    <div class="border-t border-line pt-3">
                        <p class="text-muted">Dikirim ke</p>
                        <p class="mt-1">{{ $order->alamat_kirim }}</p>
                    </div>
                @endif
            </div>
        </section>
    @endif

    {{-- Timeline per item, bukan satu timeline tunggal: satu order boleh memuat
         barang stok dan barang kustom yang jalannya berbeda (KEP-001).
         
         Sejak KEP-027 keduanya juga DIPISAH jadi dua kelompok. Menaruhnya
         berurutan membuat pelanggan mengira semuanya menunggu hal yang sama,
         padahal jersey menunggu persetujuan desain sementara bolanya sudah ada
         di rak sejak hari pertama. --}}
    @php
        $kelompok = $order->items->partition(fn ($i) => $i->kustom());
        $grup = array_values(array_filter([
            [
                'judul' => 'Dibuat di workshop',
                'catatan' => 'Melewati desain, persetujuan Anda, lalu produksi. Diambil di toko.',
                'items' => $kelompok[0],
            ],
            [
                'judul' => 'Ready Stock',
                'catatan' => $order->dikirim()
                    ? 'Barang yang sudah ada di rak. Pesanan ini dikirim ke alamat Anda.'
                    : 'Barang yang sudah ada di rak, tinggal disiapkan.',
                'items' => $kelompok[1],
            ],
        ], fn ($g) => $g['items']->isNotEmpty()));
    @endphp

    <section class="mt-8">
        <h2 class="text-xl font-extrabold">Progres pesanan</h2>

        @foreach ($grup as $g)
        <div class="mt-6">
            {{-- Judul kelompok disembunyikan kalau isinya cuma satu jenis:
                 memberi label pada satu-satunya kelompok tidak menjelaskan apa pun. --}}
            @if (count($grup) > 1)
                <div class="border-b border-line pb-2">
                    <h3 class="font-bold">{{ $g['judul'] }}</h3>
                    <p class="mt-0.5 text-sm text-muted">{{ $g['catatan'] }}</p>
                </div>
            @endif

            <div class="mt-4 space-y-4">
            @foreach ($g['items'] as $item)
                @include('publik.lacak.item', ['item' => $item, 'order' => $order])
            @endforeach
            </div>
        </div>
        @endforeach
    </section>

    @include('publik.lacak.ringkasan-bayar', ['order' => $order])

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
            @if ($order->biaya_layanan > 0)
                <div class="flex justify-between gap-3 text-muted">
                    <dt>Biaya layanan</dt>
                    <dd class="tabular">{{ rupiah($order->biaya_layanan) }}</dd>
                </div>
            @endif
            {{-- Ongkir ditulis walau masih nol (KEP-058): pelanggan yang minta
                 dikirim berhak tahu angkanya belum masuk, bukan menemukan
                 tagihannya bertambah diam-diam nanti. --}}
            @if ($order->dikirim())
                <div class="flex justify-between gap-3 text-muted">
                    <dt>Ongkos kirim</dt>
                    <dd class="tabular">
                        {{ $order->ongkir > 0 ? rupiah($order->ongkir) : 'dihitung dulu, menyusul' }}
                    </dd>
                </div>
            @endif
            <div class="flex justify-between gap-3">
                <dt class="font-semibold">Total</dt>
                <dd class="tabular font-semibold">{{ rupiah($order->total) }}</dd>
            </div>
            @if ($order->sudah_dibayar > 0 || $order->dikembalikan > 0)
                {{-- Kelebihan bayar yang sudah dikembalikan ditulis terbuka (KEP-158). --}}
                @if ($order->dikembalikan > 0)
                    <div class="flex justify-between gap-3 text-muted">
                        <dt>Dibayar</dt>
                        <dd class="tabular">{{ rupiah($order->sudah_dibayar + $order->dikembalikan) }}</dd>
                    </div>
                    <div class="flex justify-between gap-3 text-muted">
                        <dt>Dikembalikan ke Anda</dt>
                        <dd class="tabular">−{{ rupiah($order->dikembalikan) }}</dd>
                    </div>
                @else
                    <div class="flex justify-between gap-3 text-muted">
                        <dt>Sudah dibayar</dt>
                        <dd class="tabular">{{ rupiah($order->sudah_dibayar) }}</dd>
                    </div>
                @endif
                <div class="flex justify-between gap-3">
                    <dt class="font-semibold">Sisa tagihan</dt>
                    <dd class="tabular font-semibold {{ $order->sisa_tagihan > 0 ? 'text-ink' : 'text-ok' }}">
                        {{ $order->sisa_tagihan > 0 ? rupiah($order->sisa_tagihan) : 'Lunas' }}
                    </dd>
                </div>
                @if ($order->kelebihan_bayar > 0)
                    <div class="flex justify-between gap-3">
                        <dt class="font-semibold">Kelebihan bayar</dt>
                        <dd class="tabular font-semibold">{{ rupiah($order->kelebihan_bayar) }}</dd>
                    </div>
                    <p class="text-xs leading-relaxed text-muted">Kelebihan ini akan kami kembalikan. Kami hubungi Anda untuk caranya.</p>
                @endif
            @endif
        </dl>
    </section>

    {{-- Riwayat sebagai garis waktu berikon (KEP-127), seperti gambar klien.
         Disusun App\Support\RiwayatPesanan: hanya yang sudah terjadi, tanpa
         catatan staf. --}}
    @php $riwayat = \App\Support\RiwayatPesanan::untuk($order); @endphp
    <section class="mt-8">
        <h2 class="flex items-center gap-2.5 text-xl font-extrabold"><x-ikon nama="jam" class="text-navy-400" /> Riwayat</h2>
        <p class="mt-1 text-sm text-muted">Berikut adalah riwayat aktivitas dan pembaruan pesanan Anda.</p>

        <ol class="kartu-bayar riwayat-lacak mt-4">
            @foreach ($riwayat as $r)
                <li @class(['riwayat-lacak-kini' => $loop->last, 'riwayat-lacak-awal' => $loop->first])>
                    <span class="riwayat-lacak-ikon"><x-ikon :nama="$r['ikon']" ukuran="size-5" /></span>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold">{{ $r['judul'] }}</p>
                        <p class="text-sm text-muted">{{ $r['kalimat'] }}</p>
                    </div>
                    <p class="tabular shrink-0 text-sm text-muted sm:text-right">{{ $r['waktu']->translatedFormat('j M Y, H.i') }}</p>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- Pembatalan (KEP-041). Ketentuannya ditulis apa adanya SEBELUM tombolnya,
         termasuk kalimat pahit bahwa uang muka bisa hangus: pelanggan yang baru
         tahu akibatnya setelah menekan tombol akan merasa dijebak, dan itu
         berakhir jadi pertengkaran di WhatsApp. --}}
    @php $batal = $pembatalan['terakhir']; @endphp

    @if ($batal && $batal->menunggu())
        <section class="mt-8 border border-kuning bg-kuning-50 p-5">
            <h2 class="font-bold">Pengajuan pembatalan sedang diperiksa</h2>
            <p class="mt-1 text-sm leading-relaxed">
                Diajukan {{ $batal->created_at->translatedFormat('j M Y, H:i') }}. Keputusannya
                tampil di halaman ini{{ $order->customer->email ? ' dan kami kirim lewat email' : '' }}.
                Selama belum diputuskan, pesanan Anda tetap berjalan seperti biasa.
            </p>
            <p class="mt-2 text-sm text-muted">Alasan yang Anda tulis: "{{ $batal->alasan }}"</p>
        </section>
    @elseif ($batal && $batal->status === \App\Enums\StatusPembatalan::DISETUJUI)
        <section class="mt-8 border border-line bg-field p-5">
            <h2 class="font-bold">Pesanan dibatalkan</h2>
            <p class="mt-1 text-sm leading-relaxed">
                @if ($batal->refund_jumlah > 0)
                    Pengembalian sebesar <strong>{{ rupiah($batal->refund_jumlah) }}</strong>
                    dikirim lewat {{ $batal->refund_metode === 'TUNAI' ? 'tunai di toko' : 'transfer' }}.
                    Kalau dalam 3 hari kerja belum masuk, hubungi toko.
                @else
                    Tidak ada pengembalian uang untuk pembatalan ini.
                @endif
            </p>
            @if ($batal->catatan_admin)
                <p class="mt-2 text-sm text-muted">Catatan toko: {{ $batal->catatan_admin }}</p>
            @endif
        </section>
    @elseif ($batal && $batal->status === \App\Enums\StatusPembatalan::DITOLAK)
        <section class="mt-8 border border-line bg-field p-5">
            <h2 class="font-bold">Pengajuan pembatalan ditolak</h2>
            <p class="mt-1 text-sm leading-relaxed">{{ $batal->catatan_admin }}</p>
            <p class="mt-2 text-sm text-muted">
                Pesanan Anda tetap berjalan. Kalau masih ingin membicarakannya, hubungi toko.
            </p>
        </section>
    @endif

    @if ($pembatalan['tertutup'])
        {{-- Pesanan kustom hanya bisa diajukan batal sampai tahap desain (KEP-164). --}}
        <section class="kartu mt-8 p-5">
            <h2 class="flex items-center gap-2.5 font-bold">
                <x-ikon nama="peringatan" class="text-navy-400" /> Pembatalan tidak tersedia
            </h2>
            <p class="mt-1 text-sm leading-relaxed text-muted">
                Desain pesanan ini sudah disetujui dan masuk produksi. Kain dicetak khusus dengan desain dan
                nama tim Anda, jadi pesanan tidak bisa dibatalkan lagi. Kalau ada kendala, hubungi kami lewat
                {{ kanal_kontak() }}.
            </p>
        </section>
    @endif

    @if ($pembatalan['boleh'])
        {{-- Kartu terbuka seperti gambar klien (KEP-128). Kalimat di bawah
             judulnya ketentuan toko untuk tahap ini. Pesanan kustom hanya bisa
             diajukan sampai tahap desain (KEP-164). --}}
        <section class="kartu-bayar mt-8">
            <h2 class="flex items-center gap-2.5 text-xl font-extrabold">
                <x-ikon nama="chat" class="text-navy-700" /> Ajukan pembatalan pesanan
            </h2>
            <p class="mt-1 leading-relaxed text-muted">{{ $pembatalan['tahap']->ketentuan() }}</p>

            @if ($pembatalan['tahap']->adaPengembalian() && $order->sudah_dibayar > 0)
                <p class="mt-2 text-sm">
                    Perkiraan pengembalian:
                    <strong class="tabular">{{ rupiah($pembatalan['usulan']) }}</strong>
                    dari {{ rupiah($order->sudah_dibayar) }} yang sudah dibayar.
                    Angka akhirnya dipastikan toko saat menyetujui.
                </p>
            @endif

            <form method="POST" action="{{ route('lacak.pembatalan', $order->access_token) }}" class="mt-5 space-y-4">
                @csrf
                <div>
                    <label for="alasan-batal" class="label-form">Alasan pembatalan <span class="text-merah">*</span></label>
                    <textarea id="alasan-batal" name="alasan" rows="3" class="kotak-input" required
                              minlength="10" maxlength="1000"
                              placeholder="Tulis alasan pembatalan...">{{ old('alasan') }}</textarea>
                    @error('alasan')
                        <p class="mt-1 text-sm text-merah">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex flex-wrap items-center gap-x-5 gap-y-3">
                    <button type="submit" class="tombol border border-merah/40 bg-merah-50 text-merah hover:bg-merah-100"
                            data-teks-proses="Mengirim...">
                        <x-ikon nama="silang" ukuran="size-4" /> Ajukan pembatalan
                    </button>
                    <p class="flex items-center gap-2 text-sm text-muted">
                        <x-ikon nama="bantuan" ukuran="size-4" />
                        @if ($order->customer->email)
                            Pengajuan akan kami tinjau dan diberitahukan melalui email.
                        @else
                            Pengajuan akan kami tinjau dan keputusannya tampil di halaman ini.
                        @endif
                    </p>
                </div>
                <p class="text-xs leading-relaxed text-muted">
                    Pesanan belum langsung batal. Selama diperiksa, pesanan tetap berjalan seperti biasa.
                </p>
            </form>
        </section>
    @endif

    {{-- Ditawarkan di sini karena inilah saat pelanggan paling mungkin mau:
         pesanannya sudah nyata dan mereka sedang melihat manfaatnya. --}}
    @if (! auth('pelanggan')->check() && ! $order->customer->akunAktif())
        <div class="notis notis-info mt-4">
            <p class="font-semibold">Sering memesan di sini?</p>
            <p class="mt-1">
                Buat akun sekali, lalu semua pesanan Anda tampil dalam satu halaman tanpa
                perlu mencari email. Pesanan ini ikut tercatat otomatis.
            </p>
            <a href="{{ route('daftar', ['email' => $order->customer->email]) }}"
               class="tombol tombol-garis tombol-sm mt-3">Buat akun</a>
        </div>
    @endif
</div>
@endsection
