<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('judul', 'Perlengkapan tim, siap hari ini atau dibuat dari nol') | {{ setting('toko_nama', 'Setia Sport') }}</title>
    <meta name="description" content="@yield('deskripsi', 'Toko alat olahraga dan workshop jersey kustom di Wonogiri. Pesanan diambil di toko atau dikirim.')">

    {{-- Halaman yang memuat data pribadi pelanggan (pelacakan pesanan lewat
         tautan bertoken, pesanan saya) atau tidak berguna di hasil pencarian
         tidak boleh diindeks, walau tautannya sempat tersebar di tempat umum. --}}
    {{-- Hasil pencarian katalog juga: isinya hanya susunan ulang halaman lain,
         dan kata yang diketik orang tidak layak jadi halaman di Google. --}}
    @if (request()->routeIs('lacak.*', 'pesanan-saya', 'keranjang', 'checkout', 'masuk', 'daftar*')
        || (request()->routeIs('katalog') && request()->filled('q')))
        <meta name="robots" content="noindex">
    @endif

    {{-- Satu alamat resmi per halaman: tanpa ini ?utm_source=... dari tautan
         yang dibagikan dianggap halaman terpisah dengan isi kembar (KEP-089). --}}
    <link rel="canonical" href="@yield('kanonik', url()->current())">
    @stack('kepala')

    {{-- Pratinjau tautan di WhatsApp, Facebook, dan Instagram. Tanpa ini tautan
         produk yang dibagikan ke grup hanya tampil sebagai alamat polos. --}}
    <meta property="og:type" content="@yield('og_tipe', 'website')">
    <meta property="og:site_name" content="{{ setting('toko_nama', 'Setia Sport') }}">
    <meta property="og:locale" content="id_ID">
    <meta property="og:title" content="@yield('judul', 'Perlengkapan tim, siap hari ini atau dibuat dari nol')">
    <meta property="og:description" content="@yield('deskripsi', 'Toko alat olahraga dan workshop jersey kustom di Wonogiri. Pesanan diambil di toko atau dikirim.')">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="@yield('og_gambar', asset('logo.png'))">
    @hasSection('og_gambar')
        <meta name="twitter:card" content="summary_large_image">
    @else
        <meta name="twitter:card" content="summary">
    @endif

    <meta name="theme-color" content="#16255b">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">


    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col antialiased">

{{-- Pengguna papan ketik dan pembaca layar melompati kepala halaman (WCAG 2.4.1). --}}
<a href="#konten" class="lewati-ke-konten">Lewati ke konten</a>

@php
    /*
     * Media sosial toko (KEP-063). Yang kosong tidak digambar sama sekali:
     * ikon yang menuju halaman kosong lebih buruk daripada tidak ada ikon.
     */
    $sosial = array_values(array_filter([
        ['instagram', 'Instagram', setting('sosial_instagram')],
        ['tiktok', 'TikTok', setting('sosial_tiktok')],
        ['facebook', 'Facebook', setting('sosial_facebook')],
    ], fn ($t) => filled($t[2])));
@endphp

@php
    $isiKeranjang = app(\App\Services\Keranjang::class)->jumlahBaris();
    $pelanggan = auth('pelanggan')->user();

    /*
     * Susunan menu mengikuti rancangan klien (KEP-062). Jersey custom berdiri
     * sendiri, tidak disembunyikan di dalam katalog: itulah yang membedakan
     * toko ini dari toko olahraga lain, dan pemesan seragam mencarinya dengan
     * nama itu, bukan dengan kata "katalog".
     */
    $nav = array_values(array_filter([
        ['katalog', 'Produk', ['katalog', 'produk'], []],
        ['katalog', 'Custom jersey', [], ['jalur' => 'kustom']],
        $pelanggan
            ? ['pesanan-saya', 'Pesanan saya', ['pesanan-saya', 'lacak*'], []]
            : ['lacak', 'Lacak pesanan', ['lacak*'], []],
    ]));
@endphp

