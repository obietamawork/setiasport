@extends('layouts.publik')
{{-- Nilai kedua @section TIDAK BOLEH null: Blade menganggapnya "buka blok"
     dan memulai buffer keluaran yang tidak pernah ditutup. Pengaturan yang
     belum diisi mengembalikan null, jadi cadangannya wajib ada. --}}
@section('judul', setting('tentang_judul') ?: 'Toko olahraga dan konveksi jersey di Wonogiri')
{{-- Nama toko di depan, hanya di halaman depan (KEP-101): orang yang mencari
     tokonya mengetik namanya, dan tab PC yang sempit dulu hanya menampilkan
     "Perlengkapan olahraga len..." tanpa nama toko sama sekali. --}}
@section('judul_lengkap', setting('toko_nama', 'Setia Sport').' | '.(setting('tentang_judul') ?: 'Toko olahraga dan konveksi jersey di Wonogiri'))
@section('deskripsi', setting('tentang_ringkas') ?: 'Perlengkapan olahraga lengkap dan layanan jersey custom di Wonogiri. Bisa diambil di toko atau dikirim.')

{{-- Data toko untuk mesin pencari (KEP-089). JSON_HEX_TAG: isian pengaturan
     yang memuat "</script>" tidak bisa menutup blok ini. --}}
