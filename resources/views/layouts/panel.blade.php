<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('judul', 'Panel') — {{ setting('toko_nama', 'Setia Sport') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,400..800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
{{-- Register visual panel sengaja berbeda dari sisi publik: lebih padat, lebih
     tenang, tanpa hiasan. Ini alat kerja, bukan etalase. --}}
<body class="min-h-screen bg-paper" x-data="{ menu: false }">

<header class="bg-court-dark text-white">
    <div class="mx-auto flex max-w-7xl items-center gap-4 px-4 py-3">
        <button type="button" @click="menu = !menu"
                class="rounded-sm px-2 py-1 text-sm hover:bg-white/10 lg:hidden">Menu</button>

        <a href="{{ route('panel.dashboard') }}" class="t-wide font-extrabold">Setia Sport</a>
        <span class="hidden text-xs text-white/50 sm:inline">Panel internal</span>

        <div class="ml-auto flex items-center gap-3 text-sm">
            <a href="{{ route('beranda') }}" class="text-white/70 hover:text-white">Lihat toko</a>
            <span class="h-4 w-px bg-white/20"></span>
            <span class="hidden text-white/70 sm:inline">{{ auth()->user()->name }}</span>
            <form method="POST" action="{{ route('panel.keluar') }}">
                @csrf
                <button type="submit" class="rounded-sm px-2 py-1 hover:bg-white/10">Keluar</button>
            </form>
        </div>
    </div>
</header>

<div class="mx-auto flex max-w-7xl gap-6 px-4 py-6">
    <aside :class="menu ? 'block' : 'hidden'" class="w-52 shrink-0 lg:block">
        <nav class="space-y-0.5 text-sm" aria-label="Navigasi panel">
            @php
                // Pola disebut eksplisit: menurunkannya dari nama rute membuat
                // "panel.dashboard" menjadi pola "panel.*" yang cocok dengan semuanya.
                $menu = [
                    ['panel.dashboard', 'Dashboard', null, 'panel.dashboard'],
                    ['panel.pesanan.index', 'Pesanan', 'order.lihat', 'panel.pesanan.*'],
                    ['panel.antrian.index', 'Antrian kerja', null, 'panel.antrian.*'],
                    ['panel.serah-terima', 'Serah terima', 'serah_terima.proses', 'panel.serah-terima'],
                    ['panel.produk.index', 'Produk', 'katalog.kelola', 'panel.produk.*'],
                    ['panel.kategori.index', 'Kategori', 'katalog.kelola', 'panel.kategori.*'],
                    ['panel.stok.index', 'Stok', 'stok.kelola', 'panel.stok.*'],
                    ['panel.pengaturan.index', 'Pengaturan', 'pengaturan.kelola', 'panel.pengaturan.*'],
                ];
            @endphp

            @foreach ($menu as [$rute, $label, $izin, $pola])
                @continue($izin && ! auth()->user()->punyaIzin($izin))
                @continue($rute === 'panel.antrian.index' && empty(auth()->user()->tahapProduksi()))
                @php $aktif = request()->routeIs($pola); @endphp
                <a href="{{ route($rute) }}"
                   class="block rounded-sm px-3 py-2 font-medium {{ $aktif ? 'bg-court text-white' : 'hover:bg-court-soft' }}">
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        <div class="mt-6 border-t border-line pt-4">
            <p class="text-xs font-semibold text-muted">Peran Anda</p>
            <ul class="mt-2 space-y-1 text-xs text-muted">
                @foreach (auth()->user()->roles as $peran)
                    <li>{{ $peran->nama }}</li>
                @endforeach
            </ul>
        </div>
    </aside>

    <main class="min-w-0 flex-1">
        @if (session('sukses'))
            <div class="mb-5 border-l-2 border-court bg-court-soft px-4 py-3 text-sm">
                {{ session('sukses') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-5 border-l-2 border-signal bg-signal-soft px-4 py-3 text-sm">
                <p class="font-semibold">Ada yang perlu diperbaiki:</p>
                <ul class="mt-1 list-inside list-disc space-y-0.5">
                    @foreach ($errors->all() as $pesan)
                        <li>{{ $pesan }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('isi')
    </main>
</div>

</body>
</html>
