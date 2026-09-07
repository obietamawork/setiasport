@extends('layouts.publik')
@section('judul', 'Cek email Anda')

@section('isi')
<div class="mx-auto max-w-md px-5 py-14">
    <h1 class="text-3xl font-extrabold">Cek email Anda</h1>

    <div class="kartu mt-6 p-6">
        <p class="leading-relaxed">
            Kalau alamat email itu bisa dipakai untuk membuat akun, kami sudah mengirim
            tautan konfirmasi ke sana. Klik tautannya untuk mengaktifkan akun Anda.
        </p>
        <p class="mt-4 text-sm leading-relaxed text-muted">
            Tautan berlaku 24 jam. Kalau tidak ada di kotak masuk, periksa folder promosi
            atau spam.
        </p>
    </div>

    {{-- Jalur tanpa akun tetap terbuka, jadi tidak ada yang tertahan menunggu email. --}}
    <div class="notis notis-info mt-6">
        <p class="font-semibold">Tidak perlu menunggu untuk melihat pesanan</p>
        <p class="mt-1">
            Setiap pesanan punya tautannya sendiri yang dikirim ke email, dan itu langsung
            membuka halaman pesanan tanpa akun.
            <a href="{{ route('lacak') }}" class="tautan">Lacak dengan kode pesanan</a>
        </p>
    </div>
</div>
@endsection
