<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('judul', 'Perlengkapan tim, siap hari ini atau dibuat dari nol') | {{ setting('toko_nama', 'Setia Sport') }}</title>
    <meta name="description" content="@yield('deskripsi', 'Toko alat olahraga dan workshop jersey kustom di Wonogiri. Diambil sendiri di toko, tanpa ongkos kirim.')">
    <meta name="theme-color" content="#16255b">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col antialiased">

@php
    $isiKeranjang = app(\App\Services\Keranjang::class)->jumlahBaris();
    $pelanggan = auth('pelanggan')->user();

    $nav = $pelanggan
        ? [
            ['katalog', 'Katalog', ['katalog', 'produk']],
            ['pesanan-saya', 'Pesanan saya', ['pesanan-saya', 'lacak*']],
        ]
        : [
            ['katalog', 'Katalog', ['katalog', 'produk']],
            ['lacak', 'Lacak pesanan', ['lacak*']],
            ['masuk', 'Masuk', ['masuk', 'daftar']],
        ];
@endphp

<header class="sticky top-0 z-30 border-b border-line bg-field/95 backdrop-blur"
        x-data="{ menu: false }" @keydown.escape.window="menu = false">
    <div class="mx-auto flex h-16 max-w-6xl items-center gap-4 px-5">
        <a href="{{ route('beranda') }}" class="shrink-0" aria-label="Beranda Setia Sport">
            <x-logo />
        </a>

        {{-- Navigasi penuh hanya muat mulai layar sedang. Di bawah itu ia
             meluber dan memotong dirinya sendiri, jadi dipindah ke panel. --}}
        <nav class="ml-auto hidden h-full items-stretch gap-1 text-sm md:flex" aria-label="Navigasi utama">
            @foreach ($nav as [$rute, $label, $pola])
                @php $aktif = request()->routeIs(...$pola); @endphp
                <a href="{{ route($rute) }}"
                   class="flex items-center px-3 font-medium transition-colors hover:text-navy
                          {{ $aktif ? 'garis-aktif text-navy' : 'text-muted' }}">
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        {{-- Keranjang tetap terlihat di semua ukuran: ia satu-satunya aksi yang
             sedang dikejar pelanggan, jadi tidak boleh disembunyikan di menu. --}}
        <a href="{{ route('keranjang') }}"
           class="ml-auto flex items-center gap-2 rounded-[7px] border border-line-strong px-3 py-2 text-sm font-semibold transition-colors hover:border-navy-300 hover:bg-navy-50 md:ml-2">
            Keranjang
            @if ($isiKeranjang)
                <span class="tabular inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-merah px-1 text-xs text-white">
                    {{ $isiKeranjang }}
                </span>
            @endif
        </a>

        <button type="button" @click="menu = !menu"
                class="-mr-1 rounded-[7px] p-2 text-muted transition-colors hover:bg-navy-50 hover:text-ink md:hidden"
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
            @foreach ($nav as [$rute, $label, $pola])
                @php $aktif = request()->routeIs(...$pola); @endphp
                <a href="{{ route($rute) }}"
                   class="block border-l-2 py-3 pl-3 font-medium transition-colors
                          {{ $aktif ? 'border-kuning text-navy' : 'border-transparent text-muted hover:text-ink' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </nav>
</header>

<main class="flex-1">
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
    <div class="mx-auto grid max-w-6xl gap-10 px-5 py-12 sm:grid-cols-3">
        <div>
            <x-logo />
            <p class="mt-3 text-sm leading-relaxed text-muted">{{ setting('toko_alamat') }}</p>
        </div>
        <div>
            <p class="text-sm font-semibold">Jam buka</p>
            <p class="mt-2 text-sm text-muted">{{ setting('toko_jam_buka') }}</p>
            @if (setting('toko_telepon'))
                <p class="tabular mt-1 text-sm text-muted">{{ setting('toko_telepon') }}</p>
            @endif
        </div>
        <div>
            <p class="text-sm font-semibold">Pengambilan</p>
            <p class="mt-2 text-sm leading-relaxed text-muted">
                Semua pesanan diambil sendiri di toko. Kami belum melayani pengiriman,
                jadi tidak ada ongkos kirim.
            </p>
        </div>
    </div>
    <div class="border-t border-line">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-5 py-4 text-xs text-muted">
            <span>&copy; {{ date('Y') }} {{ setting('toko_nama', 'Setia Sport') }}</span>
            <a href="{{ route('panel.login') }}" class="tautan">Masuk staf</a>
        </div>
    </div>
</footer>

@stack('skrip')
</body>
</html>
