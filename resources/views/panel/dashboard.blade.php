@extends('layouts.panel')
@section('judul', 'Dashboard')

@php
    $u = auth('web')->user();
    $bolehPesanan = $u->punyaIzin('order.lihat');
    $stokPerluDiisi = $stokHabis->concat($stokMenipis)->take(5);
@endphp

@section('isi')
{{-- Tata letak dari revisi klien (KEP-112): sapaan, lima kartu berwarna,
     pesanan terbaru, stok, antrean produksi, dan jalan pintas. Daftar "yang
     perlu ditangani" tetap ada di bawahnya: dialah alasan dashboard ini
     dibuka tiap pagi, hanya kini tidak lagi sendirian. --}}
<header class="mb-6 flex flex-wrap items-start justify-between gap-4">
    <div class="min-w-0">
        <h1 class="kop-judul text-2xl sm:text-[1.75rem]">Selamat datang, {{ $u->name }} <span aria-hidden="true">👋</span></h1>
        <p class="kop-catatan">Kelola pesanan, produksi, stok, dan penjualan {{ setting('toko_nama', 'Setia Sport') }} dengan mudah.</p>
    </div>
    <p class="inline-flex items-center gap-2 rounded-[10px] border border-line bg-field px-4 py-2.5 text-sm font-semibold shadow-sm">
        <x-ikon nama="kalender" ukuran="size-4" class="text-navy-400" />
        <span class="tabular">{{ now()->translatedFormat('l, j F Y') }}</span>
    </p>
</header>

{{-- Lima kartu sejajar mulai layar lebar. Kartu yang bisa ditindaklanjuti
     menjadi tautan, yang sekadar kabar tidak. --}}
<div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
    <x-kartu-angka warna="biru" ikon="keranjang" label="Pesanan masuk hari ini" :angka="$pesananHariIni"
                   :href="$bolehPesanan ? route('panel.pesanan.index', ['dari' => now()->toDateString()]) : null" />
    <x-kartu-angka warna="hijau" ikon="dompet" label="Uang diterima hari ini" :angka="rupiah_ringkas($uangHariIni)"
                   :href="$u->punyaIzin('laporan.lihat') ? route('panel.laporan') : null" />
    <x-kartu-angka warna="jingga" ikon="kunci-pas" label="Sedang dikerjakan" :angka="$sedangDikerjakan"
                   :href="$bolehPesanan ? route('panel.pesanan.index', ['status' => 'DIPROSES']) : null" />
    {{-- Barang sudah jadi tapi uangnya belum (KEP-028). Ditaruh di antara
         "dikerjakan" dan "siap" karena memang di situlah ia berada. --}}
    <x-kartu-angka warna="ungu" ikon="stok" label="Menunggu pelunasan" :angka="$menungguPelunasan"
                   :href="$bolehPesanan ? route('panel.pesanan.index', ['status' => 'MENUNGGU_PELUNASAN']) : null" />
    <x-kartu-angka warna="pink" ikon="kirim" label="Siap diambil/kirim" :angka="$siapDiambil"
                   :href="$bolehPesanan ? route('panel.pesanan.index', ['status' => 'SIAP_DIAMBIL']) : null"
                   class="col-span-2 md:col-span-1" />
</div>

