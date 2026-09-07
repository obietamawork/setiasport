@extends('layouts.publik')
@section('judul', 'Masuk')

@section('isi')
<div class="mx-auto max-w-md px-5 py-14">
    <h1 class="text-3xl font-extrabold">Masuk</h1>
    <p class="mt-3 leading-relaxed text-muted">
        Punya akun? Masuk untuk melihat semua pesanan Anda dalam satu halaman.
    </p>

    <form method="POST" action="{{ route('masuk.proses') }}" class="kartu mt-7 space-y-4 p-6">
        @csrf

        <div>
            <label for="email" class="label-form">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}"
                   class="kotak-input" required autofocus autocomplete="email">
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

        <p class="border-t border-line pt-4 text-sm text-muted">
            Belum punya akun?
            <a href="{{ route('daftar') }}" class="tautan">Buat akun</a>
        </p>
    </form>

    {{-- Jalur tanpa akun harus tetap terlihat di sini, bukan disembunyikan.
         Memaksa pendaftaran adalah persis yang ingin dihindari (KEP-020). --}}
    <div class="notis notis-info mt-6">
        <p class="font-semibold">Tidak punya akun? Tidak masalah.</p>
        <p class="mt-1">
            Setiap pesanan punya tautan sendiri yang dikirim ke email Anda, dan tautan itu
            langsung membuka halaman pesanannya tanpa perlu masuk.
            <a href="{{ route('lacak') }}" class="tautan">Lacak dengan kode pesanan</a>
        </p>
    </div>
</div>
@endsection
