@extends('layouts.publik')
@section('judul', 'Pesanan saya')
@section('subjudul', 'Pesanan saya')

@section('isi')
@php
    use App\Enums\OrderItemStatus;
    use App\Enums\OrderStatus;
@endphp

<div class="mx-auto max-w-4xl px-5 py-10">
    @include('publik.akun.kepala', ['aktif' => 'pesanan'])

    @if ($jumlah[''] === 0)
        <div class="kartu mt-8 p-10 text-center">
            <p class="text-xl font-bold">Belum ada pesanan</p>
            <p class="kolom-baca mx-auto mt-3 text-muted">
                Pesanan yang Anda buat akan muncul di sini, lengkap dengan progres
                pengerjaannya.
            </p>
            <div class="kelompok-tombol mt-6 justify-center">
                <a href="{{ route('katalog', ['jalur' => 'stok']) }}" class="tombol tombol-garis">Lihat stok toko</a>
                <a href="{{ route('katalog', ['jalur' => 'kustom']) }}" class="tombol tombol-utama">Pesan kustom</a>
            </div>
        </div>
    @else
        {{-- Saringan status seperti papan kanvas Kit Tim (KEP-177). Kedaluwarsa
             ikut Dibatalkan: keduanya pesanan yang tidak jadi dikerjakan. --}}
        <nav class="segmen mt-5" aria-label="Saring pesanan">
            @foreach (['' => 'Semua', 'berjalan' => 'Berjalan', 'selesai' => 'Selesai', 'batal' => 'Dibatalkan'] as $nilai => $label)
                <a href="{{ route('pesanan-saya', $nilai === '' ? [] : ['status' => $nilai]) }}"
                   @class(['segmen-item segmen-pil', 'segmen-item-aktif' => $saringan === $nilai])
                   @if ($saringan === $nilai) aria-current="page" @endif>
                    {{ $label }} <span class="segmen-jumlah tabular">{{ $jumlah[$nilai] }}</span>
                </a>
            @endforeach
        </nav>

        @if ($pesanan->isEmpty())
            <div class="kartu mt-5 p-8 text-center">
                <p class="font-bold">Belum ada pesanan</p>
            </div>
        @else
            <div class="mt-5 space-y-3">
                @foreach ($pesanan as $o)
                    @php
                        // Foto barang pertama, seperti antrian divisi (KEP-129): mockup
                        // yang disetujui, lalu foto produk, lalu ikon jenis barangnya.
                        $pertama = $o->items->first();
                        $gambar = $pertama?->approvedDesign?->path
                            ? asset('storage/'.$pertama->approvedDesign->path)
                            : $pertama?->product?->images->first()?->thumb_url;

                        // Satu tombol aksi per pesanan sesuai keadaannya (KEP-177).
                        $lacak = route('lacak.token', $o->access_token);
                        $batal = in_array($o->status, [OrderStatus::DIBATALKAN, OrderStatus::KADALUARSA], true);
                        $siapAmbil = $o->status === OrderStatus::SIAP_DIAMBIL && $o->kode_ambil;
                        $persetujuan = $batal ? null : $o->items->first(fn ($i) => $i->status_item === OrderItemStatus::MENUNGGU_PERSETUJUAN);
                        $produkUlang = $o->status === OrderStatus::SELESAI && $pertama?->product?->aktif ? $pertama->product : null;
                        $aksi = match (true) {
                            $batal => [$lacak, 'Lihat detail', 'tombol-halus'],
                            $persetujuan !== null => [$lacak.'#persetujuan-'.$persetujuan->id, 'Periksa mockup', 'tombol-utama'],
                            $siapAmbil && ! $o->dikirim() => [$lacak.'#ambil-di-toko', 'Lihat petunjuk ambil', 'tombol-navy'],
                            $produkUlang !== null => [route('produk', $produkUlang), 'Pesan lagi', 'tombol-garis'],
                            default => null,
                        };
                    @endphp
                    <article @class(['kartu kartu-tautan relative overflow-hidden p-5', 'bg-paper' => $batal])>
                        {{-- Pita tiga warna menandai pesanan yang menunggu keputusan Anda. --}}
                        @if ($persetujuan)
                            <span class="pita absolute inset-x-0 top-0" aria-hidden="true"></span>
                        @endif

                        <div class="flex gap-4">
                            @if ($gambar)
                                <img src="{{ $gambar }}" alt="" width="64" height="64" loading="lazy"
                                     class="size-16 shrink-0 rounded-[12px] border border-line bg-paper object-cover">
                            @else
                                <span aria-hidden="true" class="flex size-16 shrink-0 items-center justify-center rounded-[12px] bg-navy-50 text-navy">
                                    <x-ikon :nama="ikon_barang($pertama?->nama_produk ?? '')" ukuran="size-8" />
                                </span>
                            @endif

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                                    {{-- Seluruh kartu bisa diketuk lewat tautan ini; tombol aksi
                                         di bawah berada di atasnya. --}}
                                    <a href="{{ $lacak }}" class="before:absolute before:inset-0 before:content-['']">
                                        <span class="tabular text-sm text-muted">{{ $o->kode }}</span>
                                        <span @class(['mt-0.5 block font-bold', 'text-muted' => $batal])>{{ $o->status->label() }}</span>
                                    </a>
                                    {{-- ml-auto: saat turun ke baris kedua di HP, harga tetap rata kanan. --}}
                                    <div class="ml-auto text-right">
                                        <p class="tabular font-extrabold">{{ rupiah($o->total) }}</p>
                                        @if ($o->sisa_tagihan > 0)
                                            <p class="tabular text-sm font-semibold text-ink">Sisa {{ rupiah($o->sisa_tagihan) }}</p>
                                        @else
                                            <p class="text-sm text-ok">Lunas</p>
                                        @endif
                                    </div>
                                </div>

                                <p class="mt-2 text-sm text-muted">
                                    {{ $o->items->pluck('nama_produk')->take(2)->implode(', ') }}
                                    @if ($o->items->count() > 2)
                                        dan {{ $o->items->count() - 2 }} barang lain
                                    @endif
                                </p>

                                <div class="mt-2 flex flex-wrap items-center gap-3 text-sm">
                                    <span class="tabular text-muted">
                                        {{ $o->created_at->translatedFormat('j M Y') }}
                                    </span>
                                    @if (! $siapAmbil && $o->target_siap_pada)
                                        <span class="chip chip-navy">
                                            Target siap {{ $o->target_siap_pada->translatedFormat('j M Y') }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Kode ambil dibaca keras-keras di kasir, jadi tampil besar
                             seperti di papan kanvas Kit Tim (KEP-176). --}}
                        @if ($siapAmbil)
                            <div class="mt-4 flex items-center justify-between gap-3 rounded-[10px] bg-ok-50 px-4 py-2.5">
                                <span class="text-sm font-semibold text-ok">Kode ambil</span>
                                <span class="angka-tampil tabular text-3xl tracking-[0.12em] text-ink">{{ $o->kode_ambil }}</span>
                            </div>
                        @endif

                        @if ($aksi)
                            <a href="{{ $aksi[0] }}" class="tombol {{ $aksi[2] }} relative z-10 mt-4 w-full sm:w-auto">
                                {{ $aksi[1] }}
                            </a>
                        @endif
                    </article>
                @endforeach
            </div>

            <div class="mt-8">{{ $pesanan->links() }}</div>
        @endif
    @endif

    <p class="mx-auto mt-8 max-w-md text-center text-sm text-muted">
        Pesanan yang dibuat tanpa masuk akun tetap bisa dibuka lewat
        <a href="{{ route('lacak') }}" class="tautan font-semibold">Lacak pesanan</a>
        dengan nomor pesanan dan email.
    </p>
    @include('publik.akun.keluar')
</div>
@endsection
