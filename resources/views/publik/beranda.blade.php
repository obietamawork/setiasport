@extends('layouts.publik')
{{-- Nilai kedua @section TIDAK BOLEH null: Blade menganggapnya "buka blok"
     dan memulai buffer keluaran yang tidak pernah ditutup. Pengaturan yang
     belum diisi mengembalikan null, jadi cadangannya wajib ada. --}}
@section('judul', setting('tentang_judul') ?: 'Konveksi dan toko perlengkapan olahraga di Wonogiri')
@section('deskripsi', setting('tentang_ringkas') ?: 'Toko alat olahraga dan workshop jersey kustom di Wonogiri. Pesanan diambil sendiri di toko.')

@section('isi')

@php
    // Klien: konsultasi lewat chat CS di website (KEP-030). Kalau CS dimatikan
    // (KEP-033), tombolnya jatuh ke WhatsApp supaya ajakan utama tidak hilang.
    $csAktif = cs_aktif();
    $waKonsultasi = $csAktif ? null : tautan_wa(
        'Halo '.setting('toko_nama', 'Setia Sport').', saya mau tanya soal pemesanan jersey.'
    );
@endphp

{{-- Halaman depan menjawab "kalian ini siapa" lebih dulu, bukan "beli apa"
     (KEP-024). Pengurus sekolah dan klub memilih konveksi berdasarkan bukti
     pekerjaan dan alamat yang bisa didatangi; katalog baru berguna setelah itu. --}}
<section class="border-b border-line bg-field">
    <div class="mx-auto grid max-w-6xl items-center gap-8 px-5 pt-10 pb-14 sm:pt-16 lg:grid-cols-[minmax(0,1fr)_24rem] lg:gap-12">
    {{-- Emblem asli klien. Di layar lebar ia berdiri di kanan judul; di HP ia
         membuka halaman, karena klien ingin halaman depan mengutamakan merek. --}}
    <img src="{{ asset('logo-lengkap.webp') }}" alt="Setia Sport Wonogiri"
         width="1000" height="505" class="w-64 max-w-full sm:w-80 lg:order-last lg:w-full">

    <div>
        <h1 class="max-w-4xl text-4xl leading-[1.08] font-extrabold text-balance sm:text-5xl lg:text-[3.4rem]">
            {{ setting('tentang_judul', 'Konveksi dan toko perlengkapan olahraga di Wonogiri') }}
        </h1>

        @if (setting('tentang_ringkas'))
            <p class="kolom-baca mt-5 text-base leading-relaxed text-muted sm:text-lg">
                {{ setting('tentang_ringkas') }}
            </p>
        @endif

        <div class="mt-8 flex flex-wrap gap-3">
            @if ($csAktif)
                <button type="button" x-data @click="$dispatch('buka-cs', { topik: 'KUSTOM' })" class="tombol tombol-utama">
                    Konsultasi dengan CS
                </button>
            @elseif ($waKonsultasi)
                <a href="{{ $waKonsultasi }}" target="_blank" rel="noopener" class="tombol tombol-utama">
                    Konsultasi lewat WhatsApp
                </a>
            @endif
            <a href="{{ route('katalog') }}" class="tombol tombol-garis">Lihat katalog</a>
        </div>
    </div>
    </div>

    {{-- Tiga hal yang paling sering ditanyakan sebelum orang mau memulai. --}}
    <div class="mx-auto grid max-w-6xl border-t border-line sm:grid-cols-3">
        @foreach ([
            [($leadTime ?? 7).' hari', 'Waktu pengerjaan kustom', 'Hari kerja, dihitung sejak desain Anda setujui.'],
            [setting('dp_minimum_persen', 50).'%', 'Uang muka', 'Sisanya dilunasi setelah barang selesai dikerjakan, sebelum diambil.'],
            ['Ambil di toko', 'Tanpa ongkos kirim', 'Barang diserahkan langsung, bisa dicek di tempat sebelum dibawa pulang.'],
        ] as $i => [$angka, $judul, $isi])
            <div class="border-b border-line p-6 sm:border-b-0 {{ $i < 2 ? 'sm:border-r' : '' }} lg:p-8">
                <p class="numeral text-3xl text-navy sm:text-4xl">{{ $angka }}</p>
                <p class="mt-3 font-semibold">{{ $judul }}</p>
                <p class="mt-1.5 text-sm leading-relaxed text-muted">{{ $isi }}</p>
            </div>
        @endforeach
    </div>
