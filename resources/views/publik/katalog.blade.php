@extends('layouts.publik')
@section('judul', $judul)

@section('isi')
<div class="mx-auto max-w-6xl px-5 py-10">

    <h1 class="text-3xl font-extrabold sm:text-4xl">{{ $judul }}</h1>
    @if ($subjudul)
        <p class="kolom-baca mt-3 leading-relaxed text-muted">{{ $subjudul }}</p>
    @endif

    {{-- Di layar sempit filter diringkas jadi satu baris. Formulir bertumpuk
         memakan hampir 300px sebelum produk pertama terlihat, padahal yang
         dicari pelanggan ada di bawahnya. --}}
    <form method="GET" action="{{ route('katalog') }}" class="mt-6"
          x-data="{ buka: {{ request()->hasAny(['kategori', 'jalur']) ? 'true' : 'false' }} }">

        <div class="flex gap-2">
            <label class="sr-only" for="q">Cari produk</label>
            <input type="search" id="q" name="q" value="{{ request('q') }}"
                   placeholder="Cari jersey, bola, sepatu" class="kotak-input flex-1">

            <button type="button" @click="buka = !buka"
                    class="tombol tombol-garis shrink-0 sm:hidden"
                    :aria-expanded="buka">
                Filter
                @if (request()->hasAny(['kategori', 'jalur']))
                    <span class="size-2 rounded-full bg-merah"></span>
                @endif
            </button>

            <button type="submit" class="tombol tombol-navy shrink-0">Cari</button>
        </div>

        {{-- x-show hanya mengatur layar sempit; mulai sm dipaksa tampil lewat
             CSS, karena !flex mengalahkan display none dari Alpine. --}}
        <div x-show="buka" x-cloak class="mt-3 flex flex-wrap gap-3 sm:!flex">
            <div class="min-w-44 flex-1 sm:max-w-56">
                <label for="kategori" class="label-form">Kategori</label>
                <select id="kategori" name="kategori" class="kotak-input">
                    <option value="">Semua kategori</option>
                    @foreach ($semuaKategori as $k)
                        <option value="{{ $k->slug }}" @selected(request('kategori') === $k->slug)>
                            {{ $k->parent?->nama ? $k->parent->nama.' / ' : '' }}{{ $k->nama }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="min-w-40 flex-1 sm:max-w-48">
                <label for="jalur" class="label-form">Cara beli</label>
                <select id="jalur" name="jalur" class="kotak-input">
                    <option value="">Semua</option>
                    <option value="stok" @selected(request('jalur') === 'stok')>Stok toko</option>
                    <option value="kustom" @selected(request('jalur') === 'kustom')>Pesan kustom</option>
                </select>
            </div>

            <div class="flex items-end gap-3">
                <button type="submit" class="tombol tombol-garis">Terapkan</button>
                @if (request()->hasAny(['q', 'kategori', 'jalur']))
                    <a href="{{ route('katalog') }}" class="tautan pb-2.5 text-sm text-muted">Hapus filter</a>
                @endif
            </div>
        </div>
    </form>

    <p class="mt-6 text-sm text-muted tabular">
        {{ $produk->total() }} produk
    </p>

    @if ($produk->isEmpty())
        {{-- Layar kosong sebagai ajakan bertindak, bukan sekadar kabar buruk. --}}
        <div class="mt-4 border border-line bg-field p-10 text-center">
            <p class="text-xl font-bold">Tidak ada yang cocok dengan filter ini</p>
            <p class="kolom-baca mx-auto mt-3 text-muted">
                Coba hapus salah satu filter, atau lihat seluruh katalog. Kalau yang Anda
                cari memang belum ada di daftar, kami bisa membuatkannya lewat pesanan kustom.
            </p>
            <div class="mt-6 flex flex-wrap justify-center gap-3">
                <a href="{{ route('katalog') }}" class="tombol tombol-garis">Lihat semua produk</a>
                <a href="{{ route('katalog', ['jalur' => 'kustom']) }}" class="tombol tombol-navy">
                    Lihat pesanan kustom
                </a>
            </div>
        </div>
    @else
        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4 xl:grid-cols-5">
            @foreach ($produk as $p)
                @include('komponen.kartu-produk', ['produk' => $p])
            @endforeach
        </div>

        <div class="mt-10">
            {{ $produk->withQueryString()->links() }}
        </div>
    @endif
</div>
@endsection
