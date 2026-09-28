@extends('layouts.publik')
{{-- Nilai kedua @section TIDAK BOLEH null: Blade menganggapnya "buka blok"
     dan memulai buffer keluaran yang tidak pernah ditutup. Pengaturan yang
     belum diisi mengembalikan null, jadi cadangannya wajib ada. --}}
@section('judul', setting('tentang_judul') ?: 'Toko olahraga dan konveksi jersey di Wonogiri')
@section('deskripsi', setting('tentang_ringkas') ?: 'Perlengkapan olahraga lengkap dan layanan jersey custom di Wonogiri. Bisa diambil di toko atau dikirim.')

@section('isi')

@php
    // Klien: konsultasi lewat chat CS di website (KEP-030). Kalau CS dimatikan
    // (KEP-033), tombolnya jatuh ke WhatsApp supaya ajakan utama tidak hilang.
    $csAktif = cs_aktif();
    $waKonsultasi = $csAktif ? null : tautan_wa(
        'Halo '.setting('toko_nama', 'Setia Sport').', saya mau tanya soal pemesanan.'
    );
@endphp

{{--
    Susunan halaman depan mengikuti rancangan klien (KEP-062): belanja dulu,
    bukti belakangan.

    Yang dipertahankan dari susunan lama: bukti pekerjaan dan alamat yang bisa
    didatangi tetap ada di halaman ini, hanya turun ke bawah. Pemesan seragam
    satu sekolah memang membacanya sampai bawah; orang yang mencari bola tidak
    perlu melewatinya lebih dulu.
--}}

<section class="relative overflow-hidden bg-navy-900 text-white">
    {{-- Latar berupa warna, bukan foto: foto etalase yang bagus belum ada, dan
         foto seadanya yang diregangkan selebar layar terlihat lebih murah
         daripada bidang warna yang rapi. Begitu klien mengirim fotonya, cukup
         tambahkan satu kelas latar di sini. --}}
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(60rem_40rem_at_15%_-10%,rgba(214,43,31,0.35),transparent),radial-gradient(45rem_35rem_at_85%_0%,rgba(30,52,116,0.9),transparent)]"></div>

    <div class="relative mx-auto grid max-w-6xl items-center gap-10 px-5 py-14 sm:py-20 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <div>
            <p class="text-xs font-semibold tracking-[0.18em] text-white/55 uppercase">
                {{ setting('toko_nama', 'Setia Sport') }} Wonogiri
            </p>

            <h1 class="mt-4 max-w-2xl text-4xl leading-[1.08] font-extrabold text-balance sm:text-5xl">
                {{ setting('tentang_judul') ?: 'Perlengkapan olahraga lengkap & jersey custom' }}
            </h1>

            <p class="kolom-baca mt-5 text-base leading-relaxed text-white/75 sm:text-lg">
                {{ setting('tentang_ringkas')
                    ?: 'Bola, sepatu, kaos, dan aksesoris olahraga, ditambah layanan konveksi jersey
                        yang dikerjakan sendiri di Wonogiri. Bisa diambil di toko atau dikirim.' }}
            </p>

            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('katalog') }}" class="tombol tombol-utama">
                    Lihat produk
                    <x-ikon nama="lanjut" ukuran="size-4" />
                </a>
                <a href="{{ route('katalog', ['jalur' => 'kustom']) }}"
                   class="tombol border-white/25 bg-white/10 text-white hover:bg-white/20">
                    Buat jersey custom
                </a>
            </div>
        </div>

        <img src="{{ asset('logo-lengkap.webp') }}" alt="{{ setting('toko_nama', 'Setia Sport') }} Wonogiri"
             width="1000" height="505" class="hidden w-full max-w-xs justify-self-end opacity-90 lg:block">
    </div>

    {{-- Empat hal yang paling sering ditanyakan sebelum orang mau memulai. Di
         HP ia menggeser ke samping, bukan menumpuk jadi empat baris yang
         mendorong isi halaman turun satu layar penuh. --}}
    <div class="relative border-t border-white/10">
        <div class="geser-x mx-auto flex max-w-6xl snap-x gap-px px-5 text-sm sm:grid sm:grid-cols-2 sm:px-0 lg:grid-cols-4">
            @foreach ([
                ['produk', 'Produk original', 'Dari merek yang bisa dipertanggungjawabkan'],
                ['uang', 'Harga bersaing', 'Ecer maupun grosir, harganya terbuka di web'],
                ['kategori', 'Bisa custom', 'Desain, nama, dan nomor sesuai keinginan tim'],
                ['toko', 'Ambil atau kirim', 'Diambil di toko, atau dikirim ke alamat Anda'],
            ] as $i => [$ikon, $judul, $isi])
                <div class="min-w-56 flex-1 snap-start px-5 py-5 {{ $i > 0 ? 'sm:border-l sm:border-white/10' : '' }}">
                    <div class="flex items-start gap-3">
                        <x-ikon :nama="$ikon" class="mt-0.5 shrink-0 text-kuning" />
                        <div>
                            <p class="font-semibold">{{ $judul }}</p>
                            <p class="mt-0.5 leading-relaxed text-white/60">{{ $isi }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Kategori: jalan tercepat ke barang yang dicari. Fotonya diambil dari salah
     satu produk di dalamnya, supaya tidak ada gambar tambahan yang harus
     diurus pemilik toko. --}}
