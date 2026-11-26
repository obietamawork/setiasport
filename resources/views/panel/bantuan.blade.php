@extends('layouts.panel')
@section('judul', 'Panduan')

@section('isi')
{{--
    Panduan internal (KEP-162): alur A sampai Z dan tanya jawab, disaring sesuai
    izin pembacanya. Ditulis seperti menjelaskan ke teman kerja, bukan seperti
    manual: pertanyaan yang memang ditanyakan, jawaban yang menyebut tombolnya,
    dan alasan di baliknya. Alasan ikut ditulis karena aturan yang tidak
    dimengerti akan dicari jalan pintasnya.

    Teks "Bantuan" tetap di judul kartu karena tautannya di bilah atas memakai
    kata itu.
--}}
<x-kop judul="Bantuan & panduan"
       catatan="Alur kerja dari awal sampai akhir, dan jawaban untuk yang sering ditanyakan. Yang tampil hanya bagian yang sesuai peran Anda." />

@php $kelompok = collect($bagian)->groupBy('kelompok'); @endphp

<div x-data="{
        cari: '',
        get kunci() { return this.cari.trim().toLowerCase() },
        cocok(teks) { return this.kunci === '' || teks.toLowerCase().includes(this.kunci) },
     }">

    <div class="flex flex-wrap items-end gap-3">
        <div class="relative max-w-lg min-w-64 flex-1">
            <label for="cari-bantuan" class="sr-only">Cari di panduan</label>
            <x-ikon nama="cari" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-navy-300" />
            <input type="search" id="cari-bantuan" x-model="cari" autocomplete="off"
                   placeholder="Ketik yang sedang Anda cari, misalnya: transfer, stok, PIN"
                   class="kotak-input py-3 pl-11">
        </div>

        @if ($peran->isNotEmpty())
            {{-- Pemilik melihat panduan seperti peran lain, misalnya sebelum
                 mengajari admin baru. --}}
            <form method="GET" action="{{ route('panel.bantuan') }}" class="flex items-end gap-2">
                <div>
                    <label for="sebagai" class="label-form">Lihat sebagai</label>
                    <select id="sebagai" name="sebagai" class="kotak-input" @change="$el.form.submit()">
                        <option value="">Peran saya</option>
                        @foreach ($peran as $r)
                            <option value="{{ $r->slug }}" @selected($sebagai?->is($r))>{{ $r->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <noscript><button type="submit" class="tombol tombol-garis">Tampilkan</button></noscript>
            </form>
        @endif
    </div>

    @if ($sebagai)
        <p class="notis mt-4">
            Anda melihat panduan sebagai {{ $sebagai->nama }}: hanya bagian yang dibaca peran itu yang tampil.
            <a href="{{ route('panel.bantuan') }}" class="tautan">Kembali ke peran saya</a>
        </p>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-[14rem_minmax(0,1fr)] lg:items-start">
        {{-- Daftar isi berkelompok. Di layar lebar ia menempel, karena halaman ini
             panjang dan orang yang sedang bingung tidak boleh kehilangan jalannya. --}}
        <nav class="segmen segmen-kolom lg:sticky lg:top-24 lg:overflow-visible lg:px-0"
             aria-label="Daftar isi panduan" x-show="kunci === ''">
            @foreach ($kelompok as $nama => $isi)
                <p class="hidden px-3 pt-3 text-xs font-semibold tracking-wide text-muted uppercase first:pt-0 lg:block">{{ $nama }}</p>
                @foreach ($isi as $b)
                    <a href="#{{ $b['kunci'] }}" class="segmen-item lg:w-full lg:justify-start lg:rounded-[8px]">
                        <x-ikon :nama="$b['ikon']" ukuran="size-4" />
                        {{ $b['judul'] }}
                    </a>
                @endforeach
            @endforeach
        </nav>

        <div class="min-w-0 space-y-6">
            <div class="langkah-berikut" x-show="kunci === ''">
                <div class="min-w-0 grow basis-64">
                    <p class="font-bold">Kalau sedang buru-buru dan bingung di satu pesanan</p>
                    <p class="kolom-baca mt-0.5 text-sm leading-relaxed text-muted">
                        Buka pesanannya. Kotak paling atas di halaman itu selalu menyebut siapa yang sedang
                        ditunggu dan tombol apa yang perlu ditekan. Panduan ini untuk yang ingin tahu
                        alurnya dari awal sampai akhir, dan kenapa sistemnya dibuat begitu.
                    </p>
                </div>
                @if (auth('web')->user()->punyaIzin('order.lihat'))
                    <a href="{{ route('panel.pesanan.index') }}" class="tombol tombol-garis tombol-sm">
                        Buka daftar pesanan
                        <x-ikon nama="lanjut" ukuran="size-4" />
                    </a>
                @endif
            </div>

            @foreach ($bagian as $b)
                @php
                    $teks = collect($b['langkah'] ?? [])->merge($b['isi'] ?? [])
                        ->map(fn ($i) => $i['t'].' '.$i['j'])->prepend($b['judul'])->implode(' ');
                @endphp
                <section id="{{ $b['kunci'] }}" class="kartu scroll-mt-32 overflow-hidden"
                         x-show="{{ Js::from($teks) }}.toLowerCase().includes(kunci)">
                    <div class="kartu-kop flex flex-wrap items-start gap-2.5">
                        <x-ikon :nama="$b['ikon']" class="mt-0.5 text-navy-400" />
                        <span class="min-w-0 flex-1 basis-52">
                            {{ $b['judul'] }}
                            <span class="block text-sm leading-relaxed font-normal text-muted">
                                {{ $b['catatan'] }}
                            </span>
                        </span>
                        @if ($b['kunci'] === 'konveksi' && $bolehCetakKonveksi)
                            <a href="{{ route('panel.bantuan.konveksi') }}" target="_blank" rel="noopener"
                               class="tombol tombol-garis tombol-sm">
                                <x-ikon nama="cetak" ukuran="size-4" /> Cetak A4
                            </a>
                        @endif
                    </div>

                    @if (! empty($b['langkah']))
                        {{-- Alur bernomor: dibaca berurutan, dari awal sampai akhir. --}}
                        <ol class="divide-y divide-line">
                            @foreach ($b['langkah'] as $n => $l)
                                <li class="flex gap-4 px-5 py-4" x-show="cocok({{ Js::from($l['t'].' '.$l['j']) }})">
                                    <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-navy-50 text-sm font-bold text-navy">{{ $n + 1 }}</span>
                                    <div class="min-w-0">
                                        <p class="font-semibold">{{ $l['t'] }}</p>
                                        <p class="kolom-baca mt-1 leading-relaxed text-muted">{{ $l['j'] }}</p>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    @endif

                    @if (! empty($b['isi']))
                        <div class="divide-y divide-line {{ ! empty($b['langkah']) ? 'border-t border-line' : '' }}">
                            @foreach ($b['isi'] as $i)
                                {{-- Terbuka semua sejak awal. Akordeon memang rapi dilihat,
                                     tetapi orang yang sedang mencari jawaban harus membuka
                                     satu per satu untuk tahu isinya apa. --}}
                                <div class="px-5 py-4" x-show="cocok({{ Js::from($i['t'].' '.$i['j']) }})">
                                    <p class="font-semibold">{{ $i['t'] }}</p>
                                    <p class="kolom-baca mt-1.5 leading-relaxed text-muted">{{ $i['j'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endforeach

            <p class="kartu p-5 text-sm leading-relaxed text-muted" x-show="kunci !== ''" x-cloak>
                Tidak ketemu yang dicari? Hapus kata pencariannya untuk melihat semuanya, atau tanyakan
                langsung ke pemilik toko. Pertanyaan yang sering berulang lebih baik ditambahkan ke
                halaman ini daripada dijawab satu per satu lewat chat.
            </p>
        </div>
    </div>
</div>
@endsection
