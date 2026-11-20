@extends('layouts.publik')
@section('judul', 'Lupa kata sandi')

@section('isi')
<div class="mx-auto max-w-md px-5 py-14">
    <h1 class="text-3xl font-extrabold">Lupa kata sandi</h1>

    @if (session('terkirim'))
        {{-- Kalimatnya sama untuk email terdaftar maupun tidak (KEP-157). --}}
        <div class="kartu mt-6 p-6">
            <p class="font-semibold">Periksa email Anda</p>
            <p class="mt-2 leading-relaxed">
                Jika alamat email tersebut terdaftar, kami telah mengirimkan tautan untuk membuat
                kata sandi baru. Tautan berlaku {{ \App\Models\Customer::MENIT_ATUR_SANDI }} menit
                dan hanya dapat digunakan satu kali.
            </p>
            <p class="mt-4 text-sm leading-relaxed text-muted">
                Jika email tidak ditemukan di kotak masuk, periksa folder Promosi atau Spam.
            </p>
        </div>
        <p class="mt-6 text-sm">
            <a href="{{ route('masuk') }}" class="tautan">Kembali ke halaman masuk</a>
        </p>
    @else
        <p class="mt-3 leading-relaxed text-muted">
            Masukkan email akun Anda. Kami akan mengirimkan tautan untuk membuat kata sandi baru.
        </p>

        <form method="POST" action="{{ route('lupa-sandi.kirim') }}" class="kartu mt-7 space-y-4 p-6">
            @csrf
            <div>
                <label for="email" class="label-form">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}"
                       class="kotak-input" required autofocus autocomplete="email">
            </div>

            <button type="submit" class="tombol tombol-utama w-full">Kirim tautan</button>

            <p class="border-t border-line pt-4 text-sm text-muted">
                Ingat kata sandinya? <a href="{{ route('masuk') }}" class="tautan">Masuk</a>
            </p>
        </form>
    @endif
</div>
@endsection
