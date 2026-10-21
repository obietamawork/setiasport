@extends('layouts.panel')
@section('judul', 'Produk')

@section('isi')
<x-kop judul="Produk"
       catatan="Semua yang tayang di katalog. Harga, varian, dan gambarnya diatur di halaman masing-masing produk.">
    <form method="GET" class="relative">
        <input type="hidden" name="saring" value="{{ $saring }}">
        <label for="cari-produk" class="sr-only">Cari produk</label>
        <x-ikon nama="cari" ukuran="size-4"
                class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-navy-300" />
        <input type="search" id="cari-produk" name="q" value="{{ request('q') }}"
               placeholder="Nama atau SKU&hellip;" class="kotak-input w-44 pl-9 sm:w-56">
    </form>

    <a href="{{ route('panel.produk.create') }}" class="tombol tombol-navy tombol-sm">
        <x-ikon nama="tambah" ukuran="size-4" />
        <span class="sr-only sm:not-sr-only">Tambah produk</span>
    </a>
</x-kop>

{{-- "Belum lengkap" ditaruh sebagai penyaring, bukan sekadar peringatan di
     dashboard: memberi tahu ada sepuluh produk bermasalah tanpa menyediakan
     jalan melihatnya satu per satu hanya membuat orang menyerah. --}}
<div class="segmen mt-1">
    @foreach ([
        '' => 'Semua',
        'tayang' => 'Tayang',
        'sembunyi' => 'Disembunyikan',
        'belum-lengkap' => 'Belum lengkap',
    ] as $nilai => $label)
        @continue($nilai === 'belum-lengkap' && ($jumlah[$nilai] ?? 0) === 0 && $saring !== $nilai)

        <a href="{{ route('panel.produk.index', array_filter(['saring' => $nilai, 'q' => request('q')])) }}"
           class="segmen-item {{ $saring === $nilai ? 'segmen-item-aktif' : '' }}">
            @if ($nilai === 'belum-lengkap')
                <x-ikon nama="peringatan" ukuran="size-4" />
            @endif
            {{ $label }}
            @if (($jumlah[$nilai] ?? 0) > 0)
                <span class="segmen-jumlah">{{ $jumlah[$nilai] }}</span>
            @endif
        </a>
    @endforeach
</div>

@if ($saring === 'belum-lengkap')
    <p class="kolom-baca mt-3 text-sm leading-relaxed text-muted">
        Produk di bawah ini tayang, tetapi belum punya gambar atau belum punya harga kustom.
        Tanpa gambar pelanggan jarang percaya; tanpa harga, ia harus bertanya ke admin dulu.
    </p>
@endif

@if ($produk->isEmpty())
    <div class="kartu mt-6 p-8 text-center">
        <x-ikon nama="produk" ukuran="size-9" class="mx-auto text-navy-300" />
        <p class="mt-3 text-lg font-bold">
            {{ request('q') || $saring ? 'Tidak ada produk yang cocok' : 'Belum ada produk' }}
        </p>
        <p class="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-muted">
            {{ request('q') || $saring
                ? 'Coba kata kunci lain, atau hapus penyaringnya.'
                : 'Tambahkan produk pertama, lalu lengkapi varian dan harganya.' }}
        </p>
        <a href="{{ route('panel.produk.create') }}" class="tombol tombol-navy mt-5">Tambah produk</a>
    </div>
@else
    {{-- Di bawah 48rem tabelnya berganti jadi kartu lewat .tabel-hp: enam kolom
         yang digeser ke samping praktis tidak terbaca di HP. --}}
    <div class="kartu-md mt-5">
        <table class="tabel tabel-hp tabel-lekat">
            <thead>
                <tr>
                    <th colspan="2">Produk</th>
                    <th>Kategori</th>
                    <th>Cara beli</th>
                    <th class="text-right">Harga</th>
                    <th class="text-right">Varian</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($produk as $p)
                    @php
                        $gambar = $p->images->first();
                        $kurang = array_filter([
                            $p->images->isEmpty() ? 'gambar' : null,
                            $p->punyaJalurKustom() && $p->priceTiers->isEmpty() && $p->prices->isEmpty()
                                ? 'harga' : null,
                        ]);
                    @endphp
                    <tr class="group relative hover:bg-navy-50 {{ $p->aktif ? '' : 'text-muted' }}">
                        {{-- Gambar kecil di kolomnya sendiri: itu yang paling cepat
                             dikenali mata saat mencari satu produk di antara lima puluh. --}}
                        <td class="hidden w-14 md:table-cell">
                            @if ($gambar)
                                {{-- thumb_url sudah tahu urusan berkas lokal maupun CDN;
                                     menyusun jalurnya sendiri di sini akan rusak begitu
                                     gambar dipindah ke CDN. --}}
                                <img src="{{ $gambar->thumb_url }}"
                                     alt="" width="40" height="40" loading="lazy"
                                     class="size-10 rounded-[6px] border border-line object-cover">
                            @else
                                <span class="flex size-10 items-center justify-center rounded-[6px] border border-dashed border-line-strong text-navy-300">
                                    <x-ikon nama="gambar" ukuran="size-4" />
                                </span>
                            @endif
                        </td>
                        <td data-utama>
                            <a href="{{ route('panel.produk.edit', $p) }}"
                               class="font-semibold text-navy-700 before:absolute before:inset-0 before:content-['']">
                                {{ $p->nama }}
                            </a>
                            <div class="text-xs text-muted tabular">{{ $p->sku }}</div>
                            <div class="mt-1 flex flex-wrap gap-1.5">
                                @unless ($p->aktif)
                                    <span class="chip chip-netral">
                                        <x-ikon nama="silang" ukuran="size-3.5" />
                                        Disembunyikan
                                    </span>
                                @endunless
                                @foreach ($kurang as $apa)
                                    <span class="chip chip-kuning">
                                        <x-ikon nama="peringatan" ukuran="size-3.5" />
                                        Belum ada {{ $apa }}
                                    </span>
                                @endforeach
                            </div>
                        </td>
                        <td data-kolom="Kategori">{{ $p->category->nama }}</td>
                        <td data-kolom="Cara beli">{{ $p->fulfillment_type->label() }}</td>
                        <td data-kolom="Harga" class="text-right tabular">
                            {{ $p->punyaJalurReadyStock() ? rupiah($p->harga_dasar) : '-' }}
                        </td>
                        <td data-kolom="Varian" class="text-right tabular">{{ $p->variants_count }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $produk->links() }}</div>
@endif
@endsection
