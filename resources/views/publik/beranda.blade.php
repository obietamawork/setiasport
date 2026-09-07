@extends('layouts.publik')

@section('isi')

{{-- Hero: dua jalur berdampingan, dibedakan oleh WAKTU. Pertanyaan pertama
     pelanggan selalu "kapan bisa saya ambil", jadi itu yang dijawab lebih
     dulu, bukan slogan. --}}
<section class="border-b border-line bg-field">
    <div class="mx-auto max-w-6xl px-5 pt-16 pb-12">
        <p class="text-sm font-semibold text-merah">Wonogiri</p>
        <h1 class="mt-3 max-w-4xl text-4xl leading-[1.08] font-extrabold text-balance sm:text-5xl lg:text-[3.4rem]">
            Perlengkapan tim, siap hari ini
            <span class="text-navy-400">atau dibuat dari nol.</span>
        </h1>
        <p class="kolom-baca mt-5 text-base leading-relaxed text-muted sm:text-lg">
            Bola, sepatu, dan jersey siap pakai ada di rak. Jersey tim dengan nama dan
            nomor punggung dikerjakan di workshop kami. Semuanya diambil sendiri di toko.
        </p>
    </div>

    <div class="mx-auto grid max-w-6xl border-t border-line lg:grid-cols-2">
        <div class="border-b border-line p-8 lg:border-r lg:border-b-0 lg:p-10">
            <p class="text-sm font-semibold text-muted">Ambil dari stok toko</p>
            <p class="numeral mt-5 text-6xl text-navy sm:text-7xl">Besok</p>
            <p class="mt-4 max-w-sm leading-relaxed text-muted">
                Barang yang sudah ada di rak. Bayar hari ini, ambil besok pada jam buka.
            </p>
            <a href="{{ route('katalog', ['jalur' => 'stok']) }}" class="tombol tombol-garis mt-7">
                Lihat stok toko
            </a>
        </div>

        {{-- Satu-satunya blok berani di halaman ini. Sisanya sengaja tenang. --}}
        <div class="relative overflow-hidden bg-navy p-8 text-white lg:p-10">
            <span class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-merah via-merah to-kuning"></span>
            <p class="text-sm font-semibold text-white/60">Dibuat untuk tim Anda</p>
            <p class="numeral mt-5 text-6xl sm:text-7xl">
                {{ $leadTime ?? 7 }} <span class="text-3xl font-bold sm:text-4xl">hari</span>
            </p>
            <p class="mt-4 max-w-sm leading-relaxed text-white/75">
                Hari kerja, dihitung sejak Anda menyetujui desain, bukan sejak pesanan
                masuk. Sampai desain disetujui, jam produksi belum berjalan.
            </p>
            <a href="{{ route('katalog', ['jalur' => 'kustom']) }}" class="tombol tombol-utama mt-7">
                Mulai pesan kustom
            </a>
        </div>
    </div>
</section>

@if ($kategori->isNotEmpty())
<section class="mx-auto max-w-6xl px-5 py-14">
    <h2 class="text-2xl font-extrabold">Kategori</h2>
    <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($kategori as $k)
            <a href="{{ route('katalog', ['kategori' => $k->slug]) }}"
               class="kartu kartu-tautan flex items-baseline justify-between gap-3 px-5 py-4 hover:bg-navy-50">
                <span class="font-semibold">{{ $k->nama }}</span>
                <span class="tabular text-sm text-muted">{{ $k->products_count }}</span>
            </a>
        @endforeach
    </div>
</section>
@endif

<section class="mx-auto max-w-6xl px-5 pb-6">
    <div class="flex items-baseline justify-between gap-4">
        <h2 class="text-2xl font-extrabold">Produk</h2>
        <a href="{{ route('katalog') }}" class="tautan text-sm">Lihat semua</a>
    </div>

    <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4 xl:grid-cols-5">
        @foreach ($produk as $p)
            @include('komponen.kartu-produk', ['produk' => $p])
        @endforeach
    </div>
</section>

{{-- Menjawab keberatan terbesar sebelum ditanyakan: kenapa tidak ada pengiriman. --}}
<section class="mx-auto max-w-6xl px-5 py-14">
    <div class="kartu p-8 lg:p-10">
        <h2 class="text-2xl font-extrabold">Diambil di toko, tanpa ongkos kirim</h2>
        <div class="mt-7 grid gap-8 sm:grid-cols-3">
            @foreach ([
                ['Anda tahu posisinya', 'Setiap pesanan punya halaman sendiri. Tahap desain, printing, jahit, sampai siap diambil terlihat di sana tanpa perlu bertanya.'],
                ['Desain disetujui dulu', 'Mockup dikirim sebelum apa pun dicetak. Produksi baru berjalan setelah Anda menyetujuinya.'],
                ['Bayar separuh di depan', 'Pesanan kustom cukup DP '.setting('dp_minimum_persen', 50).'%. Sisanya dibayar saat mengambil barang di toko.'],
            ] as [$judul, $isi])
                <div class="border-t-2 border-line-strong pt-4">
                    <p class="font-semibold">{{ $judul }}</p>
                    <p class="mt-2 text-sm leading-relaxed text-muted">{{ $isi }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

@endsection
