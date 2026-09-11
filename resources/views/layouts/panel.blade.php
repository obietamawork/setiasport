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
</head>
{{-- Register visual panel sengaja berbeda dari etalase: lebih padat, lebih
     tenang, tanpa hiasan. Ini alat kerja, bukan etalase. --}}
<body class="min-h-screen bg-paper" x-data="{ menu: false }">

@php
    $u = auth('web')->user();

    /*
     * Menu dikelompokkan menurut jenis pekerjaannya. Delapan tautan datar
     * memaksa staf membaca semuanya setiap kali; dikelompokkan, mata langsung
     * menuju bagian yang dicari.
     */
    $grup = [
        'Operasional' => [
            ['panel.dashboard', 'Dashboard', null, 'panel.dashboard', null],
            ['panel.pesanan.index', 'Pesanan', 'order.lihat', 'panel.pesanan.*', $jumlahPesananAktif ?? null],
            ['panel.antrian.index', 'Antrian kerja', null, 'panel.antrian.*', $jumlahAntrian ?? null],
            ['panel.serah-terima', 'Serah terima', 'serah_terima.proses', 'panel.serah-terima', $jumlahSiapDiambil ?? null],
            ['panel.cs.index', 'Chat CS', 'cs.balas', 'panel.cs.*', null],
        ],
        'Katalog' => [
            ['panel.produk.index', 'Produk', 'katalog.kelola', 'panel.produk.*', null],
            ['panel.kategori.index', 'Kategori', 'katalog.kelola', 'panel.kategori.*', null],
            ['panel.stok.index', 'Stok', 'stok.kelola', 'panel.stok.*', null],
        ],
        'Profil usaha' => [
            ['panel.portofolio.index', 'Portofolio', 'profil.kelola', 'panel.portofolio.*', null],
            ['panel.testimoni.index', 'Testimoni', 'profil.kelola', 'panel.testimoni.*', null],
        ],
        'Toko' => [
            ['panel.laporan', 'Laporan', 'laporan.lihat', 'panel.laporan', null],
            ['panel.pengaturan.index', 'Pengaturan', 'pengaturan.kelola', 'panel.pengaturan.*', null],
        ],
    ];
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
        <button type="button" @click="menu = !menu"
                class="-ml-1 rounded-[7px] p-2 hover:bg-white/10 lg:hidden"
                :aria-expanded="menu" aria-label="Buka menu">
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
                    Chat baru <span class="tabular" x-text="jumlah"></span>
                </a>
            @endif
            <a href="{{ route('beranda') }}" target="_blank" rel="noopener"
               class="hidden rounded-[7px] px-2.5 py-1.5 text-white/70 hover:bg-white/10 hover:text-white sm:block">
                Lihat toko
            </a>

            {{-- Satu blok identitas saja. Nama dan peran berdampingan sebagai dua
                 label terpisah hanya mengulang hal yang sama. --}}
            <div class="mr-1 ml-2 hidden border-l border-white/15 pl-3 leading-tight sm:block">
                <p class="font-medium">{{ $u->name }}</p>
                <p class="text-xs text-white/50">{{ $u->roles->pluck('nama')->implode(', ') }}</p>
            </div>

            <form method="POST" action="{{ route('panel.keluar') }}">
                @csrf
                <button type="submit" class="rounded-[7px] px-2.5 py-1.5 hover:bg-white/10">Keluar</button>
            </form>
        </div>
    </div>
</header>

<div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 py-6 lg:flex-row lg:gap-8">
    {{-- Di mobile sidebar tampil sebagai blok penuh di atas konten. Sebagai
         kolom sempit ia menghimpit isi layar sampai tidak terbaca. --}}
    <aside :class="menu ? 'block' : 'hidden'" class="w-full shrink-0 lg:block lg:w-52">
        <nav class="text-sm" aria-label="Navigasi panel">
            @foreach ($grup as $namaGrup => $tautan)
                @php
                    $terlihat = collect($tautan)->filter(function ($t) use ($u) {
                        if ($t[2] && ! $u->punyaIzin($t[2])) {
                            return false;
                        }

                        return ! ($t[0] === 'panel.antrian.index' && empty($u->tahapProduksi()));
                    });
                @endphp
                @continue($terlihat->isEmpty())

                <p class="grup-menu">{{ $namaGrup }}</p>

                @foreach ($terlihat as [$rute, $label, $izin, $pola, $jumlah])
                    @php $aktif = request()->routeIs($pola); @endphp
                    <a href="{{ route($rute) }}" @click="menu = false"
                       class="flex items-center gap-2 rounded-[7px] px-3 py-2.5 font-medium transition-colors lg:py-2
                              {{ $aktif ? 'bg-navy text-white' : 'text-ink hover:bg-navy-50' }}">
                        <span>{{ $label }}</span>
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

</div>

@stack('skrip')
</body>
</html>
