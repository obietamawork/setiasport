@extends('layouts.panel')
@section('judul', 'Kategori')

@section('isi')
<x-kop judul="Kategori"
       catatan="Jalan yang dipakai pelanggan menelusuri katalog. Urutannya di sini sama dengan urutan yang mereka lihat di halaman katalog.">
    <a href="{{ route('panel.kategori.create') }}" class="tombol tombol-navy tombol-sm">
        <x-ikon nama="tambah" ukuran="size-4" />
        Tambah kategori
    </a>
</x-kop>

@if ($induk->isEmpty())
    <div class="kartu mt-6 p-8 text-center">
        <x-ikon nama="kategori" ukuran="size-9" class="mx-auto text-navy-300" />
        <p class="mt-3 text-lg font-bold">Belum ada kategori</p>
        <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-muted">
            Kategori adalah cara pelanggan menelusuri katalog. Mulai dari yang paling besar,
            misalnya Jersey atau Sepatu, baru dipecah jadi subkategori kalau isinya sudah banyak.
        </p>
        <a href="{{ route('panel.kategori.create') }}" class="tombol tombol-navy mt-5">Tambah kategori pertama</a>
    </div>
@else
    <div class="mt-5 space-y-3">
        @foreach ($induk as $urutanInduk => $k)
            <section class="kartu overflow-hidden">
                {{-- Baris induk. Seluruh baris membuka halaman ubahnya; tombol
                     urutan dan jumlah produk berdiri di atasnya sebagai tautan
                     tersendiri supaya tidak ikut terbawa. --}}
                <div class="relative flex flex-wrap items-center gap-x-3 gap-y-2 px-4 py-3 hover:bg-navy-50">
                    <a href="{{ route('panel.kategori.edit', $k) }}"
                       class="font-semibold before:absolute before:inset-0 before:content-['']">
                        {{ $k->nama }}
                    </a>

                    <span class="text-xs text-muted tabular">/{{ $k->slug }}</span>

                    @unless ($k->aktif)
                        <span class="chip chip-netral">
                            <x-ikon nama="silang" ukuran="size-3.5" />
                            Disembunyikan
                        </span>
                    @endunless

                    <span class="ml-auto flex items-center gap-2">
                        @if ($k->products_count > 0)
                            <a href="{{ route('panel.produk.index', ['q' => $k->nama]) }}"
                               class="relative z-10 text-sm text-muted tabular hover:text-navy hover:underline">
                                {{ $k->products_count }} produk
                            </a>
                        @else
                            <span class="text-sm text-muted">kosong</span>
                        @endif

                        {{-- Geser urutan. Dua tombol kecil mengalahkan satu kotak
                             angka yang hasilnya baru terlihat setelah disimpan. --}}
                        <span class="relative z-10 flex">
                            <form method="POST" action="{{ route('panel.kategori.pindah', $k) }}">
                                @csrf
                                <input type="hidden" name="arah" value="naik">
                                <button type="submit" class="tombol tombol-halus tombol-sm px-2"
                                        title="Naikkan" @disabled($urutanInduk === 0)>
                                    <span class="sr-only">Naikkan {{ $k->nama }}</span>
                                    <x-ikon nama="turun" ukuran="size-4" class="rotate-180" />
                                </button>
                            </form>
                            <form method="POST" action="{{ route('panel.kategori.pindah', $k) }}">
                                @csrf
                                <input type="hidden" name="arah" value="turun">
                                <button type="submit" class="tombol tombol-halus tombol-sm px-2"
                                        title="Turunkan" @disabled($urutanInduk === $induk->count() - 1)>
                                    <span class="sr-only">Turunkan {{ $k->nama }}</span>
                                    <x-ikon nama="turun" ukuran="size-4" />
                                </button>
                            </form>
                        </span>
                    </span>
                </div>

                @if ($k->children->isNotEmpty())
                    <div class="divide-y divide-line border-t border-line bg-paper">
                        @foreach ($k->children as $urutanAnak => $anak)
                            <div class="relative flex flex-wrap items-center gap-x-3 gap-y-2 py-2.5 pr-4 pl-9 hover:bg-navy-50">
                                <span class="absolute top-0 bottom-0 left-5 w-px bg-line" aria-hidden="true"></span>

                                <a href="{{ route('panel.kategori.edit', $anak) }}"
                                   class="text-sm font-medium before:absolute before:inset-0 before:content-['']">
                                    {{ $anak->nama }}
                                </a>

                                <span class="text-xs text-muted tabular">/{{ $anak->slug }}</span>

                                @unless ($anak->aktif)
                                    <span class="chip chip-netral">
                                        <x-ikon nama="silang" ukuran="size-3.5" />
                                        Disembunyikan
                                    </span>
                                @endunless

                                <span class="ml-auto flex items-center gap-2">
                                    @if ($anak->products_count > 0)
                                        <a href="{{ route('panel.produk.index', ['q' => $anak->nama]) }}"
                                           class="relative z-10 text-sm text-muted tabular hover:text-navy hover:underline">
                                            {{ $anak->products_count }} produk
                                        </a>
                                    @else
                                        <span class="text-sm text-muted">kosong</span>
                                    @endif

                                    <span class="relative z-10 flex">
                                        <form method="POST" action="{{ route('panel.kategori.pindah', $anak) }}">
                                            @csrf
                                            <input type="hidden" name="arah" value="naik">
                                            <button type="submit" class="tombol tombol-halus tombol-sm px-2"
                                                    title="Naikkan" @disabled($urutanAnak === 0)>
                                                <span class="sr-only">Naikkan {{ $anak->nama }}</span>
                                                <x-ikon nama="turun" ukuran="size-4" class="rotate-180" />
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('panel.kategori.pindah', $anak) }}">
                                            @csrf
                                            <input type="hidden" name="arah" value="turun">
                                            <button type="submit" class="tombol tombol-halus tombol-sm px-2"
                                                    title="Turunkan" @disabled($urutanAnak === $k->children->count() - 1)>
                                                <span class="sr-only">Turunkan {{ $anak->nama }}</span>
                                                <x-ikon nama="turun" ukuran="size-4" />
                                            </button>
                                        </form>
                                    </span>
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        @endforeach
    </div>

    <p class="kolom-baca mt-5 text-sm leading-relaxed text-muted">
        Kategori yang masih dipakai produk tidak bisa dihapus. Kalau sudah tidak dipakai lagi,
        lebih aman disembunyikan daripada dihapus: pesanan lama tetap menyebut kategorinya.
    </p>
@endif
@endsection
