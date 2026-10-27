<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('judul') | {{ config('app.name') }}</title>
    <meta name="robots" content="noindex">
    @vite(['resources/css/app.css'])
</head>
{{-- Halaman ini sengaja tidak menyentuh basis data sama sekali. Sebagian galat
     justru terjadi karena basis datanya bermasalah, dan halaman galat yang ikut
     gagal adalah cara paling buruk menyambut pengunjung. --}}
<body class="flex min-h-screen flex-col bg-paper">

<header class="border-b border-line bg-field">
    <div class="mx-auto flex h-16 max-w-6xl items-center px-5">
        <a href="/" class="wordmark text-base whitespace-nowrap sm:text-xl" aria-label="Beranda">
            <span>SETIA SPORT</span>
            <span class="wordmark-kota hidden sm:inline">WONOGIRI</span>
        </a>
    </div>
</header>

<main class="flex flex-1 items-center justify-center px-5 py-16">
    <div class="w-full max-w-md text-center">
        <p class="numeral text-6xl text-navy-400">@yield('kode')</p>
        <h1 class="mt-5 text-2xl font-extrabold">@yield('judul')</h1>
        <p class="mt-3 leading-relaxed text-muted">@yield('pesan')</p>

        <div class="kelompok-tombol mt-8 justify-center">
            @yield('aksi')
        </div>
    </div>
</main>

<footer class="border-t border-line bg-field">
    <p class="mx-auto max-w-6xl px-5 py-4 text-xs text-muted">
        &copy; {{ date('Y') }} {{ config('app.name') }}
    </p>
</footer>

</body>
</html>