{{-- Bilah tipis di puncak halaman, mengikuti rancangan klien: satu kalimat
     tentang toko, nomor yang bisa dihubungi, dan media sosialnya. Disembunyikan
     di layar sempit, tempat setiap baris berharga dan nomornya sudah ada di
     kaki halaman. --}}
@if (setting('toko_telepon') || $sosial !== [])
    <div class="hidden bg-navy-900 text-white sm:block">
        <div class="mx-auto flex max-w-6xl items-center gap-4 px-5 py-1.5 text-xs">
            <span class="text-white/60">Toko olahraga &amp; konveksi jersey di Wonogiri</span>

            <div class="ml-auto flex items-center gap-3">
                @if (setting('toko_telepon'))
                    <a href="{{ tautan_wa('Halo '.setting('toko_nama', 'Setia Sport').', saya mau bertanya.') ?: '#' }}"
                       target="_blank" rel="noopener"
                       class="flex items-center gap-1.5 text-white/75 hover:text-white">
                        <x-ikon nama="whatsapp" ukuran="size-4" />
                        <span class="tabular">{{ setting('toko_telepon') }}</span>
                    </a>
                @endif

                @foreach ($sosial as [$ikon, $nama, $tautan])
                    <a href="{{ $tautan }}" target="_blank" rel="noopener"
                       class="text-white/80 hover:text-white" title="{{ $nama }}">
                        <span class="sr-only">{{ $nama }}</span>
                        <x-ikon :nama="$ikon" ukuran="size-4" />
                    </a>
                @endforeach
            </div>
        </div>
    </div>
@endif

