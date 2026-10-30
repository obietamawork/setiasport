@extends('layouts.publik')
@section('judul', 'Aktifkan akun')

@section('isi')
<div class="mx-auto max-w-md px-5 py-14">
    <h1 class="text-3xl font-extrabold">Aktifkan akun</h1>
    <p class="mt-3 leading-relaxed text-muted">
        Satu langkah lagi untuk akun {{ $pelanggan->email_samar }}: ketik kata sandi
        yang Anda buat saat mendaftar.
    </p>

    {{-- Kata sandi diminta, bukan langsung aktif dari tautan (KEP-096): orang lain
         bisa saja mendaftar memakai email Anda dengan kata sandinya sendiri. --}}
    <form method="POST" action="{{ route('daftar.konfirmasi.proses', $token) }}" class="kartu mt-7 space-y-4 p-6">
        @csrf

        <div>
            <label for="password" class="label-form">Kata sandi</label>
            <input type="password" id="password" name="password"
                   class="kotak-input" required autofocus autocomplete="current-password">
        </div>

        <button type="submit" class="tombol tombol-utama w-full">Aktifkan akun</button>
    </form>

    <div class="notis notis-info mt-6">
        <p class="font-semibold">Tidak merasa mendaftar?</p>
        <p class="mt-1">
            Tutup saja halaman ini. Tanpa kata sandi yang dibuat saat mendaftar, akun ini
            tidak akan aktif, dan tautannya kedaluwarsa sendiri dalam 24 jam.
        </p>
    </div>
</div>
@endsection
