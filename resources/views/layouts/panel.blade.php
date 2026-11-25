<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="batas-unggah-mb" content="{{ batas_unggah_mb() }}">
    {{-- "Panel" ikut di akhiran: tanpa itu tab "Pesanan SS-..." di panel sama
         persis dengan tab halaman pelacakan pelanggan, dan tab ubah produk sama
         dengan halaman produknya di etalase. Staf sering membuka keduanya. --}}
    <title>@yield('judul', 'Dashboard') | Panel {{ setting('toko_nama', 'Setia Sport') }}</title>
    <meta name="theme-color" content="#0d1733">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <meta name="robots" content="noindex">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- Sidebar yang dikuncupkan diingat per perangkat. Dipasang sebelum halaman
         tergambar, supaya sidebar tidak sempat melebar lalu menyempit. --}}
    <script nonce="{{ Vite::cspNonce() }}">
        try {
            if (localStorage.getItem('panel-sidebar') === 'tutup') document.documentElement.classList.add('sidebar-tutup');
        } catch (e) {}
    </script>
</head>
{{-- Kerangka dari revisi klien (KEP-111): sidebar navy gelap setinggi layar,
     bilah atas putih, ruang kerja terang. Panel tetap alat kerja: warna
     dipakai untuk membedakan bagian, bukan untuk menghias. --}}
{{-- Satu tombol, dua perilaku: di layar lebar menguncupkan sidebar jadi rail
     ikon supaya tabel lebar dapat ruang; di HP membuka menu sebagai laci. --}}
<body class="min-h-screen bg-paper"
      x-data="{
          menu: false,
          lebar: window.matchMedia('(min-width: 64rem)').matches,
          tutup: document.documentElement.classList.contains('sidebar-tutup'),
          alihMenu() {
              if (! this.lebar) {
                  this.menu = ! this.menu;
                  return;
              }
              this.tutup = ! this.tutup;
              document.documentElement.classList.toggle('sidebar-tutup', this.tutup);
              try { localStorage.setItem('panel-sidebar', this.tutup ? 'tutup' : 'buka'); } catch (e) {}
          },
      }"
      x-init="window.matchMedia('(min-width: 64rem)').addEventListener('change', (e) => { lebar = e.matches; menu = false; })"
      @keydown.escape.window="menu = false"
      :class="{ 'overflow-hidden': menu }">

{{-- Pengguna papan ketik melompati sidebar dan bilah atas (WCAG 2.4.1). --}}
<a href="#konten" class="lewati-ke-konten">Lewati ke konten</a>

