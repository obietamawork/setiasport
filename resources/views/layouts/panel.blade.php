<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('judul', 'Panel') | {{ setting('toko_nama', 'Setia Sport') }}</title>
    <meta name="theme-color" content="#0d1733">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <meta name="robots" content="noindex">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- Sidebar yang disembunyikan diingat per perangkat. Dipasang sebelum halaman
         tergambar, supaya sidebar tidak sempat muncul lalu hilang. --}}
    <script>
        try {
            if (localStorage.getItem('panel-sidebar') === 'tutup') document.documentElement.classList.add('sidebar-tutup');
        } catch (e) {}
    </script>
</head>
{{-- Register visual panel sengaja berbeda dari etalase: lebih padat, lebih
     tenang, tanpa hiasan. Ini alat kerja, bukan etalase. --}}
{{-- Satu tombol, dua perilaku: di layar lebar menyembunyikan sidebar supaya
     tabel lebar dapat ruang; di HP membuka menu sebagai laci dari kiri. --}}
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

@php
    $u = auth('web')->user();

    /*
     * Menu dikelompokkan menurut jenis pekerjaannya. Delapan tautan datar
     * memaksa staf membaca semuanya setiap kali; dikelompokkan, mata langsung
     * menuju bagian yang dicari.
     */
    $grup = [
        'Operasional' => [
            ['panel.dashboard', 'Dashboard', null, 'panel.dashboard', null, 'dashboard'],
            ['panel.pesanan.index', 'Pesanan', 'order.lihat', 'panel.pesanan.*', $jumlahPesananAktif ?? null, 'pesanan'],
            ['panel.antrian.index', 'Antrian kerja', null, 'panel.antrian.*', $jumlahAntrian ?? null, 'antrian'],
            ['panel.serah-terima', 'Serah terima', 'serah_terima.proses', 'panel.serah-terima', $jumlahSiapDiambil ?? null, 'serah-terima'],
            ...(($csTampilMenu ?? true) ? [['panel.cs.index', cs_aktif() ? 'Chat CS' : 'Arsip chat', 'cs.balas', 'panel.cs.*', null, 'chat']] : []),
        ],
        'Katalog' => [
            ['panel.produk.index', 'Produk', 'katalog.kelola', 'panel.produk.*', null, 'produk'],
            ['panel.kategori.index', 'Kategori', 'katalog.kelola', 'panel.kategori.*', null, 'kategori'],
            ['panel.stok.index', 'Stok', 'stok.kelola', 'panel.stok.*', null, 'stok'],
        ],
        'Profil usaha' => [
            ['panel.portofolio.index', 'Portofolio', 'profil.kelola', 'panel.portofolio.*', null, 'portofolio'],
            ['panel.testimoni.index', 'Testimoni', 'profil.kelola', 'panel.testimoni.*', null, 'testimoni'],
        ],
        'Toko' => [
            ['panel.pekerja.index', 'Pekerja konveksi', 'pengguna.kelola', 'panel.pekerja.*', null, 'pekerja'],
            ['panel.laporan', 'Laporan', 'laporan.lihat', 'panel.laporan', null, 'laporan'],
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
    $navBawah = collect($grup['Operasional'])->filter($bolehLihat)->take(4)->values();
@endphp

@php
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

<header class="sticky top-0 z-30 bg-navy-900 text-white">
    <div class="mx-auto flex h-14 max-w-7xl items-center gap-4 px-4">
        <button type="button" @click="alihMenu()" aria-controls="sidebar-panel"
                class="-ml-1 rounded-[7px] p-2 hover:bg-white/10"
                :aria-expanded="(lebar ? ! tutup : menu).toString()"
                :aria-label="lebar ? (tutup ? 'Tampilkan menu samping' : 'Sembunyikan menu samping') : (menu ? 'Tutup menu' : 'Buka menu')"
                :title="lebar ? (tutup ? 'Tampilkan menu samping' : 'Sembunyikan menu samping') : null"
                aria-label="Menu">
            <svg class="size-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8"
                 stroke-linecap="round" aria-hidden="true">
                <path x-show="!menu" d="M3 6h14M3 10h14M3 14h14" />
                <path x-show="menu" x-cloak d="M5 5l10 10M15 5L5 15" />
            </svg>
        </button>

        <a href="{{ route('panel.dashboard') }}" class="flex items-center gap-3">
            <x-logo :terang="true" />
            <span class="hidden text-xs font-medium text-white/45 sm:inline">Panel internal</span>
        </a>

        <div class="ml-auto flex items-center gap-1 text-sm">
            @if ($csNotif)
                <a href="{{ route('panel.cs.index') }}" x-show="jumlah > 0" x-cloak
                   class="flex items-center gap-1.5 rounded-[7px] bg-merah px-2.5 py-1.5 font-semibold text-white hover:bg-merah/90">
                    <x-ikon nama="chat" ukuran="size-4" />
                    <span class="hidden sm:inline">Chat baru</span>
                    <span class="tabular" x-text="jumlah"></span>
                </a>
            @endif

            {{-- Identitas, "lihat toko", dan keluar dirangkum ke satu menu.
                 Tiga hal yang jarang ditekan tidak perlu memakan bilah atas,
                 apalagi di layar HP tempat ruangnya paling sempit. --}}
            <div class="relative" x-data="{ buka: false }" @keydown.escape="buka = false">
                <button type="button" @click="buka = ! buka" :aria-expanded="buka.toString()"
                        class="flex items-center gap-2 rounded-[7px] py-1.5 pr-2 pl-2.5 hover:bg-white/10">
                    <span class="flex size-7 items-center justify-center rounded-full bg-white/15 text-xs font-bold">
                        {{ Str::of($u->name)->explode(' ')->take(2)->map(fn ($k) => Str::substr($k, 0, 1))->implode('') }}
                    </span>
                    <span class="hidden leading-tight sm:block">
                        <span class="block font-medium">{{ $u->name }}</span>
                        <span class="block text-xs text-white/50">{{ $u->roles->pluck('nama')->implode(', ') }}</span>
                    </span>
                    <x-ikon nama="turun" ukuran="size-4" class="text-white/60" />
                </button>

                <div x-show="buka" x-cloak x-transition.opacity @click.outside="buka = false"
                     class="absolute right-0 z-40 mt-2 w-60 overflow-hidden rounded-[10px] border border-line bg-field text-ink shadow-lg">
                    <div class="border-b border-line px-4 py-3 sm:hidden">
                        <p class="font-semibold">{{ $u->name }}</p>
                        <p class="text-xs text-muted">{{ $u->roles->pluck('nama')->implode(', ') }}</p>
                    </div>
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
    </div>
</header>

<div class="mx-auto flex max-w-7xl gap-6 px-4 py-6 pb-24 lg:gap-8 lg:pb-6">
    {{-- Latar gelap di belakang laci menu HP; mengetuknya menutup laci. --}}
    <div x-show="menu" x-transition.opacity x-cloak @click="menu = false"
         class="fixed inset-0 z-40 bg-navy-900/50 lg:hidden" aria-hidden="true"></div>

    {{-- Layar lebar: kolom yang menempel saat halaman digulir, jadi menu tidak
         pernah hilang di halaman panjang. HP: laci dari kiri (lihat .sidebar-panel). --}}
    <aside id="sidebar-panel" class="sidebar-panel" :class="{ 'terbuka': menu }">
        <div class="mb-4 flex items-center justify-between lg:hidden">
            <p class="px-3 text-sm font-semibold">Menu</p>
            <span class="sidebar-kaki"></span>
            <button type="button" @click="menu = false" class="rounded-[7px] p-2 text-muted hover:bg-navy-50 hover:text-ink"
                    aria-label="Tutup menu">
                <svg class="size-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8"
                     stroke-linecap="round" aria-hidden="true"><path d="M5 5l10 10M15 5L5 15" /></svg>
            </button>
        </div>

        <nav class="space-y-0.5 text-sm" aria-label="Navigasi panel">
            @foreach ($grup as $namaGrup => $tautan)
                @php $terlihat = collect($tautan)->filter($bolehLihat); @endphp
                @continue($terlihat->isEmpty())

                <p class="grup-menu">{{ $namaGrup }}</p>

                @foreach ($terlihat as [$rute, $label, $izin, $pola, $jumlah, $ikon])
                    @php $aktif = request()->routeIs($pola); @endphp
                    <a href="{{ route($rute) }}" @click="menu = false" title="{{ $label }}"
                       @if ($aktif) aria-current="page" @endif
                       class="menu-panel {{ $aktif ? 'menu-panel-aktif' : '' }}">
                        <x-ikon :nama="$ikon" />
                        <span class="menu-teks">{{ $label }}</span>
                        @if ($rute === 'panel.cs.index' && $csNotif)
                            <span x-show="jumlah > 0" x-text="jumlah" @if (! count($csNotif['belumDibaca'])) x-cloak @endif
                                  class="lencana-menu bg-merah text-white">{{ count($csNotif['belumDibaca']) }}</span>
                        @elseif ($jumlah)
                            <span class="lencana-menu {{ $aktif ? 'bg-white/20 text-white' : 'bg-navy-100 text-navy-800' }}">
                                {{ $jumlah }}
                            </span>
                        @endif
                    </a>
                @endforeach
            @endforeach
        </nav>
    </aside>

    <main class="min-w-0 flex-1">
        <div class="kolom-kerja">
            @if (session('sukses'))
                <p class="notis notis-ok mb-5">{{ session('sukses') }}</p>
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

@stack('skrip')
</body>
</html>
