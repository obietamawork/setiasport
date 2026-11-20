@extends('layouts.panel')
@section('judul', $pelanggan->nama.' | Pelanggan')

@section('isi')
<x-kop :judul="$pelanggan->nama" :kembali="route('panel.pelanggan.index')" kembali-label="Pelanggan"
       :catatan="$pelanggan->aktif === false
           ? 'Akun nonaktif: pelanggan tidak bisa masuk, tetapi tautan pesanannya tetap berlaku.'
           : $pelanggan->email.' · '.$pelanggan->no_hp" />

<div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-start">
    <div class="space-y-6">
        {{-- Data dan catatan --}}
        <section class="kartu p-5">
            <h2 class="font-bold">Data pelanggan</h2>
            <form method="POST" action="{{ route('panel.pelanggan.ubah', $pelanggan) }}" class="mt-4 space-y-4">
                @csrf @method('PUT')
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="nama" class="label-form">Nama</label>
                        <input type="text" id="nama" name="nama" value="{{ old('nama', $pelanggan->nama) }}" class="kotak-input" maxlength="100" required>
                    </div>
                    <div>
                        <label for="no_hp" class="label-form">Nomor HP</label>
                        <input type="tel" id="no_hp" name="no_hp" value="{{ old('no_hp', $pelanggan->no_hp) }}" class="kotak-input" maxlength="20" required>
                    </div>
                </div>
                <div>
                    <label for="email" class="label-form">Email</label>
                    <input type="email" id="email" name="email" value="{{ old('email', $pelanggan->email) }}" class="kotak-input" maxlength="150" required>
                    <p class="bantuan-form">Email mengikat pesanan dan akun. Ganti hanya atas permintaan pelanggan yang sudah dipastikan, misalnya lewat chat CS.</p>
                </div>
                <div>
                    <label for="catatan" class="label-form">Catatan internal</label>
                    <textarea id="catatan" name="catatan" rows="3" maxlength="1000" class="kotak-input"
                              placeholder="Hanya terlihat oleh tim. Contoh: pelatih SSB, pesan tiap awal semester.">{{ old('catatan', $pelanggan->catatan) }}</textarea>
                </div>
                <button type="submit" class="tombol tombol-navy">Simpan</button>
            </form>
        </section>

        {{-- Pesanan --}}
        <section class="kartu overflow-hidden">
            <h2 class="kartu-kop">Pesanan ({{ $jumlahPesanan }})</h2>
            @if ($pesanan->isEmpty())
                <p class="p-5 text-sm text-muted">Belum ada pesanan.</p>
            @else
                <div class="divide-y divide-line">
                    @foreach ($pesanan as $o)
                        <a href="{{ route('panel.pesanan.show', $o) }}" class="flex items-center justify-between gap-3 p-4 hover:bg-paper">
                            <div>
                                <p class="tabular font-semibold">{{ $o->kode }}</p>
                                <p class="text-xs text-muted">{{ $o->created_at?->translatedFormat('j M Y, H.i') }}</p>
                            </div>
                            <span class="chip {{ $o->status->warna() }}">{{ $o->status->label() }}</span>
                        </a>
                    @endforeach
                </div>
                @if ($jumlahPesanan > $pesanan->count())
                    <p class="border-t border-line p-4 text-sm">
                        <a href="{{ route('panel.pesanan.index', ['q' => $pelanggan->email]) }}" class="tautan">Lihat semua pesanan</a>
                    </p>
                @endif
            @endif
        </section>
    </div>

    <aside class="space-y-6">
        {{-- Akun --}}
        <section class="kartu p-5">
            <h2 class="font-bold">Akun</h2>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between gap-3">
                    <dt class="text-muted">Status</dt>
                    <dd>
                        @if ($pelanggan->aktif === false)
                            <span class="chip chip-merah">Nonaktif</span>
                        @elseif ($pelanggan->akunAktif())
                            <span class="chip chip-hijau">Akun aktif</span>
                        @else
                            <span class="chip chip-netral">Tamu</span>
                        @endif
                    </dd>
                </div>
                @if ($pelanggan->akun_dibuat_pada)
                    <div class="flex justify-between gap-3"><dt class="text-muted">Akun dibuat</dt><dd>{{ $pelanggan->akun_dibuat_pada->translatedFormat('j M Y') }}</dd></div>
                @endif
                @if ($pelanggan->login_terakhir_pada)
                    <div class="flex justify-between gap-3"><dt class="text-muted">Terakhir masuk</dt><dd>{{ $pelanggan->login_terakhir_pada->translatedFormat('j M Y, H.i') }}</dd></div>
                @endif
            </dl>

            <div class="mt-4 space-y-2 border-t border-line pt-4">
                @if ($pelanggan->aktif !== false)
                    <form method="POST" action="{{ route('panel.pelanggan.atur-sandi', $pelanggan) }}">
                        @csrf
                        <button type="submit" class="tombol tombol-garis w-full">
                            {{ $pelanggan->akunAktif() ? 'Kirim tautan atur ulang sandi' : 'Kirim tautan buat kata sandi' }}
                        </button>
                    </form>
                    <p class="bantuan-form">Tautan dikirim ke {{ $pelanggan->email }} dan berlaku {{ \App\Models\Customer::MENIT_ATUR_SANDI }} menit.</p>
                    <form method="POST" action="{{ route('panel.pelanggan.nonaktifkan', $pelanggan) }}"
                          data-konfirmasi="Nonaktifkan akun {{ $pelanggan->nama }}?"
                          data-konfirmasi-pesan="Pelanggan tidak bisa masuk sampai akunnya diaktifkan kembali. Tautan pesanan di emailnya tetap berlaku."
                          data-konfirmasi-tombol="Nonaktifkan">
                        @csrf
                        <button type="submit" class="tautan text-sm text-merah">Nonaktifkan akun</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('panel.pelanggan.aktifkan', $pelanggan) }}">
                        @csrf
                        <button type="submit" class="tombol tombol-navy w-full">Aktifkan kembali</button>
                    </form>
                @endif
            </div>
        </section>

        {{-- Alamat --}}
        <section class="kartu p-5">
            <h2 class="font-bold">Buku alamat</h2>
            @forelse ($daftarAlamat as $a)
                <div class="mt-3 border-t border-line pt-3 text-sm first:border-0 first:pt-0">
                    <p class="font-semibold">{{ $a->label }} @if ($a->utama)<span class="chip chip-netral">Utama</span>@endif</p>
                    <p class="mt-0.5 leading-relaxed">{{ $a->alamat }}</p>
                    @if ($a->area_nama)
                        <p class="text-xs text-muted">{{ $a->area_nama }}</p>
                    @endif
                </div>
            @empty
                <p class="mt-2 text-sm text-muted">Belum ada alamat tersimpan.</p>
            @endforelse
        </section>
    </aside>
</div>
@endsection
