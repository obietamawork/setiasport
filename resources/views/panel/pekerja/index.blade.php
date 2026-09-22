@extends('layouts.panel')
@section('judul', 'Pekerja konveksi')

@section('isi')
<x-kop judul="Pekerja konveksi"
       catatan="Yang memindai QR di lembar kerja. Mereka tidak punya akun panel dan tidak bisa melihat harga atau data pelanggan, hanya menandai tahap yang baru selesai." />

<div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-start">
    <section class="kartu overflow-hidden">
        <h2 class="kartu-kop">Daftar pekerja</h2>

        @if ($daftar->isEmpty())
            <p class="p-5 text-sm leading-relaxed text-muted">
                Belum ada pekerja terdaftar. Selama daftarnya kosong, QR di lembar kerja
                tidak bisa dipakai menandai apa pun.
            </p>
        @else
            <div class="divide-y divide-line">
                @foreach ($daftar as $p)
                    <div class="p-5" x-data="{ ubah: false }">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold">
                                    {{ $p->nama }}
                                    @unless ($p->aktif)
                                        <span class="chip chip-netral">Nonaktif</span>
                                    @endunless
                                </p>
                                <p class="mt-0.5 text-sm text-muted">{{ $p->labelTahap() }}</p>
                                @if ($p->catatan)
                                    <p class="mt-1 text-xs text-muted">{{ $p->catatan }}</p>
                                @endif
                            </div>

                            <div class="flex items-center gap-3">
                                <button type="button" @click="ubah = !ubah" class="tautan text-sm">Ubah</button>
                                <form method="POST" action="{{ route('panel.pekerja.nonaktif', $p) }}">
                                    @csrf
                                    <button type="submit" class="tautan text-sm text-muted">
                                        {{ $p->aktif ? 'Nonaktifkan' : 'Aktifkan' }}
                                    </button>
                                </form>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('panel.pekerja.update', $p) }}"
                              x-show="ubah" x-cloak class="mt-4 space-y-3 border-t border-line pt-4">
                            @csrf @method('PUT')

                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label for="nama-{{ $p->id }}" class="label-form">Nama</label>
                                    <input type="text" id="nama-{{ $p->id }}" name="nama" value="{{ $p->nama }}"
                                           class="kotak-input" maxlength="100" required>
                                </div>
                                <div>
                                    <label for="pin-{{ $p->id }}" class="label-form">PIN baru (opsional)</label>
                                    <input type="text" id="pin-{{ $p->id }}" name="pin" inputmode="numeric"
                                           class="kotak-input tabular" maxlength="8" placeholder="Kosongkan kalau tidak diganti">
                                </div>
                            </div>

                            <fieldset>
                                <legend class="label-form">Bagian yang dipegang</legend>
                                <div class="flex flex-wrap gap-3">
                                    @foreach ($tahap as $t)
                                        <label class="flex items-center gap-2 text-sm">
                                            <input type="checkbox" name="tahap[]" value="{{ $t->value }}"
                                                   class="size-4 accent-navy" @checked(in_array($t->value, $p->tahap ?? [], true))>
                                            {{ $t->label() }}
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>

                            <div>
                                <label for="catatan-{{ $p->id }}" class="label-form">Catatan (opsional)</label>
                                <input type="text" id="catatan-{{ $p->id }}" name="catatan" value="{{ $p->catatan }}"
                                       class="kotak-input" maxlength="200">
                            </div>

                            <button type="submit" class="tombol tombol-navy tombol-sm">Simpan</button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <section class="kartu p-5">
        <h2 class="font-semibold">Tambah pekerja</h2>

        <form method="POST" action="{{ route('panel.pekerja.simpan') }}" class="mt-4 space-y-3">
            @csrf

            <div>
                <label for="nama" class="label-form">Nama</label>
                <input type="text" id="nama" name="nama" value="{{ old('nama') }}"
                       class="kotak-input" maxlength="100" required>
            </div>

            <div>
                <label for="pin" class="label-form">PIN</label>
                <input type="text" id="pin" name="pin" inputmode="numeric" value="{{ old('pin') }}"
                       class="kotak-input tabular" maxlength="8" required>
                <p class="mt-1 text-xs leading-relaxed text-muted">
                    4 sampai 8 angka, berbeda untuk tiap orang. PIN tidak bisa dilihat lagi
                    setelah disimpan; kalau lupa, ganti dengan yang baru.
                </p>
            </div>

            <fieldset>
                <legend class="label-form">Bagian yang dipegang</legend>
                <div class="space-y-1.5">
                    @foreach ($tahap as $t)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="tahap[]" value="{{ $t->value }}" class="size-4 accent-navy">
                            {{ $t->label() }}
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <div>
                <label for="catatan" class="label-form">Catatan (opsional)</label>
                <input type="text" id="catatan" name="catatan" value="{{ old('catatan') }}"
                       class="kotak-input" maxlength="200">
            </div>

            <button type="submit" class="tombol tombol-navy w-full">Tambah pekerja</button>
        </form>
    </section>
</div>
@endsection
