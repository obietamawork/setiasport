@extends('layouts.publik')
@section('judul', 'Buat akun')

@section('isi')
<div class="mx-auto max-w-md px-5 py-14">
    <h1 class="text-3xl font-extrabold">Buat akun</h1>
    <p class="kolom-baca mt-3 leading-relaxed text-muted">
        Berguna kalau Anda memesan berulang kali. Kami akan mengirim tautan konfirmasi
        ke email Anda, dan setelah dikonfirmasi seluruh pesanan yang pernah dibuat dengan
        email itu ikut tercatat di akun Anda.
    </p>

    <form method="POST" action="{{ route('daftar.proses') }}" class="kartu mt-7 space-y-4 p-6">
        @csrf

        <div>
            <label for="nama" class="label-form">Nama</label>
            <input type="text" id="nama" name="nama" value="{{ old('nama') }}"
                   class="kotak-input" required autofocus autocomplete="name">
        </div>

        <div>
            <label for="email" class="label-form">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email', $email) }}"
                   class="kotak-input" required autocomplete="email">
        </div>

        <div>
            <label for="no_hp" class="label-form">Nomor HP</label>
            <input type="tel" id="no_hp" name="no_hp" value="{{ old('no_hp') }}"
                   class="kotak-input tabular" required autocomplete="tel">
        </div>

        <div>
            <label for="password" class="label-form">Kata sandi</label>
            <input type="password" id="password" name="password"
                   class="kotak-input" required autocomplete="new-password">
            <p class="bantuan-form">Minimal 8 karakter.</p>
        </div>

        <div>
            <label for="password_confirmation" class="label-form">Ulangi kata sandi</label>
            <input type="password" id="password_confirmation" name="password_confirmation"
                   class="kotak-input" required autocomplete="new-password">
        </div>

        <p class="text-xs leading-relaxed text-muted">
            Dengan membuat akun, Anda menyetujui
            <a href="{{ route('syarat-ketentuan') }}" target="_blank" rel="noopener" class="tautan">Syarat &amp; Ketentuan</a>
            dan
            <a href="{{ route('kebijakan-privasi') }}" target="_blank" rel="noopener" class="tautan">Kebijakan Privasi</a>
            kami.
        </p>

        <button type="submit" class="tombol tombol-utama w-full">Kirim tautan konfirmasi</button>

        <p class="border-t border-line pt-4 text-sm text-muted">
            Sudah punya akun?
            <a href="{{ route('masuk') }}" class="tautan">Masuk</a>
        </p>
    </form>
</div>
@endsection
