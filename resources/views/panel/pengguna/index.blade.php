@extends('layouts.panel')
@section('judul', 'Pengguna')

@section('isi')
<x-kop judul="Pengguna"
       catatan="Akun panel untuk staf toko. Akun tidak dihapus, hanya dinonaktifkan, supaya riwayat aktivitasnya tetap bernama." />

@php $saya = auth('web')->user(); @endphp

<div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-start">
    <section class="kartu overflow-hidden">
        <h2 class="kartu-kop">Daftar akun</h2>

        <div class="divide-y divide-line">
            @foreach ($daftar as $u)
                @php $diriSendiri = $u->is($saya); @endphp
                <div class="p-5" x-data="{ panel: null }">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="flex flex-wrap items-center gap-2 font-semibold">
                                {{ $u->name }}
                                @if ($diriSendiri)
                                    <span class="chip chip-netral">Anda</span>
                                @endif
                                @foreach ($u->roles as $r)
                                    <span class="chip {{ $r->slug === 'owner' ? 'chip-navy' : 'chip-netral' }}">{{ $r->nama }}</span>
                                @endforeach
                                @unless ($u->aktif)
                                    <span class="chip chip-netral">Nonaktif</span>
                                @endunless
                            </p>
                            <p class="mt-0.5 text-sm wrap-break-word text-muted">{{ $u->email }}</p>
                            <p class="mt-1 text-xs text-muted">
                                {{ $u->login_terakhir_pada ? 'Terakhir masuk '.$u->login_terakhir_pada->translatedFormat('j M Y, H:i') : 'Belum pernah masuk' }}
                            </p>
                            @if ($u->sandi_bawaan)
                                <p class="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold text-merah">
                                    <x-ikon nama="peringatan" ukuran="size-3.5" />
                                    Masih memakai kata sandi bawaan dari dokumentasi
                                </p>
                            @elseif ($u->wajib_ganti_sandi)
                                <p class="mt-2 text-xs font-medium text-warn">Kata sandi sementara, belum diganti pemiliknya</p>
                            @endif
                        </div>

                        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
                            <button type="button" class="tautan py-1" @click="panel = panel === 'ubah' ? null : 'ubah'"
                                    :aria-expanded="(panel === 'ubah').toString()">Ubah</button>
                            @unless ($diriSendiri)
                                <button type="button" class="tautan py-1" @click="panel = panel === 'sandi' ? null : 'sandi'"
                                        :aria-expanded="(panel === 'sandi').toString()">Atur ulang sandi</button>
                                <button type="button" class="tautan py-1 {{ $u->aktif ? 'text-merah' : '' }}"
                                        @click="panel = panel === 'aktif' ? null : 'aktif'"
                                        :aria-expanded="(panel === 'aktif').toString()">
                                    {{ $u->aktif ? 'Nonaktifkan' : 'Aktifkan' }}
                                </button>
                            @else
                                <a href="{{ route('panel.akun') }}" class="tautan py-1">Ganti sandi saya</a>
                            @endunless
                        </div>
                    </div>

                    {{-- Ubah data --}}
                    <form method="POST" action="{{ route('panel.pengguna.update', $u) }}"
                          x-show="panel === 'ubah'" x-cloak class="mt-4 space-y-3 border-t border-line pt-4">
                        @csrf @method('PUT')
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="block">
                                <span class="label-form">Nama</span>
                                <input type="text" name="name" value="{{ $u->name }}" class="kotak-input" maxlength="100" required>
                            </label>
                            <label class="block">
                                <span class="label-form">Email untuk masuk</span>
                                <input type="email" name="email" value="{{ $u->email }}" class="kotak-input" maxlength="150" required>
                            </label>
                        </div>
                        <label class="block sm:max-w-xs">
                            <span class="label-form">Peran</span>
                            <select name="peran" class="kotak-input">
                                @foreach ($peran as $r)
                                    <option value="{{ $r->slug }}" @selected($u->punyaPeran($r->slug))>{{ $r->nama }}</option>
                                @endforeach
                            </select>
                        </label>
                        <button type="submit" class="tombol tombol-navy tombol-sm">Simpan</button>
                    </form>

                    @unless ($diriSendiri)
                        {{-- Atur ulang kata sandi --}}
                        <form method="POST" action="{{ route('panel.pengguna.sandi', $u) }}"
                              x-show="panel === 'sandi'" x-cloak class="mt-4 space-y-3 border-t border-line pt-4">
                            @csrf
                            <label class="block sm:max-w-xs">
                                <span class="label-form">Kata sandi sementara</span>
                                <input type="text" name="password" class="kotak-input" minlength="10" required autocomplete="off">
                            </label>
                            <p class="text-xs leading-relaxed text-muted">
                                Minimal 10 karakter, berisi huruf dan angka. {{ $u->name }} langsung dikeluarkan dari semua
                                perangkat dan wajib menggantinya sendiri saat masuk lagi.
                            </p>
                            <button type="submit" class="tombol tombol-navy tombol-sm">Atur kata sandi sementara</button>
                        </form>

                        {{-- Konfirmasi di halaman, bukan dialog bawaan browser. --}}
                        <form method="POST" action="{{ route('panel.pengguna.aktif', $u) }}"
                              x-show="panel === 'aktif'" x-cloak class="mt-4 border-t border-line pt-4">
                            @csrf
                            <p class="text-sm">
                                @if ($u->aktif)
                                    Nonaktifkan {{ $u->name }}? Ia langsung dikeluarkan dari panel dan tidak bisa masuk lagi
                                    sampai diaktifkan. Riwayat aktivitasnya tetap tersimpan.
                                @else
                                    Aktifkan lagi {{ $u->name }}? Ia bisa masuk dengan kata sandinya yang terakhir.
                                @endif
                            </p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <button type="submit" class="tombol tombol-sm {{ $u->aktif ? 'tombol-utama' : 'tombol-navy' }}">
                                    {{ $u->aktif ? 'Ya, nonaktifkan' : 'Ya, aktifkan' }}
                                </button>
                                <button type="button" class="tombol tombol-garis tombol-sm" @click="panel = null">Batal</button>
                            </div>
                        </form>
                    @endunless
                </div>
            @endforeach
        </div>
    </section>

    <section class="kartu p-5">
        <h2 class="font-semibold">Tambah pengguna</h2>
        <p class="mt-1 text-sm leading-relaxed text-muted">
            Satu akun untuk satu orang, supaya catatan "siapa memverifikasi apa" tetap berarti.
        </p>

        <form method="POST" action="{{ route('panel.pengguna.simpan') }}" class="mt-4 space-y-3">
            @csrf

            <div>
                <label for="baru-nama" class="label-form">Nama</label>
                <input type="text" id="baru-nama" name="name" value="{{ old('name') }}" class="kotak-input" maxlength="100" required>
            </div>

            <div>
                <label for="baru-email" class="label-form">Email untuk masuk</label>
                <input type="email" id="baru-email" name="email" value="{{ old('email') }}" class="kotak-input" maxlength="150" required>
            </div>

            <div>
                <label for="baru-peran" class="label-form">Peran</label>
                <select id="baru-peran" name="peran" class="kotak-input">
                    @foreach ($peran as $r)
                        <option value="{{ $r->slug }}" @selected(old('peran', 'admin') === $r->slug)>{{ $r->nama }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs leading-relaxed text-muted">
                    Admin menjalankan operasi harian. Pemilik juga bisa membuka Pengaturan, Laporan, dan halaman ini.
                </p>
            </div>

            <div>
                <label for="baru-sandi" class="label-form">Kata sandi sementara</label>
                <input type="text" id="baru-sandi" name="password" class="kotak-input" minlength="10" required autocomplete="off">
                <p class="mt-1 text-xs leading-relaxed text-muted">
                    Minimal 10 karakter, berisi huruf dan angka. Berikan langsung ke orangnya; ia wajib menggantinya
                    saat pertama masuk.
                </p>
            </div>

            <button type="submit" class="tombol tombol-navy w-full">Tambah pengguna</button>
        </form>
    </section>
</div>
@endsection