@if ($kategori->isNotEmpty())
<section class="mx-auto max-w-6xl px-5 py-12 sm:py-16">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 class="text-2xl font-extrabold sm:text-3xl">Kategori produk</h2>
            <p class="mt-1.5 text-muted">Temukan perlengkapan olahraga favorit Anda di sini.</p>
        </div>
        <a href="{{ route('katalog') }}" class="tautan text-sm font-medium">Lihat semua &rarr;</a>
    </div>

    <div class="mt-7 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        @foreach ($kategori as $k)
            <a href="{{ route('katalog', ['kategori' => $k->slug]) }}"
               class="kartu kartu-tautan group overflow-hidden text-center">
                <div class="relative aspect-square bg-navy-50">
                    @if ($k->gambar?->thumb_url)
                        <img src="{{ $k->gambar->thumb_url }}" alt="{{ $k->nama }}" loading="lazy" decoding="async"
                             class="absolute inset-0 size-full object-cover transition-transform duration-300 group-hover:scale-105">
                    @else
                        <span class="absolute inset-0 flex items-center justify-center text-xl font-extrabold text-navy-300">
                            {{ Str::upper(Str::substr($k->nama, 0, 2)) }}
                        </span>
                    @endif
                </div>
                <p class="px-2 py-3 text-sm font-semibold">{{ $k->nama }}</p>
            </a>
        @endforeach
    </div>
</section>
@endif

{{-- Produk unggulan: diurutkan dari yang benar-benar paling banyak terjual,
     bukan dari daftar kesayangan yang basi dalam sebulan. --}}
@if ($unggulan->isNotEmpty())
<section class="border-y border-line bg-field">
    <div class="mx-auto max-w-6xl px-5 py-12 sm:py-16">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="text-2xl font-extrabold sm:text-3xl">Produk unggulan</h2>
                <p class="mt-1.5 text-muted">Yang paling sering dibawa pulang pelanggan.</p>
            </div>
            <a href="{{ route('katalog') }}" class="tautan text-sm font-medium">Lihat semua &rarr;</a>
        </div>

        <div class="mt-7 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            @foreach ($unggulan as $p)
                @include('komponen.kartu-produk', ['produk' => $p])
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Layanan jersey custom. Inilah yang membedakan toko ini dari toko olahraga
     lain, jadi ia mendapat satu bidang penuh, bukan satu baris di katalog. --}}
