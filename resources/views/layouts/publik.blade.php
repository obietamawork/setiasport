<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('judul', 'Perlengkapan tim, siap hari ini atau dibuat dari nol') — {{ setting('toko_nama', 'Setia Sport') }}</title>
    <meta name="description" content="@yield('deskripsi', 'Toko alat olahraga dan workshop jersey kustom. Diambil sendiri di toko, tanpa ongkos kirim.')">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,400..800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen flex flex-col antialiased">

<header class="border-b border-line bg-field">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-6 px-5 py-4">
        <a href="{{ route('beranda') }}" class="flex items-baseline gap-2">
            <span class="t-wide text-lg font-extrabold tracking-tight">Setia Sport</span>
            <span class="hidden text-xs text-muted sm:inline">Alat olahraga &amp; jersey kustom</span>
        </a>

        <nav class="flex items-center gap-1 text-sm" aria-label="Navigasi utama">
            <a href="{{ route('katalog') }}"
               class="rounded-sm px-3 py-2 font-medium hover:bg-court-soft {{ request()->routeIs('katalog', 'produk') ? 'bg-court-soft' : '' }}">
                Katalog
            </a>
            <a href="{{ route('katalog', ['jalur' => 'kustom']) }}"
               class="rounded-sm px-3 py-2 font-medium hover:bg-court-soft">
                Pesan kustom
            </a>
            <a href="{{ route('lacak') }}"
               class="rounded-sm px-3 py-2 font-medium hover:bg-court-soft {{ request()->routeIs('lacak*') ? 'bg-court-soft' : '' }}">
                Lacak pesanan
            </a>
            <span class="mx-1 hidden h-5 w-px bg-line sm:block"></span>
            @php $isiKeranjang = app(\App\Services\Keranjang::class)->jumlahBaris(); @endphp
            <a href="{{ route('keranjang') }}"
               class="flex items-center gap-1 rounded-sm px-3 py-2 font-semibold hover:bg-court-soft">
                <span>Keranjang</span>
                @if ($isiKeranjang)
                    <span class="tabular text-signal">{{ $isiKeranjang }}</span>
                @endif
            </a>
        </nav>
    </div>
</header>

<main class="flex-1">
    @yield('isi')
</main>

<footer class="mt-20 border-t border-line bg-field">
    <div class="mx-auto grid max-w-6xl gap-8 px-5 py-12 sm:grid-cols-3">
        <div>
            <p class="t-wide text-base font-extrabold">{{ setting('toko_nama', 'Setia Sport') }}</p>
            <p class="mt-2 text-sm leading-relaxed text-muted">
                {{ setting('toko_alamat') }}
            </p>
        </div>
        <div>
            <p class="text-sm font-semibold">Jam buka</p>
            <p class="mt-2 text-sm text-muted">{{ setting('toko_jam_buka') }}</p>
            @if (setting('toko_telepon'))
                <p class="mt-1 text-sm text-muted tabular">{{ setting('toko_telepon') }}</p>
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

</body>
</html>
