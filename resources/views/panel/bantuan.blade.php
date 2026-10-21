@extends('layouts.panel')
@section('judul', 'Bantuan')

@section('isi')
{{--
    Halaman bantuan untuk orang toko sendiri.

    Ditulis seperti menjelaskan ke teman kerja, bukan seperti manual: pertanyaan
    yang memang ditanyakan, jawaban yang menyebut tombolnya, dan alasan di
    baliknya. Alasan ikut ditulis karena aturan yang tidak dimengerti akan dicari
    jalan pintasnya.
--}}
<x-kop judul="Bantuan"
       catatan="Apa, bagaimana, dan kenapa. Ditulis untuk orang toko, bukan untuk orang komputer." />

<div x-data="{
        cari: '',
        get kunci() { return this.cari.trim().toLowerCase() },
        cocok(teks) { return this.kunci === '' || teks.toLowerCase().includes(this.kunci) },
     }">

    <div class="relative max-w-lg">
        <label for="cari-bantuan" class="sr-only">Cari di halaman bantuan</label>
        <x-ikon nama="cari" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-navy-300" />
        <input type="search" id="cari-bantuan" x-model="cari" autocomplete="off"
               placeholder="Ketik yang sedang Anda cari, misalnya: transfer, stok, PIN"
               class="kotak-input py-3 pl-11">
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[13rem_minmax(0,1fr)] lg:items-start">
        {{-- Daftar isi. Di layar lebar ia menempel, karena halaman ini panjang
             dan orang yang sedang bingung tidak boleh kehilangan jalannya. --}}
        <nav class="segmen segmen-kolom lg:sticky lg:top-24 lg:overflow-visible lg:px-0"
             aria-label="Daftar isi bantuan" x-show="kunci === ''">
            @foreach ($bagian as $b)
                <a href="#{{ $b['kunci'] }}" class="segmen-item lg:w-full lg:justify-start lg:rounded-[8px]">
                    <x-ikon :nama="$b['ikon']" ukuran="size-4" />
                    {{ $b['judul'] }}
                </a>
            @endforeach
        </nav>

        <div class="min-w-0 space-y-6">
            <div class="langkah-berikut" x-show="kunci === ''">
                <div class="min-w-0 grow basis-64">
                    <p class="font-bold">Kalau sedang buru-buru dan bingung di satu pesanan</p>
                    <p class="kolom-baca mt-0.5 text-sm leading-relaxed text-muted">
                        Buka pesanannya. Kotak paling atas di halaman itu selalu menyebut siapa yang sedang
                        ditunggu dan tombol apa yang perlu ditekan. Halaman ini untuk yang ingin tahu
                        kenapa sistemnya dibuat begitu.
                    </p>
                </div>
                <a href="{{ route('panel.pesanan.index') }}" class="tombol tombol-garis tombol-sm">
                    Buka daftar pesanan
                    <x-ikon nama="lanjut" ukuran="size-4" />
                </a>
            </div>

            @foreach ($bagian as $b)
                <section id="{{ $b['kunci'] }}" class="kartu scroll-mt-32 overflow-hidden"
                         x-show="{{ Js::from(collect($b['isi'])->map(fn ($i) => $i['t'].' '.$i['j'])->prepend($b['judul'])->implode(' ')) }}
                                 .toLowerCase().includes(kunci)">
                    <div class="kartu-kop flex items-start gap-2.5">
                        <x-ikon :nama="$b['ikon']" class="mt-0.5 text-navy-400" />
                        <span class="min-w-0">
                            {{ $b['judul'] }}
                            <span class="block text-sm leading-relaxed font-normal text-muted">
                                {{ $b['catatan'] }}
                            </span>
                        </span>
                    </div>

                    <div class="divide-y divide-line">
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