<div class="mt-5 grid items-start gap-4 xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
    @if ($bolehPesanan)
        <section class="kartu min-w-0 overflow-hidden">
            <div class="kartu-kop flex items-center gap-2.5">
                <span class="lencana-bagian warna-biru"><x-ikon nama="pesanan" ukuran="size-4" /></span>
                <h2>Pesanan terbaru</h2>
                <a href="{{ route('panel.pesanan.index') }}" class="tautan-lihat ml-auto">Lihat semua <x-ikon nama="lanjut" ukuran="size-4" /></a>
            </div>
            @if ($pesananTerbaru->isEmpty())
                <p class="p-5 text-sm text-muted">Belum ada pesanan. Pesanan dari web dan yang diketik admin muncul di sini.</p>
            @else
                <ul class="divide-y divide-line text-sm md:hidden">
                    @foreach ($pesananTerbaru as $o)
                        <li>
                            <a href="{{ route('panel.pesanan.show', $o) }}" class="flex items-center justify-between gap-3 px-5 py-3 hover:bg-navy-50">
                                <span class="min-w-0">
                                    <span class="tabular block font-semibold text-navy-700">{{ $o->kode }}</span>
                                    <span class="text-muted">{{ $o->customer->nama }}</span>
                                </span>
                                <span class="chip {{ $o->status->warna() }}">{{ $o->status->label() }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
                {{-- Kartu ini berbagi lebar dengan kartu stok mulai 80rem, jadi
                     produk menumpang di bawah nama pelanggan dan tanggal baru
                     tampil kalau muat (KEP-143). Pembungkus geser hanya jaring
                     pengaman untuk zoom ekstrem; tabel ini tanpa menu tarik. --}}
                <div class="hidden overflow-x-auto md:block">
                <table class="tabel">
                    <thead>
                        <tr>
                            <th>No. pesanan</th>
                            <th>Pelanggan</th>
                            <th>Status</th>
                            <th class="xl:hidden 2xl:table-cell">Tanggal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pesananTerbaru as $o)
                            @php $item = $o->items->first(); @endphp
                            <tr class="hover:bg-navy-50" data-tautan="{{ route('panel.pesanan.show', $o) }}">
                                <td class="whitespace-nowrap"><a href="{{ route('panel.pesanan.show', $o) }}" class="tabular font-semibold text-navy-700">{{ $o->kode }}</a></td>
                                {{-- Nama dan produk dipotong satu baris: baris setinggi lima
                                     baris teks membuat lima pesanan terbaru memakan satu layar.
                                     w-full max-w-0 membuat kolom ini yang mengalah, bukan tabelnya. --}}
                                <td class="w-full max-w-0">
                                    <span class="block truncate font-medium" title="{{ $o->customer->nama }}">{{ $o->customer->nama }}</span>
                                    <span class="block truncate text-xs text-muted" title="{{ $item?->nama_produk }}">
                                        {{ $item?->nama_produk ?? '-' }}@if ($item)<span class="tabular"> · {{ $o->items->sum('qty') }} pcs</span>@endif
                                    </span>
                                </td>
                                <td><span class="chip {{ $o->status->warna() }}">{{ $o->status->label() }}</span></td>
                                <td class="tabular whitespace-nowrap text-muted xl:hidden 2xl:table-cell">{{ $o->created_at->translatedFormat('j M Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            @endif
        </section>
    @endif

    <section class="kartu min-w-0 overflow-hidden">
        <div class="kartu-kop flex items-center gap-2.5">
            <span class="lencana-bagian warna-jingga"><x-ikon nama="stok" ukuran="size-4" /></span>
            <h2>Stok perlu diisi</h2>
            @if ($u->punyaIzin('stok.kelola'))
                <a href="{{ route('panel.stok.index') }}" class="tautan-lihat ml-auto">Lihat semua <x-ikon nama="lanjut" ukuran="size-4" /></a>
            @endif
        </div>
        @if ($stokPerluDiisi->isEmpty())
            <p class="flex items-center gap-2 p-5 text-sm text-muted">
                <x-ikon nama="centang" ukuran="size-4" class="text-ok" />
                Stok aman. Tidak ada barang yang habis atau tinggal tiga.
            </p>
        @else
            {{-- Status tetap di kanan: dulu flex-wrap menjatuhkannya ke bawah dan
                 rata kiri di HP, sehingga satu baris barang pecah jadi tiga. --}}
            <ul class="divide-y divide-line text-sm">
                @foreach ($stokPerluDiisi as $v)
                    @php $gambar = $v->product->images->first(); @endphp
                    <li class="flex items-center gap-3 px-5 py-2.5">
                        @if ($gambar)
                            <img src="{{ $gambar->thumb_url }}" alt="" width="40" height="40" loading="lazy"
                                 class="size-10 shrink-0 rounded-[8px] border border-line object-cover">
                        @else
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-[8px] border border-dashed border-line-strong text-navy-300">
                                <x-ikon nama="gambar" ukuran="size-4" />
                            </span>
                        @endif
                        <span class="min-w-0 flex-1">{{ $v->product->nama }} <span class="text-muted">{{ $v->label }}</span></span>
                        <span class="chip shrink-0 {{ $v->stok_tersedia <= 0 ? 'chip-merah' : 'chip-jingga' }}">
                            Stok: <span class="tabular">{{ max(0, $v->stok_tersedia) }}</span>
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>

<div class="mt-4 grid items-start gap-4 xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
    {{-- Antrean konveksi dihitung per pesanan, bukan per barang: pemilik toko
         bertanya "berapa pesanan yang masih di desain", bukan berapa baris. --}}
    <section class="kartu min-w-0 overflow-hidden">
        <div class="kartu-kop flex items-center gap-2.5">
            <span class="lencana-bagian warna-ungu"><x-ikon nama="kaos" ukuran="size-4" /></span>
            <h2>Antrean produksi jersey</h2>
            @if (! empty($u->tahapProduksi()))
                <a href="{{ route('panel.antrian.index') }}" class="tautan-lihat ml-auto">Lihat semua <x-ikon nama="lanjut" ukuran="size-4" /></a>
            @endif
        </div>
        <div class="grid gap-2 p-3 min-[22.5rem]:grid-cols-3 sm:gap-3 sm:p-4">
            @foreach ([
                ['ungu', 'daftar', 'Menunggu desain', $antreanJersey['desain']],
                ['jingga', 'pengaturan', 'Sedang produksi', $antreanJersey['produksi']],
                ['pink', 'kirim', 'Siap diambil / kirim', $antreanJersey['siap']],
            ] as [$warna, $ikon, $label, $angka])
                <x-kartu-angka :warna="$warna" :ikon="$ikon" :label="$label" :angka="$angka" class="kartu-angka-ringkas" />
            @endforeach
        </div>
    </section>

    @php
        $pintas = array_filter([
            $u->punyaIzin('order.kelola') ? ['biru', 'keranjang', 'Tambah pesanan', route('panel.pesanan.baru')] : null,
            $u->punyaIzin('katalog.kelola') ? ['hijau', 'tambah', 'Tambah produk', route('panel.produk.create')] : null,
            $u->punyaIzin('stok.kelola') ? ['jingga', 'stok', 'Stok opname', route('panel.stok.index')] : null,
            $u->punyaIzin('laporan.lihat') ? ['ungu', 'laporan', 'Laporan penjualan', route('panel.laporan')] : null,
        ]);
    @endphp
    @if ($pintas)
        <section class="kartu min-w-0 overflow-hidden">
            <div class="kartu-kop flex items-center gap-2.5">
                <span class="lencana-bagian warna-kuning"><x-ikon nama="petir" ukuran="size-4" /></span>
                <h2>Jalan pintas</h2>
            </div>
            <div class="grid grid-cols-2 gap-3 p-4">
                @foreach ($pintas as [$warna, $ikon, $label, $url])
                    <a href="{{ $url }}" class="pintas warna-{{ $warna }}">
                        <x-ikon :nama="$ikon" />
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</div>

<div class="mt-8 flex flex-wrap items-center justify-between gap-3">
    <h2 class="text-lg font-extrabold">Yang perlu ditangani</h2>

    {{-- Jalan pintas ke daftar pesanan yang menunggu orang toko. Kartu-kartu di
         bawah menyebut masalahnya satu per satu; tautan ini untuk yang ingin
         mengerjakannya berurutan dalam satu daftar. --}}
    @if ($bolehPesanan)
        <a href="{{ route('panel.pesanan.index', ['status' => 'tindakan']) }}"
           class="tombol tombol-garis tombol-sm">
            <x-ikon nama="pesanan" ukuran="size-4" />
            Pesanan yang menunggu Anda
            <x-ikon nama="lanjut" ukuran="size-4" />
        </a>
    @endif
</div>

@foreach ($masalahSistem as $m)
    <section class="notis notis-galat mt-4">
        <p class="font-bold">{{ $m['judul'] }}</p>
        <p class="kolom-baca mt-1">{{ $m['isi'] }}</p>
    </section>
@endforeach

{{-- Dua kolom di layar lebar. Baris yang meregang sampai seribu piksel memaksa
     mata berjalan jauh dari nama barang ke angkanya. --}}
<div class="mt-4 grid items-start gap-3 xl:grid-cols-2">
    {{-- Pengajuan pembatalan (KEP-041) didahulukan: selama menggantung,
         konveksi mengerjakan kain yang mungkin tidak jadi dipakai. --}}
    @if ($pembatalanMenunggu->isNotEmpty())
        <section class="kartu overflow-hidden">
            <div class="kartu-kop flex items-center gap-2">
                <span class="tahap-titik tahap-titik-kini"></span>
                Pengajuan pembatalan
                <span class="chip chip-merah ml-auto">{{ $pembatalanMenunggu->count() }}</span>
            </div>
            <p class="border-b border-line px-5 py-2.5 text-sm text-muted">
                Pekerjaan tetap berjalan sampai Anda memutuskan.
            </p>
            <ul class="divide-y divide-line text-sm">
                @foreach ($pembatalanMenunggu as $o)
                    <li>
                        <a href="{{ route('panel.pesanan.show', $o) }}"
                           class="flex items-center justify-between gap-4 px-5 py-2.5 hover:bg-navy-50">
                            <span class="tabular font-medium">{{ $o->kode }}</span>
                            <span class="text-muted">{{ $o->customer->nama }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($bolehHangus->isNotEmpty())
        <section class="kartu overflow-hidden">
            <div class="kartu-kop flex items-center gap-2">
                <span class="tahap-titik tahap-titik-kini"></span>
                Lewat batas pengambilan
                <span class="chip chip-merah ml-auto">{{ $bolehHangus->count() }}</span>
            </div>
            <p class="border-b border-line px-5 py-2.5 text-sm text-muted">
                Lebih dari {{ $hangusHari }} hari belum diambil. Hubungi dulu sebelum dihanguskan.
            </p>
            <ul class="divide-y divide-line text-sm">
                @foreach ($bolehHangus as $o)
                    <li>
                        <a href="{{ route('panel.pesanan.show', $o) }}"
                           class="flex items-center justify-between gap-4 px-5 py-2.5 hover:bg-navy-50">
                            <span class="tabular font-medium">{{ $o->kode }}</span>
                            <span class="text-muted">{{ $o->customer->nama }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($perluVerifikasi->isNotEmpty())
        <section class="kartu overflow-hidden">
            <div class="kartu-kop flex items-center gap-2">
                <span class="tahap-titik tahap-titik-kini"></span>
                Bukti transfer menunggu diperiksa
                <span class="chip chip-merah ml-auto">{{ $perluVerifikasi->count() }}</span>
            </div>
            <p class="border-b border-line px-5 py-2.5 text-sm text-muted">
                Pesanan belum dikerjakan sampai ini diverifikasi.
            </p>
            <ul class="divide-y divide-line text-sm">
                @foreach ($perluVerifikasi as $o)
                    <li>
                        <a href="{{ route('panel.pesanan.show', $o) }}"
                           class="flex items-center justify-between gap-4 px-5 py-2.5 hover:bg-navy-50">
                            <span class="tabular font-medium">{{ $o->kode }}</span>
                            <span class="text-muted">{{ $o->customer->nama }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Siap, tetapi yang ditunggu resi dari toko sendiri (KEP-098). --}}
    @if ($siapDikirim->isNotEmpty())
        <section class="kartu overflow-hidden">
            <div class="kartu-kop flex items-center gap-2">
                <span class="tahap-titik tahap-titik-kini"></span>
                Siap dikirim
                <span class="chip chip-merah ml-auto">{{ $siapDikirim->count() }}</span>
            </div>
            <p class="border-b border-line px-5 py-2.5 text-sm text-muted">
                Antar ke kurir, lalu catat resinya di halaman pesanan. Yang masih bersisa
                menunggu pelanggan melunasi lewat halaman pesanannya.
            </p>
            <ul class="divide-y divide-line text-sm">
                @foreach ($siapDikirim as $o)
                    <li>
                        <a href="{{ route('panel.pesanan.show', $o) }}#pengambilan"
                           class="flex items-start justify-between gap-4 px-5 py-2.5 hover:bg-navy-50">
                            <span class="tabular shrink-0 font-medium">{{ $o->kode }}</span>
                            <span class="min-w-0 text-right text-muted">
                                {{ $o->customer->nama }}{{ $o->sisa_tagihan > 0 ? ', sisa '.rupiah($o->sisa_tagihan) : '' }}
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Hanya pelanggan yang bisa menyetujui, jadi yang bisa dilakukan toko
         cuma mengingatkan (KEP-098). --}}
    @if ($persetujuanLama->isNotEmpty())
        <section class="kartu overflow-hidden">
            <div class="kartu-kop flex items-center gap-2">
                Desain menunggu persetujuan lebih dari 2 hari
                <span class="chip chip-kuning ml-auto">{{ $persetujuanLama->count() }}</span>
            </div>
            <p class="border-b border-line px-5 py-2.5 text-sm text-muted">
                Produksi belum bisa mulai. Ingatkan pelanggannya lewat tombol WhatsApp di halaman pesanan.
            </p>
            <ul class="divide-y divide-line text-sm">
                @foreach ($persetujuanLama as $o)
                    <li>
                        <a href="{{ route('panel.pesanan.show', $o) }}#pelanggan"
                           class="flex items-center justify-between gap-4 px-5 py-2.5 hover:bg-navy-50">
                            <span class="tabular font-medium">{{ $o->kode }}</span>
                            <span class="text-muted">{{ $o->customer->nama }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($lamaBelumDiambil->isNotEmpty())
        <section class="kartu overflow-hidden">
            <div class="kartu-kop flex items-center gap-2">
                Siap tapi belum diambil lebih dari {{ $peringatanHari }} hari
                <span class="chip chip-kuning ml-auto">{{ $lamaBelumDiambil->count() }}</span>
            </div>
            <p class="border-b border-line px-5 py-2.5 text-sm text-muted">
                Barang ini memakan rak toko. Hubungi pelanggannya.
            </p>
            <ul class="divide-y divide-line text-sm">
                @foreach ($lamaBelumDiambil as $o)
                    <li>
                        <a href="{{ route('panel.pesanan.show', $o) }}"
                           class="flex items-start justify-between gap-4 px-5 py-2.5 hover:bg-navy-50">
                            <span class="tabular shrink-0 font-medium">{{ $o->kode }}</span>
                            <span class="min-w-0 text-right text-muted">
                                {{ $o->customer->nama }}, siap {{ $o->siap_pada?->diffForHumans(short: true) }}
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Pesanan dikirim baru tertutup kalau ada yang menandai (KEP-026). --}}
    @if ($paketPerluDitutup->isNotEmpty())
        <section class="kartu overflow-hidden">
            <div class="kartu-kop flex items-center gap-2">
                Paket perlu dipastikan
                <span class="chip chip-kuning ml-auto">{{ $paketPerluDitutup->count() }}</span>
            </div>
            <p class="border-b border-line px-5 py-2.5 text-sm text-muted">
                Menurut kurir sudah sampai, atau sudah dua minggu di jalan. Tanyakan ke pelanggan,
                lalu tandai diterima.
            </p>
            <ul class="divide-y divide-line text-sm">
                @foreach ($paketPerluDitutup as $o)
                    <li>
                        <a href="{{ route('panel.pesanan.show', $o) }}#pengambilan"
                           class="flex items-start justify-between gap-4 px-5 py-2.5 hover:bg-navy-50">
                            <span class="tabular shrink-0 font-medium">{{ $o->kode }}</span>
                            <span class="min-w-0 text-right text-muted">
                                {{ $o->customer->nama }}, {{ $o->paketSampai() ? 'kata kurir sudah sampai' : 'dikirim '.$o->dikirim_pada?->diffForHumans(short: true) }}
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($tanpaTier->isNotEmpty())
        <section class="kartu overflow-hidden">
            <div class="kartu-kop">Harga belum lengkap</div>
            <p class="border-b border-line px-5 py-2.5 text-sm text-muted">
                Produk kustom ini tayang tapi belum punya tier harga, jadi pelanggan
                tidak bisa menghitung biayanya sendiri dan akan bertanya ke admin.
            </p>
            <ul class="divide-y divide-line text-sm">
                @foreach ($tanpaTier as $p)
                    <li>
                        <a href="{{ route('panel.produk.edit', $p) }}"
                           class="flex items-center justify-between gap-4 px-5 py-2.5 hover:bg-navy-50">
                            <span class="font-medium">{{ $p->nama }}</span>
                            <span class="tabular text-muted">{{ $p->sku }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($emailBermasalah->isNotEmpty())
        <section class="kartu overflow-hidden">
            <div class="kartu-kop">Email tidak sampai</div>
            <p class="border-b border-line px-5 py-2.5 text-sm text-muted">
                Kabari pelanggannya lewat WhatsApp, atau kirim ulang emailnya dari halaman pesanan.
                Tandai sudah ditangani di sana supaya hilang dari daftar ini.
            </p>
            <ul class="divide-y divide-line text-sm">
                @foreach ($emailBermasalah as $log)
                    <li class="flex items-start justify-between gap-4 px-5 py-2.5">
                        @if ($log->order)
                            <a href="{{ route('panel.pesanan.show', $log->order) }}"
                               class="tautan tabular font-medium">{{ $log->order->kode }}</a>
                        @else
                            <span class="tabular text-muted">tanpa pesanan</span>
                        @endif
                        <span class="min-w-0 text-right text-muted wrap-break-word">{{ $log->ke }}, {{ $log->status->label() }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if (empty($masalahSistem) && $tanpaTier->isEmpty() && $stokHabis->isEmpty() && $stokMenipis->isEmpty()
         && $perluVerifikasi->isEmpty() && $lamaBelumDiambil->isEmpty() && $emailBermasalah->isEmpty()
         && $pembatalanMenunggu->isEmpty() && $bolehHangus->isEmpty() && $siapDikirim->isEmpty()
         && $persetujuanLama->isEmpty() && $paketPerluDitutup->isEmpty())
        <section class="kartu p-8 text-center xl:col-span-2">
            <x-ikon nama="centang" ukuran="size-9" class="mx-auto text-ok" />
            <p class="mt-3 text-lg font-bold">Tidak ada yang perlu ditangani</p>
            <p class="kolom-baca mx-auto mt-2 text-sm text-muted">
                Harga lengkap, stok aman, dan semua email sampai. Waktu yang baik untuk
                menambah produk atau merapikan katalog.
            </p>
            @if (auth('web')->user()->punyaIzin('katalog.kelola'))
                <a href="{{ route('panel.produk.create') }}" class="tombol tombol-navy mt-5">Tambah produk</a>
            @endif
        </section>
    @endif
</div>

<p class="tabular mt-8 text-sm text-muted">
    Katalog: {{ $produkAktif }} produk tayang, {{ $produkNonaktif }} disembunyikan,
    {{ $tanpaFoto }} tayang tanpa foto.
</p>
@endsection
