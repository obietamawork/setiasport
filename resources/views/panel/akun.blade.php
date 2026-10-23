@extends('layouts.panel')
@section('judul', 'Akun saya')

@section('isi')
<x-kop judul="Akun saya"
       catatan="Nama, email untuk masuk, dan kata sandi Anda sendiri. Kata sandi tidak bisa dilihat siapa pun, termasuk pemilik toko." />

@if ($user->masihSandiBawaan())
    <div class="notis notis-galat mt-1 mb-6">
        <p class="font-semibold">Akun ini masih memakai kata sandi bawaan</p>
        <p class="mt-0.5 text-sm">Kata sandi itu tertulis di dokumentasi pemasangan, jadi siapa pun yang membacanya bisa masuk. Ganti sekarang.</p>
    </div>
@elseif ($user->wajib_ganti_sandi)
    <div class="notis notis-perhatian mt-1 mb-6">
        <p class="font-semibold">Kata sandi Anda masih sementara</p>
        <p class="mt-0.5 text-sm">Kata sandi ini diatur pemilik toko. Ganti dengan milik Anda sendiri sebelum memakai panel.</p>
    </div>
@endif

<div class="mt-6 grid gap-6 lg:grid-cols-2 lg:items-start">
    <section class="kartu p-5">
        <h2 class="font-semibold">Ganti kata sandi</h2>
        <p class="mt-1 text-sm text-muted">Perangkat lain yang masih masuk dengan akun ini otomatis dikeluarkan.</p>

        <form method="POST" action="{{ route('panel.akun.sandi') }}" class="mt-4 space-y-4">
            @csrf @method('PUT')

            <div>
                <label for="sandi-sekarang" class="label-form">Kata sandi sekarang</label>
                <input type="password" id="sandi-sekarang" name="password_sekarang" class="kotak-input"
                       autocomplete="current-password" required>
                @error('password_sekarang', 'sandi')
                    <p class="mt-1 text-xs font-medium text-merah">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="sandi-baru" class="label-form">Kata sandi baru</label>
                <input type="password" id="sandi-baru" name="password" class="kotak-input"
                       autocomplete="new-password" minlength="10" required aria-describedby="sandi-baru-bantu">
                <p id="sandi-baru-bantu" class="mt-1 text-xs text-muted">Minimal 10 karakter, berisi huruf dan angka.</p>
                @error('password', 'sandi')
                    <p class="mt-1 text-xs font-medium text-merah">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="sandi-ulang" class="label-form">Ulangi kata sandi baru</label>
                <input type="password" id="sandi-ulang" name="password_confirmation" class="kotak-input"
                       autocomplete="new-password" minlength="10" required>
            </div>

            <button type="submit" class="tombol tombol-navy">Ganti kata sandi</button>
        </form>
    </section>

    <section class="kartu p-5">
        <h2 class="font-semibold">Profil</h2>
        <p class="mt-1 text-sm text-muted">
            @if ($user->login_terakhir_pada)
                Terakhir masuk {{ $user->login_terakhir_pada->translatedFormat('j M Y, H:i') }}.
            @endif
            Peran: {{ $user->roles->pluck('nama')->implode(', ') ?: 'belum ada' }}.
        </p>

        <form method="POST" action="{{ route('panel.akun.profil') }}" class="mt-4 space-y-4"
              x-data="{ emailAwal: @js($user->email), email: @js(old('email', $user->email)) }">
            @csrf @method('PUT')

            <div>
                <label for="profil-nama" class="label-form">Nama</label>
                <input type="text" id="profil-nama" name="name" value="{{ old('name', $user->name) }}"
                       class="kotak-input" maxlength="100" autocomplete="name" required>
            </div>

            <div>
                <label for="profil-email" class="label-form">Email untuk masuk</label>
                <input type="email" id="profil-email" name="email" x-model="email"
                       value="{{ old('email', $user->email) }}" class="kotak-input" maxlength="150"
                       autocomplete="email" required>
            </div>

            <div>
                <label for="profil-hp" class="label-form">Nomor HP (opsional)</label>
                <input type="tel" id="profil-hp" name="no_hp" value="{{ old('no_hp', $user->no_hp) }}"
                       class="kotak-input tabular" maxlength="20" autocomplete="tel">
            </div>

            {{-- Hanya muncul kalau email diganti: email adalah nama masuk, jadi
                 menggantinya butuh bukti bahwa ini memang pemilik akunnya. --}}
            <div x-show="email !== emailAwal" x-cloak>
                <label for="profil-sandi" class="label-form">Kata sandi sekarang</label>
                <input type="password" id="profil-sandi" name="password_sekarang" class="kotak-input"
                       autocomplete="current-password">
                <p class="mt-1 text-xs text-muted">Diperlukan karena email untuk masuk ikut berubah.</p>
            </div>

            <button type="submit" class="tombol tombol-garis">Simpan profil</button>
        </form>
    </section>
</div>
@endsection
