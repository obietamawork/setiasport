@extends('layouts.publik')
@section('judul', 'Pesanan saya')

@section('isi')
@php use App\Enums\OrderStatus; @endphp

<div class="mx-auto max-w-4xl px-5 py-10">
    <div class="flex flex-wrap items-baseline justify-between gap-3">
        <div>
            <h1 class="text-3xl font-extrabold">Pesanan saya</h1>
            <p class="mt-1 text-sm text-muted">{{ $pelanggan->nama }}, {{ $pelanggan->email }}</p>
        </div>
        <form method="POST" action="{{ route('keluar') }}">
            @csrf
            <button type="submit" class="tautan text-sm text-muted">Keluar</button>
        </form>
    </div>

    @if ($pesanan->isEmpty())
        <div class="kartu mt-8 p-10 text-center">
            <p class="text-xl font-bold">Belum ada pesanan</p>
            <p class="kolom-baca mx-auto mt-3 text-muted">
                Pesanan yang Anda buat akan muncul di sini, lengkap dengan progres
                pengerjaannya.
            </p>
            <div class="mt-6 flex flex-wrap justify-center gap-3">
                <a href="{{ route('katalog', ['jalur' => 'stok']) }}" class="tombol tombol-garis">Lihat stok toko</a>
                <a href="{{ route('katalog', ['jalur' => 'kustom']) }}" class="tombol tombol-utama">Pesan kustom</a>
            </div>
        </div>
    @else
        <div class="mt-8 space-y-3">
            @foreach ($pesanan as $o)
                <a href="{{ route('lacak.token', $o->access_token) }}"
                   class="kartu kartu-tautan block p-5">
                    <div class="flex flex-wrap items-baseline justify-between gap-3">
                        <div>
                            <span class="tabular text-sm text-muted">{{ $o->kode }}</span>
                            <p class="mt-0.5 font-bold">{{ $o->status->label() }}</p>
                        </div>
                        <div class="text-right">
                            <p class="tabular font-extrabold">{{ rupiah($o->total) }}</p>
                            @if ($o->sisa_tagihan > 0)
                                <p class="tabular text-sm text-merah">Sisa {{ rupiah($o->sisa_tagihan) }}</p>
                            @else
                                <p class="text-sm text-ok">Lunas</p>
                            @endif
                        </div>
                    </div>

                    <p class="mt-3 text-sm text-muted">
                        {{ $o->items->pluck('nama_produk')->take(2)->implode(', ') }}
                        @if ($o->items->count() > 2)
                            dan {{ $o->items->count() - 2 }} barang lain
                        @endif
                    </p>

                    <div class="mt-3 flex flex-wrap items-center gap-3 text-sm">
                        <span class="tabular text-muted">
                            {{ $o->created_at->translatedFormat('j M Y') }}
                        </span>
                        @if ($o->status === OrderStatus::SIAP_DIAMBIL && $o->kode_ambil)
                            <span class="chip chip-merah">Kode ambil {{ $o->kode_ambil }}</span>
                        @elseif ($o->target_siap_pada)
                            <span class="chip chip-navy">
                                Target siap {{ $o->target_siap_pada->translatedFormat('j M Y') }}
                            </span>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-8">{{ $pesanan->links() }}</div>
    @endif
</div>
@endsection