@php
    $u = auth('web')->user();

    /*
     * Menu dikelompokkan seperti gambar contoh klien (KEP-111): pekerjaan
     * harian di atas tanpa judul, lalu Katalog, Laporan, dan Lainnya. Customer
     * Service tepat di bawah Pesanan, di atas menu kerja karyawan (KEP-149),
     * seperti gambar Ruang CS klien. Menu yang
     * tidak ada di gambar (Pengguna, Pekerja konveksi, Kesiapan, Riwayat
     * aktivitas) tetap ada di Lainnya: membuangnya berarti fiturnya hilang.
     *
     * [rute, label, izin, pola aktif, penghitung, ikon]
     */
    $grup = [
        '' => [
            ['panel.dashboard', 'Dashboard', null, 'panel.dashboard', null, 'dashboard'],
            ['panel.pesanan.index', 'Pesanan', 'order.lihat', 'panel.pesanan.*', $jumlahPesananAktif ?? null, 'keranjang'],
            ...(($csTampilMenu ?? true) ? [['panel.cs.index', cs_aktif() ? 'Customer Service' : 'Arsip chat', 'cs.balas', 'panel.cs.*', null, 'chat']] : []),
            ['panel.antrian.index', 'Antrian kerja', null, 'panel.antrian.*', $jumlahAntrian ?? null, 'antrian'],
            ['panel.serah-terima', 'Serah terima', 'serah_terima.proses', 'panel.serah-terima', $jumlahSiapDiambil ?? null, 'serah-terima'],
        ],
        'Katalog' => [
            ['panel.produk.index', 'Produk', 'katalog.kelola', 'panel.produk.*', null, 'produk'],
            ['panel.kategori.index', 'Kategori', 'katalog.kelola', 'panel.kategori.*', null, 'kategori'],
            ['panel.bahan.index', 'Bahan', 'katalog.kelola', 'panel.bahan.*', null, 'bahan'],
            ['panel.stok.index', 'Stok', 'stok.kelola', 'panel.stok.*', null, 'stok'],
        ],
        'Laporan' => [
            ['panel.laporan', 'Penjualan', 'laporan.lihat', 'panel.laporan', null, 'laporan'],
            ['panel.laporan.stok', 'Stok barang', 'laporan.lihat', 'panel.laporan.stok', null, 'daftar'],
            ['panel.laporan.terlaris', 'Produk terlaris', 'laporan.lihat', 'panel.laporan.terlaris', null, 'keranjang'],
        ],
        'Lainnya' => [
            ['panel.banner.index', 'Banner beranda', 'profil.kelola', 'panel.banner.*', null, 'gambar'],
            ['panel.portofolio.index', 'Portofolio', 'profil.kelola', 'panel.portofolio.*', null, 'portofolio'],
            ['panel.testimoni.index', 'Testimoni', 'profil.kelola', 'panel.testimoni.*', null, 'testimoni'],
            ...(cs_aktif() ? [['panel.faq.index', 'FAQ chatbot', 'cs.balas', 'panel.faq.*', null, 'bantuan']] : []),
            ['panel.pelanggan.index', 'Pelanggan', 'pelanggan.kelola', 'panel.pelanggan.*', null, 'orang'],
            ['panel.pengguna.index', 'Pengguna', 'pengguna.kelola', 'panel.pengguna.*', null, 'orang'],
            ['panel.pekerja.index', 'Pekerja konveksi', 'pengguna.kelola', 'panel.pekerja.*', null, 'pekerja'],
            ['panel.kesiapan', 'Kesiapan tayang', 'pengaturan.kelola', 'panel.kesiapan', null, 'centang'],
            ['panel.aktivitas', 'Riwayat aktivitas', 'pengaturan.kelola', 'panel.aktivitas', null, 'jam'],
            ['panel.pengaturan.index', 'Pengaturan', 'pengaturan.kelola', 'panel.pengaturan.*', null, 'pengaturan'],
        ],
    ];

    /*
     * Tautan yang boleh dilihat pengguna ini, dipakai dua kali: di menu samping
     * dan di bilah bawah layar sentuh. Disaring sekali di sini supaya keduanya
     * tidak bisa berselisih soal siapa boleh melihat apa.
     */
    $bolehLihat = function (array $t) use ($u) {
        if ($t[2] && ! $u->punyaIzin($t[2])) {
            return false;
        }

        return ! ($t[0] === 'panel.antrian.index' && empty($u->tahapProduksi()));
    };

    // Empat tujuan tersering untuk bilah bawah. Sisanya lewat laci menu: bilah
    // bawah yang memuat sepuluh ikon sama sulitnya dengan tidak ada bilah.
    $navBawah = collect($grup[''])->filter($bolehLihat)->take(4)->values();

    $lonceng ??= [];
    $jumlahLonceng = array_sum(array_column($lonceng, 'jumlah'));
    $inisial = Str::of($u->name)->explode(' ')->take(2)->map(fn ($k) => Str::substr($k, 0, 1))->implode('');

    $csNotif = ($csBelumDibaca ?? null) !== null ? [
        'belumDibaca' => $csBelumDibaca,
        'realtime' => config('broadcasting.default') === 'pusher' ? config('broadcasting.klien') : null,
        'url' => ['auth' => route('panel.cs.auth'), 'ringkasan' => route('panel.cs.ringkasan')],
    ] : null;
@endphp

{{-- Chat yang tidak dijawab lebih buruk daripada tidak ada chat (KEP-030).
     Sambungannya dipasang di SEMUA halaman panel, supaya admin yang sedang
     mengurus stok tetap mendengar bunyi saat pelanggan menulis. --}}
<div @if ($csNotif) x-data="notifikasiCs(@js($csNotif))" @endif>

{{-- Latar gelap di belakang laci menu HP; mengetuknya menutup laci. --}}
<div x-show="menu" x-transition.opacity x-cloak @click="menu = false"
     class="fixed inset-0 z-40 bg-navy-900/50 lg:hidden" aria-hidden="true"></div>

{{-- Layar lebar: kolom gelap setinggi layar yang tidak ikut digulir, jadi menu
     tidak pernah hilang di halaman panjang. HP: laci dari kiri. --}}