<header class="sticky top-0 z-30 border-b border-line bg-field/95 backdrop-blur"
        x-data="{ menu: false }" @keydown.escape.window="menu = false">
    {{-- Di HP baris ini cuma punya ruang 280px (layar 320) sampai 320px (layar
         360), dan tidak ada isinya yang boleh menyusut. Dengan celah gap-4 dan
         logo h-9 isinya butuh ±351px: tombol menu terdorong keluar layar dan
         seluruh halaman bisa digeser ke samping. Celah dan logo dikecilkan
         khusus di HP, dan tombol "Masuk" baru muncul mulai layar 360px
         (di bawah itu ia tetap ada di dalam menu). --}}
    <div class="mx-auto flex h-16 max-w-6xl items-center gap-2 px-5 sm:gap-4">
        <a href="{{ route('beranda') }}" class="shrink-0" aria-label="Beranda Setia Sport">
            <x-logo tinggi="h-8 sm:h-10" />
        </a>

        {{-- Navigasi penuh hanya muat mulai layar sedang. Di bawah itu ia
             meluber dan memotong dirinya sendiri, jadi dipindah ke panel. --}}
        <nav class="ml-auto hidden h-full items-stretch gap-1 text-sm md:flex" aria-label="Navigasi utama">
            @foreach ($nav as [$rute, $label, $pola, $parameter])
                @php
                    // "Custom jersey" menunjuk ke katalog dengan penyaring, jadi
                    // keaktifannya dibaca dari penyaring itu, bukan dari nama rute.
                    $aktif = $parameter === []
                        ? ($pola !== [] && request()->routeIs(...$pola) && ! request('jalur'))
                        : request()->routeIs('katalog') && request('jalur') === ($parameter['jalur'] ?? null);
                @endphp
                <a href="{{ route($rute, $parameter) }}"
                   class="flex items-center px-3 font-medium transition-colors hover:text-navy
                          {{ $aktif ? 'garis-aktif text-navy' : 'text-muted' }}">
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        {{-- Keranjang tetap terlihat di semua ukuran: ia satu-satunya aksi yang
             sedang dikejar pelanggan, jadi tidak boleh disembunyikan di menu.
             Berupa ikon supaya muat berdampingan dengan tombol masuk di layar
             HP; namanya tetap terbaca pembaca layar dan muncul sebagai tooltip. --}}
        <a href="{{ route('keranjang') }}" title="Keranjang"
           class="group relative ml-auto flex items-center rounded-[7px] p-2 text-muted transition-colors hover:bg-navy-50 hover:text-ink md:ml-2">
            <span class="sr-only">Keranjang</span>
            <svg class="size-6 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M3 4h2l2.4 10.4a2 2 0 0 0 2 1.6h7.2a2 2 0 0 0 2-1.6L20 7H6" />
                <circle cx="10" cy="19.5" r="1.3" />
                <circle cx="17" cy="19.5" r="1.3" />
            </svg>
            {{-- Teks muncul meluncur hanya di layar yang punya hover sungguhan;
                 di HP ikon saja sudah cukup jelas dan tidak ada gunanya hover. --}}
            <span aria-hidden="true"
                  class="hidden max-w-0 overflow-hidden text-sm font-semibold whitespace-nowrap transition-all duration-200 md:inline-block md:group-hover:ml-1.5 md:group-hover:max-w-24">
                Keranjang
            </span>
            @if ($isiKeranjang)
                <span class="tabular absolute -top-0.5 -right-0.5 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-merah px-1 text-xs font-semibold text-white">
                    {{ $isiKeranjang }}
                </span>
            @endif
        </a>

        @unless ($pelanggan)
            <a href="{{ route('masuk') }}"
               class="hidden rounded-[7px] border border-line-strong px-3 py-2 text-sm font-semibold transition-colors hover:border-navy-300 hover:bg-navy-50 min-[360px]:block">
                Masuk
            </a>
        @endunless

        <button type="button" @click="menu = !menu"
                class="-mr-2 rounded-[7px] p-3 text-muted transition-colors hover:bg-navy-50 hover:text-ink md:hidden"
                :aria-expanded="menu" aria-controls="menu-mobil" aria-label="Buka menu">
            <svg class="size-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8"
                 stroke-linecap="round" aria-hidden="true">
                <path x-show="!menu" d="M3 6h14M3 10h14M3 14h14" />
                <path x-show="menu" x-cloak d="M5 5l10 10M15 5L5 15" />
            </svg>
        </button>
    </div>

    <nav id="menu-mobil" x-show="menu" x-cloak @click="menu = false"
         class="border-t border-line bg-field md:hidden" aria-label="Navigasi utama">
        <div class="mx-auto max-w-6xl px-5 py-2">
            @foreach ($nav as [$rute, $label, $pola, $parameter])
                @php
                    $aktif = $parameter === []
                        ? ($pola !== [] && request()->routeIs(...$pola) && ! request('jalur'))
                        : request()->routeIs('katalog') && request('jalur') === ($parameter['jalur'] ?? null);
                @endphp
                <a href="{{ route($rute, $parameter) }}"
                   class="block border-l-2 py-3 pl-3 font-medium transition-colors
                          {{ $aktif ? 'border-kuning text-navy' : 'border-transparent text-muted hover:text-ink' }}">
                    {{ $label }}
                </a>
            @endforeach

            @unless ($pelanggan)
                <a href="{{ route('masuk') }}"
                   class="block border-l-2 py-3 pl-3 font-medium transition-colors
                          {{ request()->routeIs('masuk', 'daftar') ? 'border-kuning text-navy' : 'border-transparent text-muted hover:text-ink' }}">
                    Masuk
                </a>
            @endunless
        </div>
    </nav>
</header>

{{-- Di HP, judul halaman ikut menempel di bawah bilah atas. Setelah menggulir
     beberapa layar, pelanggan kehilangan jejak sedang ada di halaman apa, dan
     bilah atas hanya berisi logo. Di layar lebar judulnya sudah terbaca utuh di
     badan halaman, jadi bilah ini tidak dipasang di sana. --}}
@hasSection('subjudul')
    <div class="sticky top-16 z-20 border-b border-line bg-field/95 backdrop-blur md:hidden">
        <div class="mx-auto max-w-6xl px-5 py-2.5">
            <p class="text-sm font-semibold">@yield('subjudul')</p>
            @hasSection('subcatatan')
                <p class="mt-0.5 text-xs leading-relaxed text-muted">@yield('subcatatan')</p>
            @endif
        </div>
    </div>
@endif