</section>

{{-- Tentang usaha. Dilewati sepenuhnya kalau pemilik toko belum mengisinya,
     supaya halaman tidak menampilkan kerangka kosong. --}}
@if (setting('tentang_lengkap'))
<section class="mx-auto max-w-6xl px-5 py-16">
    <div class="grid gap-10 lg:grid-cols-[minmax(0,18rem)_minmax(0,1fr)]">
        <h2 class="text-2xl font-extrabold text-balance sm:text-3xl">Tentang kami</h2>
        <div class="kolom-baca space-y-4 leading-relaxed text-muted">
            @foreach (preg_split('/\R{2,}/', trim(setting('tentang_lengkap'))) as $paragraf)
                <p>{{ $paragraf }}</p>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Portofolio: bukti, bukan janji. Inilah alasan halaman ini ada. --}}
@if ($portofolio->isNotEmpty())
<section class="border-y border-line bg-field">
    <div class="mx-auto max-w-6xl px-5 py-16">
        <div class="flex flex-wrap items-baseline justify-between gap-4">
            <h2 class="text-2xl font-extrabold sm:text-3xl">Pernah kami kerjakan</h2>
            <p class="text-sm text-muted">Sekolah, instansi, dan komunitas di sekitar Wonogiri</p>
        </div>

        <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($portofolio as $p)
                <article class="kartu overflow-hidden">
                    @if ($p->gambar_path)
                        {{-- Tinggi persen tidak bisa diselesaikan di dalam kotak
                             aspect-ratio, jadi gambar diposisikan absolut. --}}
                        <div class="relative aspect-[4/3] bg-navy-50">
                            <img src="{{ asset('storage/'.($p->thumb_path ?: $p->gambar_path)) }}"
                                 alt="{{ $p->judul }}" loading="lazy"
                                 class="absolute inset-0 size-full object-cover">
                        </div>
                    @endif

                    <div class="p-5">
                        @if ($p->kategori)
                            <p class="text-xs font-semibold tracking-wide text-merah uppercase">{{ $p->kategori }}</p>
                        @endif

                        <p class="mt-1.5 font-bold text-balance">{{ $p->judul }}</p>

                        @if ($p->keterangan())
                            <p class="mt-1 text-sm text-muted">{{ $p->keterangan() }}</p>
                        @endif

                        @if ($p->ringkasan)
                            <p class="mt-3 text-sm leading-relaxed text-muted">{{ $p->ringkasan }}</p>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Cara kerja. Menjawab keberatan terbesar pemesan pertama kali: uangnya
     dibayar di depan, lalu apa yang terjadi. --}}
<section class="mx-auto max-w-6xl px-5 py-16">
    <h2 class="text-2xl font-extrabold sm:text-3xl">Cara memesan jersey tim</h2>

    <ol class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            ['Bicarakan dulu', 'Kirim contoh desain atau ceritakan maunya lewat '.kanal_kontak().'. Kami hitungkan harganya sesuai jumlah tim.'],
            ['Setujui mockupnya', 'Kami kirim gambar jadi sebelum apa pun dicetak. Boleh direvisi sampai '.setting('revisi_gratis_maks', 3).' kali.'],
            ['Kami kerjakan', 'Printing, jahit, sampai packing. Setiap tahapnya terlihat di halaman pesanan Anda sendiri.'],
            ['Lunasi, lalu ambil', 'Kami kabari begitu jadi. Lunasi sisanya lewat transfer atau di kasir, lalu ambil di toko dengan kode ambil.'],
        ] as $i => [$judul, $isi])
            <li class="border-t-2 border-line-strong pt-4">
                <p class="numeral text-sm text-muted">0{{ $i + 1 }}</p>
                <p class="mt-2 font-semibold">{{ $judul }}</p>
                <p class="mt-1.5 text-sm leading-relaxed text-muted">{{ $isi }}</p>
            </li>
        @endforeach
    </ol>
</section>

{{-- Testimoni. Sengaja setelah cara kerja: ia menguatkan yang barusan dibaca,
     bukan menggantikan penjelasannya. --}}