<aside id="sidebar-panel" class="sidebar-panel" :class="{ 'terbuka': menu }">
    <div class="sidebar-kepala">
        <a href="{{ route('panel.dashboard') }}" class="sidebar-merek">
            <x-logo :terang="true" tinggi="h-10" />
            <span class="sidebar-sebutan">Panel Admin</span>
        </a>
        <img src="{{ asset('favicon.png') }}" alt="" width="32" height="32" class="sidebar-merek-kecil">
        <button type="button" @click="menu = false" class="sidebar-tutup-laci" aria-label="Tutup menu">
            <x-ikon nama="silang" />
        </button>
    </div>

    <nav class="sidebar-menu" aria-label="Navigasi panel">
        @foreach ($grup as $namaGrup => $tautan)
            @php $terlihat = collect($tautan)->filter($bolehLihat); @endphp
            @continue($terlihat->isEmpty())

            @if ($namaGrup !== '')
                <p class="grup-menu">{{ $namaGrup }}</p>
            @endif

            @foreach ($terlihat as [$rute, $label, $izin, $pola, $jumlah, $ikon])
                @php $aktif = request()->routeIs($pola); @endphp
                <a href="{{ route($rute) }}" @click="menu = false" title="{{ $label }}"
                   @if ($aktif) aria-current="page" @endif
                   class="menu-panel {{ $aktif ? 'menu-panel-aktif' : '' }}">
                    <x-ikon :nama="$ikon" />
                    <span class="menu-teks">{{ $label }}</span>
                    @if ($rute === 'panel.cs.index' && $csNotif)
                        <span x-show="jumlah > 0" x-text="jumlah" @if (! count($csNotif['belumDibaca'])) x-cloak @endif
                              class="lencana-menu">{{ count($csNotif['belumDibaca']) }}</span>
                    @elseif ($jumlah)
                        <span class="lencana-menu">{{ $jumlah }}</span>
                    @endif
                </a>
            @endforeach
        @endforeach

    </nav>

    {{-- Pusat Bantuan seperti gambar klien (KEP-150), di dasar sidebar dan di
         laci HP (dulu tautan "Bantuan" khusus laci). Disembunyikan di mode rail. --}}
    <div class="sidebar-bantuan">
        <p class="flex items-center gap-2 font-semibold text-white">
            <x-ikon nama="bantuan" ukuran="size-5" /> Pusat Bantuan
        </p>
        <p class="mt-1 text-xs text-white/70">Panduan penggunaan sistem admin.</p>
        <a href="{{ route('panel.bantuan') }}" @click="menu = false"
           class="mt-3 block rounded-[8px] border border-white/25 px-3 py-1.5 text-center text-sm font-semibold text-white hover:bg-white/10">
            Lihat Panduan
        </a>
    </div>
</aside>

