@extends('layouts.panel')
@section('judul', 'Pengaturan')

@section('isi')
@php
    // Tab pertama yang memuat kesalahan dibuka lebih dulu; kalau tidak ada,
    // tab dari alamat (?tab=), supaya setelah menyimpan kita kembali ke tempat yang sama.
    $tabGalat = collect($tab)->keys()->first(fn ($k) => collect($tab[$k]['bagian'])
        ->flatMap(fn ($b) => $b['kunci'])
        ->contains(fn ($kunci) => $errors->has('setting.'.$kunci)));
    $tabAwal = $tabGalat ?? (array_key_exists(request('tab'), $tab) ? request('tab') : array_key_first($tab));
@endphp

<x-kop judul="Pengaturan"
       catatan="Berlaku di seluruh website begitu disimpan: halaman produk, checkout, halaman pesanan, dan email pelanggan." />

<div x-data="{
        tab: @js($tabAwal),
        berubah: @js($errors->any()),
        menyimpan: false,
        csAktif: @js((bool) old('setting.cs_aktif', $setting['cs_aktif']->value ?? false)),
        pilih(t) {
            this.tab = t;
            history.replaceState(null, '', '?tab=' + t);
            window.scrollTo({ top: 0 });
        },
        tandai(e) {
            if (e.target.getAttribute('form') === 'form-pengaturan') this.berubah = true;
        },
    }"
     x-init="window.addEventListener('beforeunload', (e) => { if (berubah && !menyimpan) { e.preventDefault(); e.returnValue = ''; } })"
     @input="tandai($event)" @change="tandai($event)"
     class="lg:grid lg:grid-cols-[12rem_minmax(0,1fr)] lg:items-start lg:gap-8">

    {{-- Formulirnya kosong; setiap isian menunjuk ke sini lewat atribut form.
         Dengan begitu kartu QRIS, yang punya formulir unggah sendiri, bisa
         berada di dalam tab Pembayaran tanpa formulir bersarang. class="hidden"
         wajib: tanpa itu formulir ini menempati satu sel grid dan menggeser menu. --}}
    <form id="form-pengaturan" method="POST" action="{{ route('panel.pengaturan.update') }}"
          @submit="menyimpan = true" class="hidden">
        @csrf @method('PUT')
    </form>

    {{-- Menu tab: berderet dan bisa digeser di HP, jadi kolom kiri di layar lebar. --}}
    <nav class="segmen segmen-kolom lg:sticky lg:top-24 lg:mx-0 lg:overflow-visible lg:px-0"
         aria-label="Kelompok pengaturan">
        @foreach ($tab as $kunci => $t)
            @php
                $adaGalat = collect($t['bagian'])->flatMap(fn ($b) => $b['kunci'])
                    ->contains(fn ($k) => $errors->has('setting.'.$k));
            @endphp
            <button type="button" @click="pilih(@js($kunci))" :aria-current="tab === @js($kunci) ? 'page' : null"
                    class="segmen-item lg:w-full lg:justify-start lg:rounded-[8px]"
                    :class="tab === @js($kunci) ? 'segmen-item-aktif' : ''">
                {{ $t['judul'] }}
                @if ($adaGalat)
                    <span class="size-2 shrink-0 rounded-full bg-merah" aria-label="ada kesalahan"></span>
                @endif
            </button>
        @endforeach
    </nav>

    <div class="mt-4 min-w-0 lg:mt-0">
        @foreach ($tab as $kunci => $t)
            <section x-show="tab === @js($kunci)" @unless ($kunci === $tabAwal) x-cloak @endunless class="space-y-4">
                <div>
                    <h2 class="text-lg font-bold">{{ $t['judul'] }}</h2>
                    <p class="mt-1 text-sm leading-relaxed text-muted">{{ $t['catatan'] }}</p>
                </div>

                {{-- Bagian dengan "baris" yang sama berdampingan, misalnya dua rekening. --}}
                @foreach (collect($t['bagian'])->chunkWhile(fn ($b, $i, $kelompok) => isset($b['baris']) && ($kelompok->last()['baris'] ?? null) === $b['baris']) as $kelompok)
                    <div class="grid gap-4 {{ $kelompok->count() > 1 ? 'md:grid-cols-2' : '' }}">
                        @foreach ($kelompok as $b)
                            @php $isian = collect($b['kunci'])->map(fn ($k) => $setting[$k] ?? null)->filter(); @endphp
                            @continue($isian->isEmpty())

                            <section class="kartu p-5" @if (! empty($b['hanyaSaatCs'])) x-show="csAktif" x-cloak @endif>
                                @if ($b['judul'])
                                    <h3 class="font-semibold">{{ $b['judul'] }}</h3>
                                    @if (! empty($b['catatan']))
                                        <p class="mt-0.5 text-xs text-muted">{{ $b['catatan'] }}</p>
                                    @endif
                                @endif
                                <div class="{{ $b['judul'] ? 'mt-4' : '' }} space-y-4">
                                    @foreach ($isian as $s)
                                        @include('panel.pengaturan.isian', ['s' => $s])
                                    @endforeach
                                </div>
                            </section>
                        @endforeach
                    </div>
                @endforeach

                @if ($kunci === 'pembayaran')
                    {{-- Gambar QRIS terpisah dari formulir teks (KEP-023). Salah unggah di
                         sini berarti uang pelanggan masuk ke rekening lain, jadi pratinjaunya
                         ditampilkan besar dan penggantinya butuh tindakan tersendiri. --}}
                    <section class="kartu p-5">
                        <h3 class="font-semibold">Gambar QRIS</h3>
                        <div class="mt-4 space-y-4">
                            @if (setting('qris_path'))
                                <div class="flex flex-wrap items-start gap-5">
                                    <img src="{{ asset('storage/'.setting('qris_path')) }}" alt="QRIS toko"
                                         class="w-40 border border-line bg-white p-2">
                                    <div class="min-w-48 flex-1 space-y-3">
                                        <p class="text-sm leading-relaxed">
                                            Inilah gambar yang dipindai pelanggan. Pastikan nama merchantnya
                                            terbaca dan tidak tertutup.
                                        </p>
                                        <form method="POST" action="{{ route('panel.pengaturan.qris.hapus') }}"
                                              data-konfirmasi="Hapus gambar QRIS?"
                                              data-konfirmasi-pesan="Pelanggan hanya akan melihat rekening bank."
                                              data-konfirmasi-tombol="Hapus QRIS">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="tautan text-merah">Hapus gambar QRIS</button>
                                        </form>
                                    </div>
                                </div>
                            @else
                                <p class="text-sm leading-relaxed text-muted">
                                    Belum ada. Tanpa ini, halaman pesanan pelanggan hanya menampilkan rekening bank.
                                </p>
                            @endif

                            <form method="POST" action="{{ route('panel.pengaturan.qris') }}" enctype="multipart/form-data"
                                  class="flex flex-wrap items-end gap-3 border-t border-line pt-4">
                                @csrf
                                <div class="min-w-56 flex-1">
                                    <label for="qris" class="label-form">
                                        {{ setting('qris_path') ? 'Ganti dengan gambar lain' : 'Unggah gambar QRIS' }}
                                    </label>
                                    <input type="file" id="qris" name="qris" accept="image/*" class="kotak-input" required>
                                    <p class="mt-1 text-xs text-muted">
                                        JPG, PNG, atau WebP, maksimal {{ setting('upload_maks_mb', 10) }} MB.
                                    </p>
                                </div>
                                <button type="submit" class="tombol tombol-garis">Simpan gambar</button>
                            </form>
                        </div>
                    </section>
                @endif
            </section>
        @endforeach

        {{-- Tombol simpan selalu terlihat, di tab mana pun. Satu tombol untuk semua
             tab: berpindah tab tidak membuang isian yang sudah diketik. --}}
        <div class="bilah-aksi mt-6 justify-between">
            <p class="flex items-center gap-2 text-sm"
               :class="berubah ? 'font-medium text-ink' : 'text-muted'">
                <span x-show="berubah" x-cloak class="size-2 shrink-0 rounded-full bg-kuning"></span>
                <span x-text="berubah ? 'Ada perubahan yang belum disimpan' : 'Tidak ada perubahan'"></span>
            </p>
            <button type="submit" form="form-pengaturan" class="tombol tombol-navy" :disabled="!berubah">
                <x-ikon nama="centang" ukuran="size-4" />
                Simpan pengaturan
            </button>
        </div>
    </div>
</div>
@endsection