@if ($testimoni->isNotEmpty())
<section class="border-y border-line bg-field">
    <div class="mx-auto max-w-6xl px-5 py-16">
        <h2 class="text-2xl font-extrabold sm:text-3xl">Kata mereka yang sudah pesan</h2>

        <div class="mt-8 grid gap-4 lg:grid-cols-3">
            @foreach ($testimoni as $t)
                <figure class="kartu flex h-full flex-col p-6">
                    @if ($t->bintang)
                        <p class="text-kuning" aria-label="{{ $t->bintang }} dari 5 bintang">
                            {{ str_repeat('★', $t->bintang) }}<span class="text-line-strong">{{ str_repeat('★', 5 - $t->bintang) }}</span>
                        </p>
                    @endif

                    <blockquote class="mt-3 flex-1 leading-relaxed text-balance">{{ $t->isi }}</blockquote>

                    <figcaption class="mt-5 border-t border-line pt-4 text-sm">
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

{{-- Lokasi: satu-satunya cara mengambil barang, jadi ia bukan catatan kaki.
     Dua tempat dengan tugas berbeda: toko tempat pesanan diambil dan dilunasi,
     konveksi tempat jersey dikerjakan. Pelanggan harus tahu harus ke mana. --}}
<section class="mx-auto max-w-6xl px-5 py-16">
    <h2 class="text-2xl font-extrabold sm:text-3xl">Toko dan konveksi kami</h2>

    <div class="mt-8 grid gap-4 lg:grid-cols-2">
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
                // Dikonfirmasi klien: pesanan custom pun diambil di toko, bukan di sini.
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

    <div class="mt-4 grid gap-6 border-t border-line pt-8 lg:grid-cols-2 lg:gap-10">
        <div>
            <p class="font-semibold">Hubungi kami</p>
            <p class="mt-2 leading-relaxed text-muted">
                Pertanyaan, konsultasi jersey, dan komplain dilayani lewat {{ kanal_kontak() }},
                {{ Str::lcfirst((string) setting('cs_jam_layanan')) }}.
            </p>
            @if (setting('toko_telepon'))
                <p class="mt-3 text-sm">
                    WhatsApp toko <span class="tabular font-semibold">{{ setting('toko_telepon') }}</span>
                </p>
            @endif
            @if ($csAktif)
                <button type="button" x-data @click="$dispatch('buka-cs', {})" class="tombol tombol-navy mt-5">
                    Chat dengan CS
                </button>
            @elseif ($waKonsultasi)
                <a href="{{ $waKonsultasi }}" target="_blank" rel="noopener" class="tombol tombol-navy mt-5">
                    Hubungi lewat WhatsApp
                </a>
            @endif
        </div>

        <div>
            <p class="font-semibold">Diambil sendiri di toko</p>
            <p class="mt-2 leading-relaxed text-muted">
                Jersey dan barang konveksi selalu diambil di toko. Ukurannya dicoba di tempat,
                dan kalau ada yang meleset kami perbaiki saat itu juga. Perlengkapan olahraga
                yang sudah ada di rak bisa dikirim untuk pelanggan yang jauh; ongkirnya disepakati
                lewat {{ kanal_kontak() }} lebih dulu.
            </p>
            <a href="{{ route('lacak') }}" class="tautan mt-3 inline-block text-sm">Lacak pesanan yang sudah masuk</a>
        </div>
    </div>
</section>

{{-- Barulah katalog. Cuplikan saja: penelusuran sesungguhnya di halaman katalog. --}}
@if ($produk->isNotEmpty())
<section class="border-t border-line bg-field">
    <div class="mx-auto max-w-6xl px-5 py-16">
        <div class="flex flex-wrap items-baseline justify-between gap-4">
            <div>
                <h2 class="text-2xl font-extrabold sm:text-3xl">Yang ada di toko</h2>
                <p class="mt-2 leading-relaxed text-muted">
                    Bola, sepatu, dan perlengkapan latihan siap pakai, di samping jersey
                    yang kami kerjakan sendiri.
                </p>
            </div>
            <a href="{{ route('katalog') }}" class="tautan text-sm">Lihat katalog lengkap</a>
        </div>

        <div class="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-5">
            @foreach ($produk as $p)
                @include('komponen.kartu-produk', ['produk' => $p])
            @endforeach
        </div>
    </div>
</section>
@endif

@endsection
