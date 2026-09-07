<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk staf | {{ setting('toko_nama', 'Setia Sport') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,400..800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-navy-900 px-5 py-10">

<div class="w-full max-w-sm">
    <a href="{{ route('beranda') }}" class="block"><x-logo :terang="true" /></a>
    <p class="mt-2 text-sm font-medium text-white/70">Panel internal</p>

    <form method="POST" action="{{ route('panel.masuk') }}" class="kartu mt-6 space-y-4 p-6">
        @csrf

        @if ($errors->any())
            <p class="notis notis-galat">
                {{ $errors->first() }}
            </p>
        @endif

        <div>
            <label for="email" class="label-form">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}"
                   class="kotak-input" required autofocus autocomplete="username">
        </div>

        <div>
            <label for="password" class="label-form">Kata sandi</label>
            <input type="password" id="password" name="password"
                   class="kotak-input" required autocomplete="current-password">
        </div>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="ingat" value="1" class="size-4 accent-navy">
            Ingat saya di perangkat ini
        </label>

        <button type="submit" class="tombol tombol-utama w-full">Masuk</button>
    </form>

    <p class="mt-4 text-xs leading-relaxed text-white/60">
        Halaman ini hanya untuk staf toko. Pelanggan melacak pesanannya lewat tautan
        yang dikirim ke email, tanpa perlu akun.
    </p>
</div>

</body>
</html>
