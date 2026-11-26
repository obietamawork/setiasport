@extends('layouts.publik')
@section('judul', $judulTab)
{{-- Bilah yang menempel di HP memakai judul yang sama dengan tab dan judul
     besar (KEP-101), bukan "Katalog" untuk semua kategori dan pencarian. --}}
@section('subjudul', $judul)
@section('subcatatan', $produk->total() > 0 ? $produk->total().' produk' : 'Belum ada produk')
{{-- Kategori, jalur, dan halaman memang halaman berbeda; kata pencarian bukan. --}}
@section('kanonik', route('katalog', array_filter(request()->only('kategori', 'jalur', 'page'))))

@section('isi')
<div class="mx-auto max-w-6xl px-5 py-10">

    @if ($induk)
        <nav class="mb-2 text-sm text-muted" aria-label="Remah roti">
            <a href="{{ route('katalog') }}" class="tautan">Katalog</a>
            <span class="mx-2 text-line-strong">/</span>
            <a href="{{ route('katalog', ['kategori' => $induk->slug]) }}" class="tautan">{{ $induk->nama }}</a>
        </nav>
    @endif
    <h1 class="text-3xl font-extrabold sm:text-4xl">{{ $judulBesar }}</h1>
    @if ($keterangan)
        <p class="kolom-baca mt-3 leading-relaxed text-muted">{{ $keterangan }}</p>
    @endif

    {{-- Tiga cara belanja dalam satu baris (KEP-142). Dulu kartu penjelas dan
         tab di bawahnya menyaring hal yang sama dan terlihat dobel; kini kartu
         itulah penyaringnya, dan penjelasnya tetap terbaca setelah dipilih.
         Tautan berpindah halaman, jadi nav dengan aria-current, bukan
         role="tab" yang menjanjikan panel tab yang tidak ada (KEP-104). --}}
    @php
        $jalurAktif = request()->string('jalur')->toString();
        // Urutan bawaan tidak perlu ikut di tautan; yang dipilih pelanggan ikut terbawa.
        $urutDibawa = $urut === array_key_first(App\Http\Controllers\Publik\KatalogController::URUTAN) ? null : $urut;
    @endphp
    <nav class="mt-6 grid gap-2 sm:grid-cols-3 sm:gap-3" aria-label="Cara belanja">
        @foreach ([
            ['', 'Semua produk', 'Perlengkapan olahraga siap beli dan jersey custom dalam satu daftar.', 'produk', 'bg-navy-50 text-navy'],
            ['stok', 'Ready Stock', 'Produk tersedia dan siap dibeli. Pesanan dapat dikirim atau diambil langsung di toko.', 'toko', 'bg-navy-50 text-navy'],
            ['kustom', 'Custom Apparel (Pre-Order)', 'Estimasi produksi 7–10 hari kerja setelah desain disetujui.', 'kategori', 'bg-merah-50 text-merah'],
        ] as [$nilai, $label, $penjelasan, $ikon, $warnaIkon])
            @php $aktif = $jalurAktif === $nilai; @endphp
            <a href="{{ route('katalog', array_filter(['jalur' => $nilai, 'kategori' => request('kategori'), 'q' => request('q'), 'urut' => $urutDibawa])) }}"
               @if ($aktif) aria-current="page" @endif
               class="kartu flex items-start gap-3 p-3 transition-colors sm:p-4
                      {{ $aktif ? 'border-navy bg-navy-50 ring-1 ring-navy' : 'hover:border-navy-300 hover:bg-navy-50' }}">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-[10px] {{ $aktif ? 'bg-navy text-white' : $warnaIkon }}">
                    <x-ikon :nama="$ikon" />
                </span>
                <span class="min-w-0">
                    <span class="block font-semibold {{ $aktif ? 'text-navy-800' : '' }}">{{ $label }}</span>
                    <span class="mt-0.5 block text-sm leading-snug text-muted">{{ $penjelasan }}</span>
                </span>
            </a>
        @endforeach
    </nav>

    {{-- Satu baris penyaring, bukan dua formulir bertumpuk. Kategori mengirim
         formulirnya sendiri saat dipilih: tombol "Terapkan" yang berdiri
         terpisah dari kotak pencarian membuat orang mengira keduanya dua
         pencarian yang berbeda. --}}
    <form method="GET" action="{{ route('katalog') }}" id="saring-katalog" class="mt-5 flex flex-wrap items-center gap-2">
        {{-- Cara belanja yang sedang dipilih ikut terbawa saat filter dikirim. --}}
        <input type="hidden" name="jalur" value="{{ request('jalur') }}">

        <div class="relative min-w-52 flex-1">
            <label class="sr-only" for="q">Cari produk</label>
            <x-ikon nama="cari" ukuran="size-4"
                    class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-navy-300" />
            <input type="search" id="q" name="q" value="{{ request('q') }}"
                   placeholder="Cari jersey, bola, sepatu&hellip;" class="kotak-input pl-10">
        </div>

        <label class="sr-only" for="kategori">Kategori</label>
        <select id="kategori" name="kategori" class="kotak-input w-auto min-w-44"
                data-kirim-saat-ubah>
            <option value="">Semua kategori</option>
            @foreach ($semuaKategori as $k)
                <option value="{{ $k->slug }}" @selected(request('kategori') === $k->slug)>
                    {{ $k->parent?->nama ? $k->parent->nama.' / ' : '' }}{{ $k->nama }}
                </option>
            @endforeach
        </select>

        <button type="submit" class="tombol tombol-navy">Cari</button>

        @if (request()->hasAny(['q', 'kategori']))
            <a href="{{ route('katalog', array_filter(['jalur' => request('jalur'), 'urut' => $urutDibawa])) }}"
               class="tombol tombol-halus">Hapus filter</a>
        @endif
    </form>

