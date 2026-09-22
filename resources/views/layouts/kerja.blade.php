<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('judul', 'Lembar kerja') | {{ setting('toko_nama', 'Setia Sport') }}</title>
    <meta name="theme-color" content="#0d1733">
    <meta name="robots" content="noindex">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

{{--
    Kerangka untuk layar yang dibuka lewat QR di meja kerja: lembar kerja dan
    lembar roll.

    Sengaja bukan kerangka etalase. Tukang jahit yang memindai QR mendapat
    keranjang belanja, tombol "Masuk", dan kaki halaman berisi alamat toko dan
    jam buka: tidak satu pun berguna di meja kerja, dan semuanya menambah
    gulungan sebelum sampai ke tombol yang dicari.

    Juga bukan kerangka panel: pekerja tidak punya akun panel (KEP-053), jadi
    menu Pesanan, Stok, dan Pengaturan tidak boleh muncul di sini sama sekali.
--}}
<body class="min-h-screen bg-paper antialiased">

<header class="sticky top-0 z-20 bg-navy-900 text-white" style="padding-top: env(safe-area-inset-top, 0px);">
    <div class="mx-auto flex h-14 max-w-lg items-center gap-3 px-5">
        <x-logo :terang="true" tinggi="h-8" />
        <span class="ml-auto text-xs font-medium text-white/50">@yield('kop', 'Lembar kerja')</span>
    </div>
</header>

<main class="mx-auto max-w-lg px-5 py-6" style="padding-bottom: calc(2rem + env(safe-area-inset-bottom, 0px));">
    @if (session('sukses'))
        <p class="notis notis-ok mb-5">{{ session('sukses') }}</p>
    @endif

    @if ($errors->any())
        <p class="notis notis-galat mb-5">{{ $errors->first() }}</p>
    @endif

    @yield('isi')
</main>

@stack('skrip')
</body>
</html>
