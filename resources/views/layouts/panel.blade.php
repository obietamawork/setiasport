<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('judul', 'Panel') | {{ setting('toko_nama', 'Setia Sport') }}</title>
    <meta name="theme-color" content="#0d1733">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
{{-- Register visual panel sengaja berbeda dari etalase: lebih padat, lebih
     tenang, tanpa hiasan. Ini alat kerja, bukan etalase. --}}
<body class="min-h-screen bg-paper" x-data="{ menu: false }">

@php
    $u = auth('web')->user();
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

<header class="sticky top-0 z-30 bg-navy-900 text-white">
    <div class="mx-auto flex h-14 max-w-7xl items-center gap-4 px-4">
        <button type="button" @click="menu = !menu"
                class="-ml-1 rounded-[7px] px-2.5 py-1.5 text-sm font-medium hover:bg-white/10 lg:hidden">
            Menu
        </button>

        <a href="{{ route('panel.dashboard') }}" class="flex items-center gap-3">
            <x-logo :terang="true" />
            <span class="hidden text-xs font-medium text-white/45 sm:inline">Panel internal</span>
        </a>

        <div class="ml-auto flex items-center gap-2 text-sm">
            <a href="{{ route('beranda') }}" target="_blank" rel="noopener"
               class="hidden rounded-[7px] px-2.5 py-1.5 text-white/70 hover:bg-white/10 hover:text-white sm:block">
                Lihat toko
            </a>

            <div class="hidden items-center gap-2 border-l border-white/15 pl-3 sm:flex">
                <span class="text-white/80">{{ $u->name }}</span>
                <span class="chip bg-white/10 text-white/70">{{ $u->roles->pluck('nama')->implode(', ') }}</span>
            </div>

            <form method="POST" action="{{ route('panel.keluar') }}">
                @csrf
                <button type="submit" class="rounded-[7px] px-2.5 py-1.5 hover:bg-white/10">Keluar</button>
            </form>
        </div>
    </div>
</header>

<div class="mx-auto flex max-w-7xl gap-7 px-4 py-6">
    <aside :class="menu ? 'block' : 'hidden'" class="w-52 shrink-0 lg:block">
        <nav class="space-y-0.5 text-sm" aria-label="Navigasi panel">
            @foreach ($menu as [$rute, $label, $izin, $pola])
                @continue($izin && ! $u->punyaIzin($izin))
                @continue($rute === 'panel.antrian.index' && empty($u->tahapProduksi()))
                @php $aktif = request()->routeIs($pola); @endphp
                <a href="{{ route($rute) }}"
                   class="block rounded-[7px] px-3 py-2 font-medium transition-colors
                          {{ $aktif ? 'bg-navy text-white' : 'text-ink hover:bg-navy-50' }}">
                    {{ $label }}
                </a>
            @endforeach
        </nav>
    </aside>

    <main class="min-w-0 flex-1">
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
    </main>
</div>

@stack('skrip')
</body>
</html>
