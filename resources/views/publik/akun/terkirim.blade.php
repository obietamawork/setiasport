@extends('layouts.publik')
@section('judul', 'Verifikasi email Anda')

@section('isi')
<div class="mx-auto max-w-md px-5 py-14">
    <h1 class="text-3xl font-extrabold">Verifikasi email Anda</h1>

    <div class="kartu mt-6 p-6">
        {{-- Kalimatnya sengaja tidak memastikan email terdaftar atau tidak, supaya
             halaman ini tidak bisa dipakai menebak email pelanggan (KEP-096). --}}
        <p class="leading-relaxed">
            Jika alamat email tersebut dapat digunakan untuk pendaftaran, kami telah mengirimkan
            tautan verifikasi ke alamat tersebut. Buka email dari kami dan klik tautan verifikasi
            untuk mengaktifkan akun Anda.
        </p>
        <p class="mt-4 text-sm leading-relaxed text-muted">
            Tautan verifikasi berlaku selama 24 jam. Jika email tidak ditemukan di kotak masuk,
            periksa folder Promosi atau Spam.
        </p>
    </div>

    {{-- Jalur tanpa akun tetap terbuka, jadi tidak ada yang tertahan menunggu email. --}}
    <div class="notis notis-info mt-6">
        <p class="font-semibold">Pesanan Anda tetap dapat diakses</p>
        <p class="mt-1">
            Setiap pesanan memiliki tautan khusus yang dikirim melalui email, sehingga Anda dapat
            memantau pesanan tanpa masuk ke akun.
            <a href="{{ route('lacak') }}" class="tautan">Lacak dengan kode pesanan</a>
        </p>
    </div>
</div>
@endsection
