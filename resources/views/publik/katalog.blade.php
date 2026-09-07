@extends('layouts.publik')
@section('judul', $judul)

@section('isi')
<div class="mx-auto max-w-6xl px-5 py-10">

    <h1 class="t-wide text-3xl font-extrabold sm:text-4xl">{{ $judul }}</h1>
    @if ($subjudul)
        <p class="kolom-baca mt-3 leading-relaxed text-muted">{{ $subjudul }}</p>
    @endif

    <form method="GET" action="{{ route('katalog') }}" class="mt-8 border border-line bg-field">
        <div class="flex flex-wrap items-end gap-4 p-4">
            <div class="min-w-45 flex-1">
                <label for="q" class="label-form">Cari produk</label>
                <input type="search" id="q" name="q" value="{{ request('q') }}"
                       placeholder="Jersey, bola, sepatu…" class="kotak-input">
            </div>

            <div class="min-w-40">
                <label for="kategori" class="label-form">Kategori</label>
                <select id="kategori" name="kategori" class="kotak-input">
                    <option value="">Semua kategori</option>
                    @foreach ($semuaKategori as $k)
                        <option value="{{ $k->slug }}" @selected(request('kategori') === $k->slug)>
                            {{ $k->parent?->nama ? $k->parent->nama.' — ' : '' }}{{ $k->nama }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="min-w-40">
                <label for="jalur" class="label-form">Cara beli</label>
                <select id="jalur" name="jalur" class="kotak-input">
                    <option value="">Semua</option>
                    <option value="stok" @selected(request('jalur') === 'stok')>Stok toko</option>
                    <option value="kustom" @selected(request('jalur') === 'kustom')>Pesan kustom</option>
                </select>
            </div>

            <button type="submit" class="tombol tombol-lapangan">Terapkan</button>

            @if (request()->hasAny(['q', 'kategori', 'jalur']))
                <a href="{{ route('katalog') }}" class="tautan self-center text-sm text-muted">Hapus filter</a>
            @endif
        </div>
    </form>

    <p class="mt-6 text-sm text-muted tabular">
        {{ $produk->total() }} produk
    </p>

    @if ($produk->isEmpty())
        {{-- Layar kosong sebagai ajakan bertindak, bukan sekadar kabar buruk. --}}
        <div class="mt-4 border border-line bg-field p-10 text-center">
            <p class="t-wide text-xl font-bold">Tidak ada yang cocok dengan filter ini</p>
            <p class="kolom-baca mx-auto mt-3 text-muted">
                Coba hapus salah satu filter, atau lihat seluruh katalog. Kalau yang Anda
                cari memang belum ada di daftar, kami bisa membuatkannya lewat pesanan kustom.
            </p>
            <div class="mt-6 flex flex-wrap justify-center gap-3">
                <a href="{{ route('katalog') }}" class="tombol tombol-garis">Lihat semua produk</a>
                <a href="{{ route('katalog', ['jalur' => 'kustom']) }}" class="tombol tombol-lapangan">
                    Lihat pesanan kustom
                </a>
            </div>
        </div>
    @else
        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
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