@push('kepala')
    <script type="application/ld+json">@json(\App\Support\DataTerstruktur::toko(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG)</script>
@endpush

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

<section class="motif relative overflow-hidden text-white">
    {{-- Latar berupa warna, bukan foto: foto etalase yang bagus belum ada, dan
         foto seadanya yang diregangkan selebar layar terlihat lebih murah
         daripada bidang warna yang rapi. Begitu klien mengirim fotonya, cukup
         tambahkan satu kelas latar di sini.

         Navy padat bermotif garis tipis (KEP-167). Gradien navy ke merah yang
         dulu di sini jatuh ke ungu keruh, warna yang tidak ada di logo. --}}

    @if ($banner->isNotEmpty())
        {{-- Banner promo menggantikan judul dan tombol (KEP-092). Judul halaman
             tetap ada untuk pembaca layar dan mesin pencari. --}}
        <h1 class="sr-only">{{ setting('tentang_judul') ?: 'Perlengkapan olahraga lengkap & jersey custom' }}</h1>
        @include('komponen.banner-beranda', ['banner' => $banner])
    @else
    <div class="relative mx-auto grid max-w-6xl items-center gap-10 px-5 pt-14 pb-10 sm:pt-20 sm:pb-12 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <div>
            <p class="alis alis-terang">
                <span class="garis-kit" aria-hidden="true"></span>
                {{ setting('toko_nama', 'Setia Sport') }} Wonogiri
            </p>

            <h1 class="judul-tampil mt-4 max-w-2xl text-[2.75rem] text-balance sm:text-6xl">
                {{ setting('tentang_judul') ?: 'Perlengkapan olahraga lengkap & jersey custom' }}
            </h1>

            <p class="kolom-baca mt-5 text-base leading-relaxed text-navy-200 sm:text-lg">
                {{ setting('tentang_ringkas')
                    ?: 'Tersedia berbagai kebutuhan olahraga mulai dari sepatu, bola, jersey, pakaian olahraga,
                        aksesoris, hingga layanan custom jersey sesuai dengan desainmu.' }}
            </p>

            {{-- Putih, bukan merah: melihat produk belum menggerakkan pesanan.
                 Merah disimpan untuk "Mulai pesan custom" di bawah (KEP-167). --}}
            <div class="kelompok-tombol mt-8">
                <a href="{{ route('katalog') }}" class="tombol bg-white font-bold text-navy hover:bg-navy-50">
                    Lihat produk
                    <x-ikon nama="lanjut" ukuran="size-4" />
                </a>
                {{-- Permintaan klien (KEP-102): jalan ke kustom sudah ada di menu
                     "Custom jersey", jadi tombol kedua dipakai untuk bertanya.
                     Chat CS kalau sedang dibuka, selain itu WhatsApp toko. --}}
                @if ($csAktif)
                    <button type="button" x-data @click="$dispatch('buka-cs', {})"
                            class="tombol border-white/25 bg-white/10 text-white hover:bg-white/20">
                        <x-ikon nama="chat" ukuran="size-4" />
                        Hubungi Kami
                    </button>
                @elseif ($waKonsultasi)
                    <a href="{{ $waKonsultasi }}" target="_blank" rel="noopener"
                       class="tombol border-white/25 bg-white/10 text-white hover:bg-white/20">
                        <x-ikon nama="whatsapp" ukuran="size-4" />
                        Hubungi Kami
                    </a>
                @endif
            </div>
        </div>

        {{-- Versi terang: tulisan navy logo asli tenggelam di latar hero yang
             juga navy (KEP-092). Sama seperti logo-terang.png di kepala panel,
             hanya tulisan dan lengkungnya yang diputihkan; atlet dan balok
             warnanya tidak diubah. --}}
        <img src="{{ asset('logo-lengkap-terang-640.webp') }}"
             srcset="{{ asset('logo-lengkap-terang-640.webp') }} 640w, {{ asset('logo-lengkap-terang.webp') }} 1000w"
             sizes="20rem" alt="{{ setting('toko_nama', 'Setia Sport') }} Wonogiri"
             width="1000" height="505" class="hidden w-full max-w-xs justify-self-end lg:block">
    </div>
    @endif

</section>

{{-- Kategori: jalan tercepat ke barang yang dicari. Fotonya diambil dari salah
     satu produk di dalamnya, supaya tidak ada gambar tambahan yang harus
     diurus pemilik toko. --}}
@if ($kategori->isNotEmpty())
<section class="mx-auto max-w-6xl px-5 py-12 sm:py-16">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 class="judul-bagian text-[2rem] sm:text-[2.5rem]">Kategori produk</h2>
            <p class="mt-1.5 text-muted">Temukan perlengkapan olahraga favorit Anda di sini.</p>
        </div>
        <a href="{{ route('katalog') }}" class="tautan inline-block py-1 pointer-coarse:py-3 text-sm font-medium">Lihat semua<span class="sr-only"> produk</span> &rarr;</a>
    </div>

    {{-- Jumlah kolom mengikuti jumlah kategori, supaya setiap baris penuh.
         Dulu 4 kolom di tablet: enam kategori jatuh jadi 4 + 2 dengan dua
         petak kosong, dan petaknya membesar jadi kotak 170px yang melompong. --}}
    @php
        // Ikon pengganti foto kategori (KEP-167), ditebak dari slug. Kategori
        // yang tidak dikenali mendapat ikon umum, bukan inisial seperti "BO".
        $ikonKategori = fn ($k) => match (true) {
            Str::contains($k->slug, 'bola') => 'bola',
            Str::contains($k->slug, 'sepatu') => 'sepatu',
            Str::contains($k->slug, 'celana') => 'celana',
            Str::contains($k->slug, 'jaket') => 'jaket',
            Str::contains($k->slug, ['jersey', 'kaos', 'baju', 'seragam', 'polo', 'pakaian']) => 'kaos',
            default => 'kategori',
        };
        $kolomKategori = match ($kategori->count()) {
            1 => 'grid-cols-2 sm:grid-cols-4',
            2 => 'grid-cols-2',
            3 => 'grid-cols-3',
            4 => 'grid-cols-2 sm:grid-cols-4',
            5 => 'grid-cols-3 sm:grid-cols-5',
            default => 'grid-cols-3 sm:grid-cols-6',
        };
    @endphp
    <div class="mt-7 grid gap-2.5 sm:gap-3 {{ $kolomKategori }}">
        @foreach ($kategori as $k)
            <a href="{{ route('katalog', ['kategori' => $k->slug]) }}"
               class="kartu kartu-tautan group overflow-hidden text-center">
                <div class="relative aspect-square bg-navy-50">
                    @if ($k->gambar?->thumb_url)
                        <img src="{{ $k->gambar->thumb_url }}" alt="{{ $k->nama }}" loading="lazy" decoding="async"
                             class="absolute inset-0 size-full object-cover transition-transform duration-300 group-hover:scale-105">
                    @else
                        <span aria-hidden="true" class="absolute inset-0 flex items-center justify-center">
                            <span class="flex size-14 items-center justify-center rounded-full bg-field text-navy shadow-sm sm:size-20">
                                <x-ikon :nama="$ikonKategori($k)" ukuran="size-7 sm:size-10" />
                            </span>
                        </span>
                    @endif
                </div>
                <p class="px-1.5 py-2 text-xs font-semibold sm:text-sm">{{ $k->nama }}</p>
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
                <h2 class="judul-bagian text-[2rem] sm:text-[2.5rem]">Produk unggulan</h2>
                <p class="mt-1.5 text-muted">Yang paling sering dibawa pulang pelanggan.</p>
            </div>
            <a href="{{ route('katalog') }}" class="tautan inline-block py-1 pointer-coarse:py-3 text-sm font-medium">Lihat semua<span class="sr-only"> produk</span> &rarr;</a>
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
     lain, jadi ia mendapat satu bidang penuh, bukan satu baris di katalog.
     Tautan "Cara pesan custom" di kaki halaman menuju ke sini (KEP-179). --}}
<section id="cara-pesan" class="mx-auto max-w-6xl px-5 py-12 sm:py-16">
    <div class="kartu overflow-hidden lg:grid lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
        <div class="motif relative min-h-56">
            <div class="relative flex h-full flex-col justify-center gap-3 p-8 text-white">
                <p class="alis alis-terang"><span class="garis-kit" aria-hidden="true"></span>Layanan custom</p>
                <p class="judul-tampil text-[2.5rem] text-balance">Buat jersey tim sesuai keinginan</p>
                <p class="leading-relaxed text-navy-200">
                    Seluruh proses produksi dikerjakan langsung di konveksi kami tanpa melalui pihak ketiga.
                    Dalam satu pesanan, ukuran dapat dikombinasikan mulai dari anak-anak hingga dewasa.
                </p>
            </div>
        </div>

        <div class="p-8">
            <ul class="space-y-3">
                @foreach ([
                    'Bebas pilih desain, bahan, dan warna',
                    'Nama dan nomor punggung per pemain',
                    'Harga Transparan ditampilkan di website tanpa perlu menghubungi admin',
                    'Gratis 3 Kali Revisi desain tanpa biaya tambahan',
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
                {{-- Teks dari klien (KEP-137), termasuk perkiraan 7–10 hari kerja. --}}
                @foreach ([
                    'Pilih Produk & Ukuran',
                    'Pembayaran Uang Muka (DP)',
                    'Proses Desain',
                    'Pesanan diproduksi dalam waktu 7–10 hari kerja',
                ] as $i => $langkah)
                    <li class="flex gap-2">
                        <span class="angka-tampil w-4 shrink-0 text-lg text-navy">{{ $i + 1 }}</span>
                        <span>{{ $langkah }}</span>
                    </li>
                @endforeach
            </ol>

            <div class="kelompok-tombol mt-6">
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
        <h2 class="judul-bagian text-[2rem] sm:text-[2.5rem]">Kenapa Belanja di {{ setting('toko_nama', 'Setia Sport') }}?</h2>

        <div class="mt-7 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['produk', 'Produk Olahraga Lengkap', 'Berbagai kebutuhan olahraga, mulai dari sepatu, pakaian, perlengkapan, hingga aksesori tersedia dalam satu toko.'],
                ['kategori', 'Konveksi Sendiri', 'Pesanan jersey dan apparel diproduksi langsung di konveksi kami, sehingga kualitas dan proses pengerjaan lebih terkontrol.'],
                ['toko', 'Toko Fisik Terpercaya', 'Kunjungi toko kami di Wonogiri untuk melihat, memilih, dan mencoba produk secara langsung.'],
                ['pesanan', 'Pesanan Mudah Dipantau', 'Pantau status pesanan, mulai dari proses pengerjaan hingga pengiriman, melalui website kami.'],
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

{{-- Bukti pekerjaan, ditaruh di bawah (KEP-062): yang mencari bukti akan
     menggulir sampai sini, yang mencari bola tidak perlu melewatinya. Bentuknya
     mengikuti contoh klien (KEP-106): sebaris foto dengan judul di atasnya,
     dan "Lihat semua" ke galeri lengkap. --}}
@if ($portofolio->isNotEmpty())
<section class="mx-auto max-w-6xl px-5 py-12 sm:py-16">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 class="judul-bagian text-[2rem] sm:text-[2.5rem]">Galeri portofolio</h2>
            <p class="mt-1.5 text-muted">Sekolah, instansi, dan komunitas di sekitar Wonogiri.</p>
        </div>
        <a href="{{ route('portofolio') }}" class="tautan inline-block py-1 pointer-coarse:py-3 text-sm font-medium">Lihat semua<span class="sr-only"> portofolio</span> &rarr;</a>
    </div>

    <div class="mt-7 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        @foreach ($portofolio as $p)
            @include('komponen.ubin-portofolio', ['p' => $p])
        @endforeach
    </div>
</section>
@endif

{{-- Testimoni menguatkan bukti di atasnya. Ditulis ulang toko dari pesan
     WhatsApp pelanggan, jadi tidak ada antrian moderasi yang menumpuk. Kartu
     berinisial, bernama, dan berbintang mengikuti contoh klien (KEP-106). --}}
@if ($testimoni->isNotEmpty())
<section class="border-y border-line bg-field">
    <div class="mx-auto max-w-6xl px-5 py-12 sm:py-16">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="judul-bagian text-[2rem] sm:text-[2.5rem]">Testimoni pelanggan</h2>
                <p class="mt-1.5 text-muted">Kata mereka yang sudah memesan di kami.</p>
            </div>
            <a href="{{ route('testimoni') }}" class="tautan inline-block py-1 pointer-coarse:py-3 text-sm font-medium">Lihat semua<span class="sr-only"> testimoni</span> &rarr;</a>
        </div>

        <div class="mt-7 grid gap-4 lg:grid-cols-3">
            @foreach ($testimoni as $t)
                @include('komponen.kartu-testimoni', ['t' => $t, 'ringkas' => true])
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
            <h2 class="judul-bagian text-[2rem] sm:text-[2.5rem]">Tentang {{ setting('toko_nama', 'kami') }}</h2>
            <div class="kolom-baca mt-4 space-y-4 leading-relaxed text-muted">
                @foreach (preg_split('/\R{2,}/', (string) setting('tentang_lengkap')) as $paragraf)
                    <p>{{ $paragraf }}</p>
                @endforeach
            </div>
        </div>

        <img src="{{ asset('logo-lengkap-640.webp') }}" srcset="{{ asset('logo-lengkap-640.webp') }} 640w, {{ asset('logo-lengkap.webp') }} 1000w" sizes="20rem" alt="{{ setting('toko_nama', 'Setia Sport') }}"
             width="1000" height="505" class="hidden w-full max-w-xs lg:block">
    </div>
</section>
@endif

{{-- Lokasi: satu-satunya cara mengambil barang, jadi ia bukan catatan kaki.
     Dua tempat dengan tugas berbeda: toko tempat pesanan diambil dan dilunasi,
     konveksi tempat jersey dikerjakan. --}}
<section class="mx-auto max-w-6xl px-5 py-12 sm:py-16">
    <h2 class="judul-bagian text-[2rem] sm:text-[2.5rem]">Toko dan konveksi kami</h2>

    <div class="mt-7 grid gap-4 md:grid-cols-2">
        @foreach ([
            [
                'judul' => 'Toko',
                'tugas' => 'Toko Olahraga & Lokasi Pengambilan Pesanan',
                'alamat' => setting('toko_alamat'),
                'jam' => setting('toko_jam_buka'),
                'peta' => setting('maps_embed') ?: tautan_peta((string) setting('toko_alamat')),
            ],
            [
                'judul' => 'Konveksi',
                'tugas' => 'Lokasi produksi jersey & konveksi '.setting('toko_nama', 'Setia Sport'),
                'alamat' => setting('konveksi_alamat'),
                'jam' => setting('konveksi_jam_buka'),
                'peta' => tautan_peta((string) setting('konveksi_alamat')),
            ],
        ] as $lokasi)
            @continue(! $lokasi['alamat'])

            <article class="kartu flex flex-col p-6 lg:p-8">
                <p class="text-xl font-extrabold">{{ $lokasi['judul'] }}</p>
                <p class="mt-1 text-sm font-semibold text-navy-700">{{ $lokasi['tugas'] }}</p>

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
<section class="motif text-white">
    <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-6 px-5 py-12">
        <div>
            <p class="judul-tampil text-[2rem] text-balance sm:text-[2.5rem]">Butuh perlengkapan olahraga atau jersey tim?</p>
            <p class="mt-2 leading-relaxed text-navy-200">
                Belanja perlengkapan olahraga dan pesan jersey custom langsung melalui website.
            </p>
            {{-- Nomornya ditulis, bukan hanya jadi tautan: sebagian orang menyimpan
                 nomornya dulu di kontak sebelum menghubungi, dan nomor yang hanya
                 hidup di dalam tautan tidak bisa disalin. --}}
            @if (setting('toko_telepon'))
                <p class="mt-3 text-sm text-navy-200">
                    <span class="tabular font-semibold text-white">WhatsApp toko {{ setting('toko_telepon') }}</span>,
                    {{ Str::lcfirst((string) setting('cs_jam_layanan')) }}
                </p>
            @endif
        </div>

        <div class="kelompok-tombol">
            <a href="{{ route('katalog') }}" class="tombol bg-white font-bold text-navy hover:bg-navy-50">Lihat produk</a>
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