@php $modeJumlah = request('jalur') === 'kustom'; @endphp

    <div @if ($modeJumlah) x-data="katalogJumlah({{ $qtyAwal }})" @endif>

        <div class="mt-6 flex flex-wrap items-center justify-between gap-x-4 gap-y-3">
            <p class="tabular text-sm text-muted">{{ $produk->total() }} produk</p>

            <div class="flex flex-wrap items-center gap-x-5 gap-y-3">
                @if ($modeJumlah && $produk->isNotEmpty())
                    {{-- Satu kolom ini mengubah katalog dari daftar menjadi alat
                         penawaran: kapten tim mengetik jumlah timnya, dan semua
                         harga langsung menyesuaikan. --}}
                    <div class="flex items-center gap-2">
                        <label for="qty-katalog" class="text-sm font-semibold whitespace-nowrap">
                            Untuk berapa pcs?
                        </label>
                        <x-jumlah id="qty-katalog" x-model.number="qty" max="100000" class="w-20" />
                    </div>
                @endif

                {{-- Urutan (KEP-165) ikut formulir penyaring lewat atribut form,
                     jadi kata pencarian dan kategori tetap terbawa saat diganti. --}}
                @if ($produk->total() > 1)
                    <div class="flex items-center gap-2">
                        <label for="urut" class="text-sm font-semibold whitespace-nowrap">Urutkan</label>
                        <select id="urut" name="urut" form="saring-katalog" class="kotak-input w-auto py-2 text-sm"
                                data-kirim-saat-ubah>
                            @foreach (App\Http\Controllers\Publik\KatalogController::URUTAN as $nilai => $label)
                                <option value="{{ $nilai }}" @selected($urut === $nilai)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>
        </div>

    @if ($produk->isEmpty())
        {{-- Layar kosong sebagai ajakan bertindak, bukan sekadar kabar buruk. --}}
        <div class="mt-4 border border-line bg-field p-10 text-center">
            <p class="text-xl font-bold">Tidak ada yang cocok dengan filter ini</p>
            <p class="kolom-baca mx-auto mt-3 text-muted">
                Coba hapus salah satu filter, atau lihat seluruh katalog. Kalau yang Anda
                cari memang belum ada di daftar, kami bisa membuatkannya lewat pesanan kustom.
            </p>
            <div class="kelompok-tombol mt-6 justify-center">
                <a href="{{ route('katalog') }}" class="tombol tombol-garis">Lihat semua produk</a>
                <a href="{{ route('katalog', ['jalur' => 'kustom']) }}" class="tombol tombol-navy">
                    Lihat pesanan kustom
                </a>
            </div>
        </div>
    @else
        {{-- Nama di tiap kartu berupa h3; tanpa h2 ini urutan judulnya melompat dari h1. --}}
        <h2 class="sr-only">Daftar produk</h2>
        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4 xl:grid-cols-5">
            @foreach ($produk as $p)
                @include('komponen.kartu-produk', ['produk' => $p, 'modeJumlah' => $modeJumlah])
            @endforeach
        </div>

        <div class="mt-10">
            {{ $produk->withQueryString()->links() }}
        </div>
    @endif
    </div>
</div>

@push('skrip')
<script nonce="{{ Vite::cspNonce() }}">
    function katalogJumlah(awal) {
        return {
            qty: awal,

            rp(n) {
                return 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(n || 0));
            },

            // Rumus tier yang sama dengan PriceCalculator di server.
            hargaDi(tiers) {
                const t = tiers.find(t => this.qty >= t.min && (t.max === null || this.qty <= t.max));
                return t ? t.harga : (tiers[0]?.harga ?? 0);
            },
        };
    }
</script>
@endpush
@endsection
