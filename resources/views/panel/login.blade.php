<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk staf | {{ setting('toko_nama', 'Setia Sport') }}</title>
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
<body class="min-h-screen bg-navy-900">

{{-- Dua kolom di layar lebar. Kartu yang mengambang sendirian di tengah bidang
     navy terasa belum jadi; sisi kiri diisi konteks yang memang berguna. --}}
{{-- Grid selebar layar; isi tiap kolom yang dibatasi, bukan pembungkusnya.
     Kalau dibatasi di luar, sisi kertas berhenti sebelum tepi dan terlihat
     seperti salah potong. --}}
<div class="grid min-h-screen lg:grid-cols-2">

    <div class="hidden flex-col justify-between p-12 text-white lg:flex xl:p-16">
        <a href="{{ route('beranda') }}">
            <x-logo :terang="true" tinggi="h-12" />
        </a>

        <div>
            <p class="text-3xl leading-tight font-extrabold">
                Panel internal
                <span class="block text-navy-300">{{ setting('toko_nama', 'Setia Sport') }}</span>
            </p>

            <ul class="mt-8 space-y-3 text-sm text-white/70">
                @foreach ([
                    'Terima dan verifikasi pesanan yang masuk',
                    'Kerjakan antrian desain, printing, jahit, sampai packing',
                    'Serahkan barang dan catat pelunasan di kasir',
                    'Kelola katalog, stok, dan harga',
                ] as $baris)
                    <li class="flex gap-3">
                        <span class="tahap-titik tahap-titik-lewat mt-2 shrink-0 bg-kuning"></span>
                        <span>{{ $baris }}</span>
                    </li>
                @endforeach
            </ul>
        </div>

        <p class="text-xs leading-relaxed text-white/60">
            Pelanggan tidak memakai halaman ini. Mereka membuka pesanannya lewat tautan
            yang dikirim ke email, tanpa perlu akun.
        </p>
    </div>

    <div class="flex items-center justify-center p-6 lg:bg-paper lg:p-12 xl:p-16">
        <div class="w-full max-w-sm">
            <div class="mb-8 lg:hidden">
                <a href="{{ route('beranda') }}"><x-logo :terang="true" /></a>
                <p class="mt-2 text-sm font-medium text-white/70">Panel internal</p>
            </div>

            <form method="POST" action="{{ route('panel.masuk') }}" class="kartu space-y-4 p-6">
                @csrf

                <div>
                    <h1 class="text-xl font-extrabold">Masuk</h1>
                    <p class="mt-1 text-sm text-muted">Pakai akun staf yang diberikan pemilik toko.</p>
                </div>

                @if ($errors->any())
                    <p class="notis notis-galat">{{ $errors->first() }}</p>
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

                {{-- Tidak ada pemulihan kata sandi lewat email dengan sengaja: ia
                     bergantung pada cron dan Resend, yang justru bisa ikut
                     bermasalah. Jalur pemulihannya lewat perintah di server. --}}
                <p class="border-t border-line pt-4 text-xs leading-relaxed text-muted">
                    Lupa kata sandi? Pemilik toko dapat mengaturnya ulang lewat server.
                    Caranya ada di dokumen serah terima.
                </p>
            </form>

            <p class="mt-5 text-center text-xs leading-relaxed text-white/50 lg:text-muted">
                Bukan staf?
                {{-- Di HP tautan ini berada di atas latar navy-900: warna bawaan
                     .tautan (navy-700) di sana kontrasnya cuma 1,5:1, nyaris tak terlihat. --}}
                <a href="{{ route('lacak') }}" class="tautan text-white lg:text-navy-700">Lacak pesanan Anda di sini</a>
            </p>
        </div>
    </div>
</div>

</body>
</html>
