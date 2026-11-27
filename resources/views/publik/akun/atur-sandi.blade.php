@extends('layouts.publik')
@section('judul', 'Buat kata sandi baru')

@section('isi')
<div class="mx-auto max-w-md px-5 py-14">
    <h1 class="judul-tampil text-[2.5rem]">Kata sandi baru</h1>
    <p class="mt-3 leading-relaxed text-muted">
        Buat kata sandi baru untuk akun {{ $pelanggan->email_samar }}. Minimal 8 karakter.
    </p>

    <form method="POST" action="{{ route('atur-sandi.simpan', $token) }}" class="kartu mt-7 space-y-4 p-6">
        @csrf
        <div>
            <label for="password" class="label-form">Kata sandi baru</label>
            <input type="password" id="password" name="password" class="kotak-input"
                   required minlength="8" autofocus autocomplete="new-password">
        </div>
        <div>
            <label for="password_confirmation" class="label-form">Ulangi kata sandi baru</label>
            <input type="password" id="password_confirmation" name="password_confirmation" class="kotak-input"
                   required minlength="8" autocomplete="new-password">
        </div>

        <button type="submit" class="tombol tombol-navy w-full">Simpan dan masuk</button>
    </form>
</div>
@endsection