<section class="mx-auto max-w-6xl px-5 py-12 sm:py-16">
    <div class="kartu overflow-hidden lg:grid lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
        <div class="relative min-h-56 bg-navy-900">
            <div class="absolute inset-0 bg-[radial-gradient(30rem_20rem_at_30%_20%,rgba(214,43,31,0.45),transparent)]"></div>
            <div class="relative flex h-full flex-col justify-center gap-3 p-8 text-white">
                <p class="text-xs font-semibold tracking-[0.18em] text-white/55 uppercase">Layanan custom</p>
                <p class="text-3xl leading-tight font-extrabold text-balance">Buat jersey tim sesuai keinginan</p>
                <p class="leading-relaxed text-white/70">
                    Dikerjakan sendiri di konveksi kami, bukan dilempar ke pihak lain.
                    Ukurannya bisa dicampur anak sampai dewasa dalam satu pesanan.
                </p>
            </div>
        </div>

        <div class="p-8">
            <ul class="space-y-3">
                @foreach ([
                    'Bebas pilih desain, bahan, dan warna',
                    'Nama dan nomor punggung per pemain',
                    'Harga per potong terbaca langsung di web, tanpa perlu bertanya dulu',
                    'Tiga kali revisi desain, semuanya gratis',
                ] as $poin)
                    <li class="flex items-start gap-2.5">
                        <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-ok-50 text-ok">
                            <x-ikon nama="centang" ukuran="size-3.5" />
                        </span>
                        <span class="leading-relaxed">{{ $poin }}</span>
                    </li>
                @endforeach
            </ul>

            {{-- Empat langkah singkat: keberatan terbesar pemesan pertama kali
                 adalah tidak tahu uangnya dipegang siapa dan sampai kapan. --}}
            <ol class="mt-6 grid gap-x-4 gap-y-2 border-t border-line pt-5 text-sm text-muted sm:grid-cols-2">
                @foreach ([
                    'Pilih produk dan isi ukurannya',
                    'Bayar uang muka '.setting('dp_minimum_persen', 50).'%',
                    'Desain dikirim, Anda setujui',
                    'Dikerjakan '.($leadTime ?? 7).' hari kerja, lalu dilunasi',
                ] as $i => $langkah)
                    <li class="flex gap-2">
                        <span class="numeral font-bold text-navy">{{ $i + 1 }}.</span>
                        <span>{{ $langkah }}</span>
                    </li>
                @endforeach
            </ol>

            <div class="mt-6 flex flex-wrap gap-3">
                <a href="{{ route('katalog', ['jalur' => 'kustom']) }}" class="tombol tombol-utama">
                    Mulai pesan custom
                    <x-ikon nama="lanjut" ukuran="size-4" />
                </a>
                @if ($csAktif)
                    <button type="button" x-data @click="$dispatch('buka-cs', { topik: 'KUSTOM' })"
                            class="tombol tombol-garis">Tanya dulu ke CS</button>
                @elseif ($waKonsultasi)
                    <a href="{{ $waKonsultasi }}" target="_blank" rel="noopener" class="tombol tombol-garis">
                        Tanya dulu lewat WhatsApp
                    </a>
                @endif
            </div>
        </div>
    </div>
</section>

{{-- Kenapa di sini, bukan di toko sebelah. Empat saja: daftar sembilan
     keunggulan tidak pernah dibaca sampai habis. --}}
<section class="border-y border-line bg-field">
    <div class="mx-auto max-w-6xl px-5 py-12 sm:py-14">
        <h2 class="text-2xl font-extrabold sm:text-3xl">Kenapa belanja di {{ setting('toko_nama', 'Setia Sport') }}?</h2>

        <div class="mt-7 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['produk', 'Produk lengkap', 'Bola, sepatu, apparel, aksesoris, sampai trofi, ada di satu toko.'],
                ['kategori', 'Konveksi sendiri', 'Jersey dikerjakan di workshop kami, jadi revisinya cepat dan bisa diawasi.'],
                ['toko', 'Tokonya bisa didatangi', 'Alamatnya jelas di Wonogiri, barangnya bisa dilihat dan dicoba dulu.'],
                ['pesanan', 'Pesanan bisa dipantau', 'Setiap pesanan punya halamannya sendiri: progres, tagihan, dan resi.'],
            ] as [$ikon, $judul, $isi])
                <div class="flex gap-3">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-[10px] bg-navy-50 text-navy">
                        <x-ikon :nama="$ikon" />
                    </span>
                    <div>
                        <p class="font-bold">{{ $judul }}</p>
                        <p class="mt-1 text-sm leading-relaxed text-muted">{{ $isi }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Bukti pekerjaan. Turun ke bawah dan gridnya dikecilkan (KEP-062): yang
     mencari bukti akan menggulir sampai sini, yang mencari bola tidak perlu
     melewati enam kartu besar lebih dulu. --}}
