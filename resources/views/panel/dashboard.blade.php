@extends('layouts.panel')
@section('judul', 'Dashboard')

@section('isi')
<h1 class="t-wide text-2xl font-extrabold">Yang perlu ditangani</h1>
<p class="mt-1 text-sm text-muted">
    Daftar tindakan, bukan grafik. Kalau bagian ini kosong, katalog sedang sehat.
</p>

<div class="mt-6 space-y-4">

    @if ($tanpaTier->isNotEmpty())
        <section class="border-l-2 border-signal bg-field p-5">
            <h2 class="font-bold">Harga belum lengkap</h2>
            <p class="mt-1 text-sm text-muted">
                Produk kustom ini tayang tapi belum punya tier harga, jadi pelanggan
                tidak bisa menghitung biayanya sendiri dan akan bertanya ke admin.
            </p>
            <ul class="mt-3 space-y-1 text-sm">
                @foreach ($tanpaTier as $p)
                    <li>
                        <a href="{{ route('panel.produk.edit', $p) }}" class="tautan font-medium">{{ $p->nama }}</a>
                        <span class="text-muted tabular">{{ $p->sku }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($stokHabis->isNotEmpty())
        <section class="border-l-2 border-warn bg-field p-5">
            <h2 class="font-bold">Stok habis <span class="tabular font-normal text-muted">{{ $stokHabis->count() }}</span></h2>
            <ul class="mt-3 grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                @foreach ($stokHabis->take(10) as $v)
                    <li class="flex justify-between gap-3">
                        <span>{{ $v->product->nama }} <span class="text-muted">{{ $v->label }}</span></span>
                    </li>
                @endforeach
            </ul>
            @if ($stokHabis->count() > 10)
                <a href="{{ route('panel.stok.index') }}" class="tautan mt-3 inline-block text-sm">
                    Lihat semua di halaman stok
                </a>
            @endif
        </section>
    @endif

    @if ($stokMenipis->isNotEmpty())
        <section class="border-l-2 border-line-strong bg-field p-5">
            <h2 class="font-bold">Stok menipis <span class="tabular font-normal text-muted">{{ $stokMenipis->count() }}</span></h2>
            <ul class="mt-3 grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                @foreach ($stokMenipis->take(10) as $v)
                    <li class="flex justify-between gap-3">
                        <span>{{ $v->product->nama }} <span class="text-muted">{{ $v->label }}</span></span>
                        <span class="tabular text-muted">sisa {{ $v->stok_tersedia }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($tanpaTier->isEmpty() && $stokHabis->isEmpty() && $stokMenipis->isEmpty())
        <section class="border border-line bg-field p-8 text-center">
            <p class="t-wide text-lg font-bold">Tidak ada yang perlu ditangani</p>
            <p class="mt-2 text-sm text-muted">
                Harga lengkap dan stok masih aman. Waktu yang baik untuk menambah produk.
            </p>
            <a href="{{ route('panel.produk.create') }}" class="tombol tombol-lapangan mt-5">Tambah produk</a>
        </section>
    @endif
</div>

<h2 class="t-wide mt-10 text-lg font-extrabold">Ringkasan katalog</h2>
<dl class="mt-4 grid gap-px border border-line bg-line sm:grid-cols-3">
    <div class="bg-field p-5">
        <dt class="text-sm text-muted">Produk tayang</dt>
        <dd class="numeral mt-2 text-3xl">{{ $produkAktif }}</dd>
    </div>
    <div class="bg-field p-5">
        <dt class="text-sm text-muted">Produk disembunyikan</dt>
        <dd class="numeral mt-2 text-3xl">{{ $produkNonaktif }}</dd>
    </div>
    <div class="bg-field p-5">
        <dt class="text-sm text-muted">Tayang tanpa foto</dt>
        <dd class="numeral mt-2 text-3xl">{{ $tanpaFoto }}</dd>
    </div>
</dl>
@endsection