<main id="konten" tabindex="-1" class="flex-1">
    @if (session('sukses') && ! request()->routeIs('lacak.token'))
        <div class="mx-auto max-w-6xl px-5 pt-6">
            <p class="notis notis-ok">{{ session('sukses') }}</p>
        </div>
    @endif

    @if ($errors->any() && ! request()->routeIs('lacak.token'))
        <div class="mx-auto max-w-6xl px-5 pt-6">
            <p class="notis notis-galat">{{ $errors->first() }}</p>
        </div>
    @endif

    @yield('isi')
</main>

<footer class="mt-20 border-t border-line bg-field">
    {{-- Dua lokasi dengan tugas berbeda: toko tempat pesanan diambil, konveksi
         tempat jersey dikerjakan. Keduanya punya jam buka sendiri. --}}
    <div class="mx-auto grid max-w-6xl gap-10 px-5 py-12 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <x-logo tinggi="h-10" />
            <p class="mt-4 text-sm leading-relaxed text-muted">
                Toko perlengkapan olahraga dan konveksi jersey di Wonogiri.
            </p>

            @if ($sosial !== [])
                <div class="mt-4 flex gap-2">
                    {{-- Ikon bergaris tipis nyaris tak terlihat di kartu terang; latar
                         navy membuatnya jelas tanpa memakai warna merek pihak lain,
                         yang justru akan pecah dari palet situs. --}}
                    @foreach ($sosial as [$ikon, $nama, $tautan])
                        <a href="{{ $tautan }}" target="_blank" rel="noopener" title="{{ $nama }}"
                           class="flex size-11 items-center justify-center rounded-[8px] bg-navy text-white transition-colors hover:bg-navy-800">
                            <span class="sr-only">{{ $nama }}</span>
                            <x-ikon :nama="$ikon" ukuran="size-4" />
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
        <div>
            <p class="text-sm font-semibold">Toko</p>
            <p class="mt-2 text-sm leading-relaxed text-muted">{{ setting('toko_alamat') }}</p>
            <p class="tabular mt-2 text-sm leading-relaxed text-muted">{{ baris_html(setting('toko_jam_buka')) }}</p>
        </div>
        @if (setting('konveksi_alamat'))
            <div>
                <p class="text-sm font-semibold">Konveksi</p>
                <p class="mt-2 text-sm leading-relaxed text-muted">{{ setting('konveksi_alamat') }}</p>
                <p class="tabular mt-2 text-sm leading-relaxed text-muted">{{ baris_html(setting('konveksi_jam_buka')) }}</p>
            </div>
        @endif
        <div>
            <p class="text-sm font-semibold">Hubungi kami</p>
            <p class="mt-2 text-sm leading-relaxed text-muted">
                {{ cs_aktif() ? 'Chat CS di website' : 'Lewat WhatsApp' }}, {{ Str::lcfirst((string) setting('cs_jam_layanan')) }}.
            </p>
            @if (setting('toko_telepon'))
                <p class="tabular mt-1 text-sm text-muted">WhatsApp {{ setting('toko_telepon') }}</p>
            @endif
            <p class="mt-3 text-sm leading-relaxed text-muted">
                Pesanan bisa diambil sendiri di toko atau dikirim ke alamat Anda, dipilih saat memesan.
            </p>
        </div>
    </div>
    <div class="border-t border-line">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-5 py-4 text-xs text-muted">
            <span>&copy; {{ date('Y') }} {{ setting('toko_nama', 'Setia Sport') }}</span>
            <nav class="flex flex-wrap items-center gap-x-4" aria-label="Tautan kebijakan">
                <a href="{{ route('kebijakan-privasi') }}" class="tautan inline-block py-1.5">Kebijakan Privasi</a>
                <a href="{{ route('syarat-ketentuan') }}" class="tautan inline-block py-1.5">Syarat &amp; Ketentuan</a>
                <a href="{{ route('panel.login') }}" class="tautan inline-block py-1.5">Masuk staf</a>
            </nav>
        </div>
    </div>
</footer>

@if (cs_aktif())
    @include('komponen.widget-cs')
@endif

@stack('skrip')
</body>
</html>