@if ($portofolio->isNotEmpty())
<section class="mx-auto max-w-6xl px-5 py-12 sm:py-16">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 class="text-2xl font-extrabold sm:text-3xl">Pernah kami kerjakan</h2>
            <p class="mt-1.5 text-muted">Sekolah, instansi, dan komunitas di sekitar Wonogiri.</p>
        </div>
    </div>

    <div class="mt-7 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        @foreach ($portofolio as $p)
            <figure class="kartu overflow-hidden">
                <div class="relative aspect-square bg-navy-50">
                    @if ($p->gambar_path)
                        <img src="{{ asset('storage/'.($p->thumb_path ?: $p->gambar_path)) }}"
                             alt="{{ $p->judul }}" loading="lazy" decoding="async"
                             class="absolute inset-0 size-full object-cover">
                    @else
                        <span class="absolute inset-0 flex items-center justify-center px-2 text-center text-xs font-semibold text-navy-300">
                            {{ $p->judul }}
                        </span>
                    @endif
                </div>
                <figcaption class="px-2.5 py-2.5">
                    <p class="line-clamp-2 text-xs leading-snug font-semibold">{{ $p->judul }}</p>
                    @if ($p->keterangan())
                        <p class="mt-0.5 truncate text-xs text-muted">{{ $p->keterangan() }}</p>
                    @endif
                </figcaption>
            </figure>
        @endforeach
    </div>
</section>
@endif

{{-- Testimoni menguatkan bukti di atasnya. Ditulis ulang toko dari pesan
     WhatsApp pelanggan, jadi tidak ada antrian moderasi yang menumpuk. --}}
@if ($testimoni->isNotEmpty())
<section class="border-y border-line bg-field">
    <div class="mx-auto max-w-6xl px-5 py-12 sm:py-16">
        <h2 class="text-2xl font-extrabold sm:text-3xl">Kata mereka yang sudah pesan</h2>

        <div class="mt-7 grid gap-4 lg:grid-cols-3">
            @foreach ($testimoni as $t)
                <figure class="kartu flex flex-col p-6">
                    @if ($t->bintang)
                        <div class="flex gap-0.5 text-kuning" aria-label="{{ $t->bintang }} dari 5 bintang">
                            @for ($i = 0; $i < $t->bintang; $i++)
                                {{-- fill="currentColor" menang atas default fill="none" ikon
                                     (atribut pertama yang menang saat HTML di-parse), supaya
                                     bintang terlihat penuh, bukan garis tipis yang di layar
                                     kecil gampang terbaca sebagai kosong. --}}
                                <x-ikon nama="testimoni" ukuran="size-4" fill="currentColor" />
                            @endfor
                        </div>
                    @endif

                    <blockquote class="mt-3 flex-1 leading-relaxed">&ldquo;{{ $t->isi }}&rdquo;</blockquote>

                    <figcaption class="mt-4 border-t border-line pt-4 text-sm">
                        <span class="font-semibold">{{ $t->nama }}</span>
                        @if ($t->peran)
                            <span class="block text-muted">{{ $t->peran }}</span>
                        @endif
                    </figcaption>
                </figure>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Tentang usaha. Ditaruh setelah bukti pekerjaan, karena cerita baru
     dipercaya setelah ada yang bisa dilihat. Dilewati sepenuhnya kalau pemilik
     toko mengosongkannya: paragraf basa-basi lebih buruk daripada tidak ada. --}}
@if (setting('tentang_lengkap'))
<section id="tentang" class="scroll-mt-20 border-y border-line bg-field">
    <div class="mx-auto grid max-w-6xl gap-8 px-5 py-12 sm:py-16 lg:grid-cols-[minmax(0,1fr)_18rem] lg:items-start">
        <div>
            <h2 class="text-2xl font-extrabold sm:text-3xl">Tentang {{ setting('toko_nama', 'kami') }}</h2>
            <div class="kolom-baca mt-4 space-y-4 leading-relaxed text-muted">
                @foreach (preg_split('/\R{2,}/', (string) setting('tentang_lengkap')) as $paragraf)
                    <p>{{ $paragraf }}</p>
                @endforeach
            </div>
        </div>

        <img src="{{ asset('logo-lengkap.webp') }}" alt="{{ setting('toko_nama', 'Setia Sport') }}"
             width="1000" height="505" class="hidden w-full max-w-xs lg:block">
    </div>
