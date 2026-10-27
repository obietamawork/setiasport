<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Ruang CS | Panel {{ setting('toko_nama', 'Setia Sport') }}</title>
    <meta name="theme-color" content="#0d1733">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <meta name="robots" content="noindex">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
{{-- Ruang CS (KEP-031) sengaja tidak memakai tata letak panel. Panel dipakai
     dengan berpindah menu untuk menyelesaikan tugas; ruang CS dibuka seharian
     dan menunggu pesan datang, jadi setiap piksel vertikal diberikan ke
     percakapan, bukan ke sidebar dan judul halaman. Akun dan izinnya tetap
     sama dengan panel. --}}
<body class="h-dvh overflow-hidden bg-paper text-ink antialiased">
    @yield('isi')
</body>
</html>