<div class="panel-utama">
    <header class="bilah-atas">
        <button type="button" @click="alihMenu()" aria-controls="sidebar-panel"
                class="tombol-bilah -ml-1.5"
                :aria-expanded="(lebar ? ! tutup : menu).toString()"
                :aria-label="lebar ? (tutup ? 'Bentangkan menu samping' : 'Kuncupkan menu samping') : (menu ? 'Tutup menu' : 'Buka menu')"
                :title="lebar ? (tutup ? 'Bentangkan menu samping' : 'Kuncupkan menu samping') : null"
                aria-label="Menu">
            <x-ikon nama="menu" />
        </button>

        {{-- Di HP sidebar tersembunyi, jadi logo pindah ke bilah atas. --}}
        <a href="{{ route('panel.dashboard') }}" class="shrink-0 lg:hidden">
            <x-logo tinggi="h-7" />
        </a>

        {{-- Cari untuk seluruh panel (KEP-111). Kode yang tepat langsung
             membuka pesanannya; selain itu hasilnya dikelompokkan. --}}
        <form method="GET" action="{{ route('panel.cari') }}" role="search"
              class="relative hidden max-w-md flex-1 md:block">
            <label for="cari-panel" class="sr-only">Cari pesanan, produk, atau pelanggan</label>
            <x-ikon nama="cari" ukuran="size-4"
                    class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-navy-300" />
            <input type="search" id="cari-panel" name="q"
                   value="{{ request()->routeIs('panel.cari') ? request('q') : '' }}"
                   placeholder="Cari pesanan, produk, atau pelanggan&hellip;"
                   class="kotak-input kotak-cari-atas pl-9">
        </form>

        <div class="ml-auto flex items-center gap-1 text-sm">
            <a href="{{ route('panel.cari') }}" class="tombol-bilah md:hidden" title="Cari">
                <x-ikon nama="cari" />
                <span class="sr-only">Cari</span>
            </a>

            @if ($csNotif)
                <a href="{{ route('panel.cs.index') }}" x-show="jumlah > 0" x-cloak
                   class="flex items-center gap-1.5 rounded-[7px] bg-merah px-2.5 py-1.5 font-semibold text-white hover:bg-merah/90">
                    <x-ikon nama="chat" ukuran="size-4" />
                    <span class="sr-only sm:not-sr-only">Chat baru</span>
                    <span class="tabular" x-text="jumlah"></span>
                </a>
            @endif

            {{-- Lonceng: daftar "perlu ditangani" yang sama dengan dashboard,
                 dari halaman mana pun. Tanpa status "sudah dibaca": isinya hilang
                 sendiri begitu urusannya ditangani. --}}
            <details class="relative" data-lonceng
                     x-data @click.outside="$el.removeAttribute('open')" @keydown.escape="$el.removeAttribute('open')">
                <summary class="tombol-bilah relative" title="Perlu ditangani">
                    <x-ikon nama="lonceng" />
                    <span class="sr-only">Perlu ditangani: {{ $jumlahLonceng }}</span>
                    @if ($jumlahLonceng > 0)
                        <span class="titik-lonceng" aria-hidden="true"></span>
                    @endif
                </summary>
                <div class="menu-tarik lonceng-isi sm:right-0 sm:w-80">
                    <p class="border-b border-line px-4 py-3 font-semibold">Perlu ditangani</p>
                    @if ($lonceng === [])
                        <p class="px-4 py-5 text-sm text-muted">Tidak ada yang menunggu Anda. Toko sedang sehat.</p>
                    @else
                        <ul class="max-h-96 divide-y divide-line overflow-y-auto text-sm">
                            @foreach ($lonceng as $l)
                                <li>
                                    <a href="{{ $l['url'] }}" class="flex items-center gap-3 px-4 py-2.5 hover:bg-navy-50">
                                        <span class="size-2 shrink-0 rounded-full {{ $l['mendesak'] ? 'bg-merah' : 'bg-kuning' }}"></span>
                                        <span class="min-w-0 flex-1">{{ $l['label'] }}</span>
                                        <span class="tabular font-semibold">{{ $l['jumlah'] }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    <a href="{{ route('panel.dashboard') }}" class="block border-t border-line px-4 py-2.5 text-sm font-semibold text-navy-700 hover:bg-navy-50">
                        Buka dashboard
                    </a>
                </div>
            </details>

            {{-- Bantuan berdiri sendiri di bilah atas, tidak disembunyikan di
                 dalam menu: yang sedang bingung tidak akan menemukannya kalau
                 harus menebak dulu di mana letaknya. --}}
            <a href="{{ route('panel.bantuan') }}" title="Bantuan"
               class="tombol-bilah {{ request()->routeIs('panel.bantuan') ? 'bg-navy-50 text-ink' : '' }}">
                <x-ikon nama="bantuan" />
                <span class="sr-only lg:not-sr-only">Bantuan</span>
            </a>

            {{-- Identitas, "lihat toko", dan keluar dirangkum ke satu menu. --}}
            <div class="relative border-line sm:ml-1 sm:border-l sm:pl-2" x-data="{ buka: false }" @keydown.escape="buka = false">
                <button type="button" @click="buka = ! buka" :aria-expanded="buka.toString()"
                        class="flex items-center gap-2 rounded-[8px] py-1 pr-1.5 pl-1 hover:bg-navy-50">
                    <span class="flex size-9 items-center justify-center rounded-full bg-navy-100 text-xs font-bold text-navy-800">
                        {{ $inisial }}
                    </span>
                    <span class="hidden text-left leading-tight sm:block">
                        <span class="block font-semibold">{{ $u->name }}</span>
                        <span class="block text-xs text-muted">{{ $u->roles->pluck('nama')->implode(', ') }}</span>
                    </span>
                    <x-ikon nama="turun" ukuran="size-4" class="hidden text-muted sm:block" />
                </button>

                <div x-show="buka" x-cloak x-transition.opacity @click.outside="buka = false" class="menu-tarik right-0 w-60">
                    <div class="border-b border-line px-4 py-3 sm:hidden">
                        <p class="font-semibold">{{ $u->name }}</p>
                        <p class="text-xs text-muted">{{ $u->roles->pluck('nama')->implode(', ') }}</p>
                    </div>
                    <a href="{{ route('panel.akun') }}" class="flex items-center gap-2.5 px-4 py-2.5 text-sm hover:bg-navy-50">
                        <x-ikon nama="orang" ukuran="size-4" class="text-navy-400" />
                        Akun saya
                    </a>
                    <a href="{{ route('beranda') }}" target="_blank" rel="noopener"
                       class="flex items-center gap-2.5 px-4 py-2.5 text-sm hover:bg-navy-50">
                        <x-ikon nama="buka-luar" ukuran="size-4" class="text-navy-400" />
                        Lihat toko
                    </a>
                    <form method="POST" action="{{ route('panel.keluar') }}" class="border-t border-line">
                        @csrf
                        <button type="submit"
                                class="flex w-full items-center gap-2.5 px-4 py-2.5 text-left text-sm hover:bg-navy-50">
                            <x-ikon nama="keluar" ukuran="size-4" class="text-navy-400" />
                            Keluar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <main id="konten" tabindex="-1" class="ruang-kerja">
        <div class="kolom-kerja">
            @if (session('sukses'))
                <p class="notis notis-ok mb-5">{{ session('sukses') }}</p>
            @endif

            @if (session('peringatan'))
                <p class="notis notis-perhatian mb-5">{{ session('peringatan') }}</p>
            @endif

            @if ($errors->any())
                <div class="notis notis-galat mb-5">
                    <p class="font-semibold">Ada yang perlu diperbaiki</p>
                    <ul class="mt-1 list-inside list-disc space-y-0.5">
                        @foreach ($errors->all() as $pesan)
                            <li>{{ $pesan }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('isi')
        </div>
    </main>
</div>

{{-- Bilah bawah untuk HP dan tablet: jempol ada di bawah layar, bukan di pojok
     kiri atas tempat tombol menu berada. --}}
<nav class="nav-bawah" aria-label="Navigasi cepat">
    @foreach ($navBawah as [$rute, $label, $izin, $pola, $jumlah, $ikon])
        @php $aktif = request()->routeIs($pola); @endphp
        <a href="{{ route($rute) }}" @if ($aktif) aria-current="page" @endif>
            <span class="relative">
                <x-ikon :nama="$ikon" />
                @if ($jumlah)
                    <span class="absolute -top-1 -right-1.5 inline-flex min-w-4 justify-center rounded-full bg-merah px-1 text-[0.625rem] font-bold text-white tabular">
                        {{ $jumlah }}
                    </span>
                @endif
            </span>
            {{ Str::of($label)->explode(' ')->first() }}
        </a>
    @endforeach
    <button type="button" @click="menu = true"
            class="flex flex-col items-center gap-0.5 px-1 py-2 text-[0.6875rem] font-semibold text-muted">
        <x-ikon nama="menu" />
        Menu
    </button>
</nav>

</div>

{{-- Konfirmasi untuk formulir ber-data-konfirmasi (app.js), pengganti confirm()
     bawaan peramban: yang itu berjudul alamat situs, tidak bisa diberi gaya,
     dan membatalkannya dulu meninggalkan tombol "Memproses..." yang mati. --}}
<dialog id="dialog-konfirmasi" class="dialog" aria-labelledby="dialog-konfirmasi-judul" aria-describedby="dialog-konfirmasi-pesan">
    <div class="p-5 sm:p-6">
        <div class="flex items-start gap-4">
            <span data-konfirmasi-ikon hidden class="flex size-11 shrink-0 items-center justify-center rounded-full bg-merah-50 text-merah">
                <x-ikon nama="hapus" ukuran="size-5" />
            </span>
            <div class="min-w-0">
                <h2 id="dialog-konfirmasi-judul" class="text-lg font-bold" data-konfirmasi-judul></h2>
                <p id="dialog-konfirmasi-pesan" class="mt-2 leading-relaxed text-muted" data-konfirmasi-pesan></p>
            </div>
        </div>
        <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <button type="button" class="tombol tombol-garis" data-konfirmasi-batal>Batal</button>
            <button type="button" class="tombol tombol-utama" data-konfirmasi-ya></button>
        </div>
    </div>
</dialog>

@stack('skrip')
</body>
</html>