</section>
@endif

{{-- Lokasi: satu-satunya cara mengambil barang, jadi ia bukan catatan kaki.
     Dua tempat dengan tugas berbeda: toko tempat pesanan diambil dan dilunasi,
     konveksi tempat jersey dikerjakan. --}}
<section class="mx-auto max-w-6xl px-5 py-12 sm:py-16">
    <h2 class="text-2xl font-extrabold sm:text-3xl">Toko dan konveksi kami</h2>

    <div class="mt-7 grid gap-4 lg:grid-cols-2">
        @foreach ([
            [
                'judul' => 'Toko',
                'tugas' => 'Tempat pesanan diambil dan dilunasi',
                'alamat' => setting('toko_alamat'),
                'jam' => setting('toko_jam_buka'),
                'peta' => setting('maps_embed') ?: tautan_peta((string) setting('toko_alamat')),
            ],
            [
                'judul' => 'Konveksi',
                'tugas' => 'Tempat jersey dikerjakan. Pesanan tetap diambil di toko',
                'alamat' => setting('konveksi_alamat'),
                'jam' => setting('konveksi_jam_buka'),
                'peta' => tautan_peta((string) setting('konveksi_alamat')),
            ],
        ] as $lokasi)
            @continue(! $lokasi['alamat'])

            <article class="kartu flex flex-col p-6 lg:p-8">
                <p class="text-xl font-extrabold">{{ $lokasi['judul'] }}</p>
                <p class="mt-1 text-sm font-medium text-merah">{{ $lokasi['tugas'] }}</p>

                <p class="mt-4 leading-relaxed">{{ $lokasi['alamat'] }}</p>

                <div class="mt-4 flex-1 text-sm">
                    <p class="font-semibold">Jam buka</p>
                    <p class="tabular mt-1 leading-relaxed text-muted">{{ baris_html($lokasi['jam']) }}</p>
                </div>

                <a href="{{ $lokasi['peta'] }}" target="_blank" rel="noopener" class="tombol tombol-garis mt-6 self-start">
                    Buka di Google Maps
                </a>
            </article>
        @endforeach
    </div>
</section>

{{-- Ajakan penutup. WhatsApp di sini BANTUAN, bukan jalur pemesanan (KEP-040):
     pesanannya tetap dibuat di web supaya ada nomornya, ada halaman lacaknya,
     dan ada catatan siapa menyetujui apa. --}}
<section class="bg-navy-900 text-white">
    <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-6 px-5 py-12">
        <div>
            <p class="text-2xl font-extrabold text-balance">Butuh perlengkapan olahraga atau jersey tim?</p>
            <p class="mt-2 leading-relaxed text-white/70">
                Pesan langsung dari web, atau tanya dulu kalau masih ragu soal ukuran dan bahan.
            </p>
            {{-- Nomornya ditulis, bukan hanya jadi tautan: sebagian orang menyimpan
                 nomornya dulu di kontak sebelum menghubungi, dan nomor yang hanya
                 hidup di dalam tautan tidak bisa disalin. --}}
            @if (setting('toko_telepon'))
                <p class="mt-3 text-sm text-white/70">
                    <span class="tabular font-semibold text-white">WhatsApp toko {{ setting('toko_telepon') }}</span>,
                    {{ Str::lcfirst((string) setting('cs_jam_layanan')) }}
                </p>
            @endif
        </div>

        <div class="flex flex-wrap gap-3">
            <a href="{{ route('katalog') }}" class="tombol tombol-utama">Lihat produk</a>
            @if ($csAktif)
                <button type="button" x-data @click="$dispatch('buka-cs', {})"
                        class="tombol border-white/25 bg-white/10 text-white hover:bg-white/20">Chat dengan CS</button>
            @elseif ($waKonsultasi)
                <a href="{{ $waKonsultasi }}" target="_blank" rel="noopener"
                   class="tombol border-white/25 bg-white/10 text-white hover:bg-white/20">Tanya lewat WhatsApp</a>
            @endif
            <a href="{{ route('lacak') }}" class="tombol tombol-halus text-white/80 hover:bg-white/10 hover:text-white">
                Lacak pesanan
            </a>
        </div>
    </div>
</section>
@endsection
