<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="batas-unggah-mb" content="{{ batas_unggah_mb() }}">
    @hasSection('judul_lengkap')
        <title>@yield('judul_lengkap')</title>
    @else
        <title>@yield('judul', 'Perlengkapan tim, siap hari ini atau dibuat dari nol') | {{ setting('toko_nama', 'Setia Sport') }}</title>
    @endif
    <meta name="description" content="@yield('deskripsi', 'Toko alat olahraga dan workshop jersey kustom di Wonogiri. Pesanan diambil di toko atau dikirim.')">

    {{-- Halaman yang memuat data pribadi pelanggan (pelacakan pesanan lewat
         tautan bertoken, pesanan saya) atau tidak berguna di hasil pencarian
         tidak boleh diindeks, walau tautannya sempat tersebar di tempat umum. --}}
    {{-- Hasil pencarian katalog juga: isinya hanya susunan ulang halaman lain,
         dan kata yang diketik orang tidak layak jadi halaman di Google. --}}
    @if (request()->routeIs('lacak.*', 'pesanan-saya', 'keranjang', 'checkout', 'masuk', 'daftar*', 'akun', 'lupa-sandi', 'atur-sandi*')
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
<body class="ada-nav-bawah @yield('kelas-body') flex min-h-screen flex-col antialiased">

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
                       class="area-sentuh -my-1 flex items-center gap-1.5 py-1 text-white/75 hover:text-white">
                        <x-ikon nama="whatsapp" ukuran="size-4" />
                        <span class="tabular">{{ setting('toko_telepon') }}</span>
                    </a>
                @endif

                @foreach ($sosial as [$ikon, $nama, $tautan])
                    <a href="{{ $tautan }}" target="_blank" rel="noopener"
                       class="area-sentuh text-white/80 hover:text-white" title="{{ $nama }}">
                        <span class="sr-only">{{ $nama }}</span>
                        <x-ikon :nama="$ikon" ukuran="size-4" />
                    </a>
                @endforeach
            </div>
        </div>
    </div>
@endif

{{--
    Kepala halaman (KEP-167). Di HP isinya cukup logo, cari, keranjang, dan
    akun: menu pindah ke bilah bawah, tempat jempol berada. Di layar 320 baris
    ini punya ruang 280px; logo h-8 dan tiga ikon 40px muat dengan celah 8px.
    Mulai layar sedang navigasi (urutan dari klien, KEP-062) muncul di sini,
    dan kotak cari baru muat mulai layar lebar.
--}}
<header class="sticky top-0 z-30 border-b border-line bg-field/95 backdrop-blur">
    <div class="mx-auto flex h-16 max-w-6xl items-center gap-2 px-5 sm:gap-4">
        <a href="{{ route('beranda') }}" class="shrink-0 pointer-coarse:py-1.5" aria-label="Beranda Setia Sport">
            <x-logo tinggi="h-8 sm:h-10" />
        </a>

        <nav class="hidden h-full items-stretch gap-1 text-sm md:ml-2 md:flex" aria-label="Navigasi utama">
            @foreach ($nav as [$rute, $label, $pola, $parameter])
                @php
                    // "Custom jersey" menunjuk ke katalog dengan penyaring, jadi
                    // keaktifannya dibaca dari penyaring itu, bukan dari nama rute.
                    $aktif = $parameter === []
                        ? ($pola !== [] && request()->routeIs(...$pola) && ! request('jalur'))
                        : request()->routeIs('katalog') && request('jalur') === ($parameter['jalur'] ?? null);
                @endphp
                <a href="{{ route($rute, $parameter) }}" @if ($aktif) aria-current="page" @endif
                   class="flex items-center px-3 transition-colors hover:text-navy
                          {{ $aktif ? 'garis-aktif font-bold text-navy' : 'font-medium text-ink' }}">
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        <form action="{{ route('katalog') }}" method="GET" role="search" class="relative ml-auto hidden w-full max-w-xs lg:block">
            <label for="cari-atas" class="sr-only">Cari produk</label>
            <x-ikon nama="cari" ukuran="size-5" class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-muted" />
            <input id="cari-atas" type="search" name="q" value="{{ request()->routeIs('katalog') ? request('q') : '' }}"
                   placeholder="Cari jersey, bola, sepatu" enterkeyhint="search"
                   class="h-11 w-full rounded-full border border-transparent bg-paper pr-4 pl-11 text-sm placeholder:text-muted focus:border-navy focus:bg-field focus:outline-none">
        </form>

        <a href="{{ route('katalog') }}#q" title="Cari produk"
           class="ml-auto flex rounded-[7px] p-2 pointer-coarse:p-2.5 text-ink transition-colors hover:bg-navy-50 lg:hidden">
            <span class="sr-only">Cari produk</span>
            <x-ikon nama="cari" ukuran="size-6" />
        </a>

        {{-- Keranjang tetap terlihat di semua ukuran: ia satu-satunya aksi yang
             sedang dikejar pelanggan, jadi tidak boleh disembunyikan di menu.
             Berupa ikon supaya muat berdampingan dengan tombol masuk di layar
             HP; namanya tetap terbaca pembaca layar dan muncul sebagai tooltip. --}}
        <a href="{{ route('keranjang') }}" title="Keranjang"
           class="group relative flex items-center rounded-[7px] p-2 pointer-coarse:p-2.5 text-ink transition-colors hover:bg-navy-50">
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
                {{-- Penghitung netral (navy): merah disimpan untuk tombol yang
                     menggerakkan pesanan (KEP-167). --}}
                <span class="tabular absolute -top-0.5 -right-0.5 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-navy px-1 text-xs font-bold text-white ring-2 ring-field">
                    {{ $isiKeranjang }}
                </span>
            @endif
        </a>

        @if ($pelanggan)
            <a href="{{ route('akun') }}" title="Akun saya"
               @if (request()->routeIs('akun', 'pesanan-saya')) aria-current="page" @endif
               class="-mr-2 flex rounded-[7px] p-2 pointer-coarse:p-2.5 text-ink transition-colors hover:bg-navy-50 md:mr-0">
                <span class="sr-only">Akun saya</span>
                <x-ikon nama="orang" ukuran="size-6" />
            </a>
        @else
            {{-- Di HP cukup ikon; tulisan "Masuk" baru muat mulai layar sedang. --}}
            <a href="{{ route('masuk') }}" title="Masuk"
               class="-mr-2 flex rounded-[7px] p-2 pointer-coarse:p-2.5 text-ink transition-colors hover:bg-navy-50 md:hidden">
                <span class="sr-only">Masuk</span>
                <x-ikon nama="orang" ukuran="size-6" />
            </a>
            <a href="{{ route('masuk') }}"
               class="hidden rounded-[8px] border border-line-strong px-3.5 py-2 text-sm font-semibold transition-colors hover:border-navy-300 hover:bg-navy-50 md:block">
                Masuk
            </a>
        @endif
    </div>
</header>

{{-- Di HP, judul halaman ikut menempel di bawah bilah atas. Setelah menggulir
     beberapa layar, pelanggan kehilangan jejak sedang ada di halaman apa, dan
     bilah atas hanya berisi logo. Di layar lebar judulnya sudah terbaca utuh di
     badan halaman, jadi bilah ini tidak dipasang di sana.

     Bilah baru muncul setelah judul besar halaman tergulir lewat (KEP-107):
     dulu keduanya berdempetan di layar pertama dengan tulisan yang sama, dan
     bilahnya memakan satu baris layar HP. Ia mengambang, jadi muncul dan
     hilangnya tidak menggeser isi. Halaman yang judul besarnya tersembunyi
     (langkah 2 sampai 4 pesan kustom) langsung memakainya. --}}
@hasSection('subjudul')
    <div x-data="{ tampak: false }" x-show="tampak" x-cloak x-transition.opacity.duration.150ms
         x-init="const h = document.querySelector('#konten h1');
                 if (! h) { tampak = true; return; }
                 new IntersectionObserver(([e]) => {
                     tampak = ! e.isIntersecting && (e.boundingClientRect.height === 0 || e.boundingClientRect.top < 64);
                 }, { rootMargin: '-64px 0px 0px 0px' }).observe(h);"
         class="fixed inset-x-0 top-16 z-20 border-b border-line bg-field/95 backdrop-blur md:hidden">
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

{{--
    Kaki halaman (KEP-091). Di HP dulu satu kolom panjang setinggi 817px, dan
    nomor WhatsApp, yang paling ingin diketuk orang, cuma teks biasa. Kini
    kontaknya tombol di blok merek, tiap lokasi punya tautan petunjuk arah, dan
    kolom "Hubungi kami" yang hanya mengulang nomor itu dilebur.
--}}
{{-- Kaki halaman navy berpita tiga warna (KEP-167), menutup halaman seperti
     kerah jersey. Teks navy 200 di navy 900 (11,6:1), judul putih. --}}
<footer class="mt-16 bg-navy-900 text-navy-200 sm:mt-20">
    <div class="pita" aria-hidden="true"></div>
    {{-- Lima kolom sebaris seperti papan Kaki di kanvas Kit Tim (KEP-179) mulai
         layar lebar. Di bawahnya merek mengambil satu baris sendiri: di tablet
         empat kolom sisanya sebaris, di HP berpasangan dua-dua. Kolom lokasi
         dibuat lebih lebar supaya jam buka tidak patah di tengah. --}}
    <div class="mx-auto grid max-w-6xl grid-cols-2 gap-x-6 gap-y-8 px-5 py-9 sm:gap-x-10 sm:py-12 md:grid-cols-[0.7fr_0.9fr_1.2fr_1.2fr] xl:grid-cols-[1.3fr_0.8fr_0.9fr_1fr_1fr]">
        <div class="col-span-2 md:col-span-4 xl:col-span-1">
            <x-logo terang tinggi="h-9 sm:h-10" />
            <p class="mt-3 max-w-md text-sm leading-relaxed">
                Menyediakan berbagai perlengkapan olahraga dan layanan konveksi jersey custom. Pesanan dapat diambil langsung di toko atau dikirim ke alamat tujuan.
            </p>

            @if (tautan_wa())
                <a href="{{ tautan_wa() }}" target="_blank" rel="noopener"
                   class="tombol mt-4 w-full border-white/30 bg-white/5 whitespace-nowrap text-white hover:bg-white/10 sm:w-auto md:text-sm">
                    <x-ikon nama="whatsapp" ukuran="size-4" />
                    Chat lewat WhatsApp
                </a>
            @endif

            @if ($sosial !== [])
                <div class="mt-4 flex gap-2">
                    {{-- Kotak bergaris terang di atas navy, bukan warna merek pihak
                         lain yang akan pecah dari palet situs. --}}
                    @foreach ($sosial as [$ikon, $nama, $tautan])
                        <a href="{{ $tautan }}" target="_blank" rel="noopener" title="{{ $nama }}"
                           class="flex size-11 items-center justify-center rounded-[8px] border border-white/20 text-white transition-colors hover:bg-white/10">
                            <span class="sr-only">{{ $nama }}</span>
                            <x-ikon :nama="$ikon" ukuran="size-4" />
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
        {{-- Tautan navy 100, sedikit lebih terang dari teks biasa, tanpa garis
             bawah kecuali saat disorot, seperti di kanvas. Portofolio dan
             testimoni dua halaman berbeda; masing-masing hanya ditautkan kalau
             berisi, karena galeri kosong menjawab 404 (KEP-106). --}}
        @foreach ([
            ['Belanja', array_filter([
                ['Katalog', route('katalog')],
                ['Jersey custom', route('katalog', ['jalur' => 'kustom'])],
                ['Siap beli', route('katalog', ['jalur' => 'stok'])],
                \App\Models\Portofolio::aktif()->exists() ? ['Portofolio', route('portofolio')] : null,
                \App\Models\Testimoni::aktif()->exists() ? ['Testimoni', route('testimoni')] : null,
            ])],
            ['Bantuan', [
                ['Lacak pesanan', route('lacak')],
                ['Cara pesan custom', route('beranda').'#cara-pesan'],
                ['Kebijakan Privasi', route('kebijakan-privasi')],
                ['Syarat & Ketentuan', route('syarat-ketentuan')],
            ]],
        ] as [$judulKolom, $tautanKolom])
            <nav aria-label="{{ $judulKolom }}">
                <p class="text-sm font-bold text-white">{{ $judulKolom }}</p>
                <ul class="mt-1.5">
                    @foreach ($tautanKolom as [$labelTautan, $urlTautan])
                        <li><a href="{{ $urlTautan }}" class="inline-block py-1.5 pointer-coarse:py-3 text-sm text-navy-100 underline-offset-[3px] hover:underline">{{ $labelTautan }}</a></li>
                    @endforeach
                </ul>
            </nav>
        @endforeach
        {{-- Dua lokasi dengan tugas berbeda: toko tempat pesanan diambil, konveksi
             tempat jersey dikerjakan. Keduanya punya jam buka sendiri. --}}
        @foreach (array_filter([
            ['Toko', 'Toko Olahraga & Lokasi Pengambilan Pesanan', setting('toko_alamat'), setting('toko_jam_buka'), setting('maps_embed') ?: tautan_peta((string) setting('toko_alamat'))],
            setting('konveksi_alamat')
                ? ['Konveksi', 'Lokasi produksi jersey & konveksi '.setting('toko_nama', 'Setia Sport'), setting('konveksi_alamat'), setting('konveksi_jam_buka'), tautan_peta((string) setting('konveksi_alamat'))]
                : null,
        ]) as [$judul, $tugas, $alamat, $jam, $peta])
            <div class="col-span-2 sm:col-span-1">
                <p class="text-sm font-bold text-white">{{ $judul }}</p>
                <p class="text-xs">{{ $tugas }}</p>
                <p class="mt-2 text-sm leading-relaxed">{{ $alamat }}</p>
                @if (filled($jam))
                    <p class="tabular mt-1.5 text-sm leading-relaxed">{{ baris_html($jam) }}</p>
                @endif
                <a href="{{ $peta }}" target="_blank" rel="noopener"
                   class="mt-1 inline-flex items-center gap-1.5 py-1.5 pointer-coarse:py-3 text-sm font-semibold text-white underline decoration-white/40 underline-offset-[3px] hover:decoration-white">
                    Petunjuk arah
                    <x-ikon nama="buka-luar" ukuran="size-3.5" />
                </a>
            </div>
        @endforeach
    </div>
    <div class="border-t border-white/10">
        {{-- "Masuk staf" menumpang di sebelah hak cipta, terpisah dari tautan
             untuk pelanggan (KEP-165); tautan kebijakan kini di kolom Bantuan
             (KEP-179). Di HP bilah bawah sudah memberi ruang sendiri; mulai layar
             sedang tombol Chat CS kembali mengambang, jadi bagian paling bawah
             diberi ruang setinggi tombol itu. --}}
        <div @class([
            'mx-auto max-w-6xl px-5 pt-4 pb-4 text-xs',
            'md:pb-24' => cs_aktif(),
        ])>
            <p class="flex flex-wrap items-center gap-x-2">
                <span>&copy; {{ date('Y') }} {{ setting('toko_nama', 'Setia Sport') }}</span>
                <span aria-hidden="true" class="text-white/30">&middot;</span>
                <a href="{{ route('panel.login') }}" class="inline-block py-1.5 pointer-coarse:py-3.5 text-white underline decoration-white/40 underline-offset-[3px] hover:decoration-white">Masuk staf</a>
            </p>
        </div>
    </div>
</footer>

{{--
    Bilah bawah etalase di HP (KEP-167). Jempol ada di bawah layar; menu garis
    tiga di pojok atas dulu butuh dua ketukan untuk berpindah halaman. Isinya
    mengikuti menu atas (KEP-062) ditambah Beranda dan Chat. Tombol Chat
    membuka jendela CS lewat peristiwa buka-cs; tanda pesan belum dibaca
    dikirim balik oleh widget lewat cs-belum-dibaca. Tanpa CS aktif, tempatnya
    diisi akun pelanggan. Ditaruh sebelum widget supaya pendengarnya sudah
    terpasang saat widget mengirim hitungan awal.
--}}
@php
    $aktifBawah = fn (array $pola, ?string $jalur = null) => request()->routeIs(...$pola)
        && ($jalur === null ? ! request('jalur') : request('jalur') === $jalur);
    $navBawah = [
        [route('beranda'), 'Beranda', 'beranda', $aktifBawah(['beranda'])],
        [route('katalog'), 'Produk', 'kategori', $aktifBawah(['katalog', 'produk'])],
        [route('katalog', ['jalur' => 'kustom']), 'Custom', 'kaos', $aktifBawah(['katalog'], 'kustom') || request()->routeIs('kustom.*')],
        $pelanggan
            ? [route('pesanan-saya'), 'Pesanan', 'pesanan', request()->routeIs('pesanan-saya', 'lacak*')]
            : [route('lacak'), 'Lacak', 'produk', request()->routeIs('lacak*')],
    ];
@endphp
<nav class="nav-bawah nav-bawah-etalase print:hidden" aria-label="Navigasi bawah">
    @foreach ($navBawah as [$url, $label, $ikon, $aktif])
        <a href="{{ $url }}" @if ($aktif) aria-current="page" @endif>
            <x-ikon :nama="$ikon" />
            {{ $label }}
        </a>
    @endforeach
    @if (cs_aktif())
        <button type="button" x-data="{ belum: 0 }" @cs-belum-dibaca.window="belum = $event.detail"
                @click="window.dispatchEvent(new CustomEvent('buka-cs'))">
            <span class="relative">
                <x-ikon nama="chat" />
                <span x-show="belum > 0" x-cloak x-text="belum"
                      class="tabular absolute -top-1 -right-2 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-navy px-1 text-xs font-bold text-white ring-2 ring-field"></span>
            </span>
            Chat
        </button>
    @else
        <a href="{{ $pelanggan ? route('akun') : route('masuk') }}"
           @if (request()->routeIs('akun', 'masuk', 'daftar')) aria-current="page" @endif>
            <x-ikon nama="orang" />
            {{ $pelanggan ? 'Akun' : 'Masuk' }}
        </a>
    @endif
</nav>

@if (cs_aktif())
    @include('komponen.widget-cs')
@endif

@stack('skrip')
</body>
</html>
