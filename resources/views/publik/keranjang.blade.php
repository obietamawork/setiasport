@extends('layouts.publik')
@section('judul', 'Keranjang')
@section('subjudul', 'Keranjang')

@section('isi')
<div class="mx-auto max-w-4xl px-5 py-10">
    <h1 class="text-3xl font-extrabold">Keranjang</h1>

    @if ($isi->isEmpty())
        <div class="mt-8 border border-line bg-field p-10 text-center">
            <p class="text-xl font-bold">Keranjang masih kosong</p>
            <p class="kolom-baca mx-auto mt-3 text-muted">
                Barang yang Anda pilih akan muncul di sini. Mulai dari stok toko kalau
                butuh cepat, atau pesanan kustom kalau ingin desain sendiri.
            </p>
            <div class="mt-6 flex flex-wrap justify-center gap-3">
                <a href="{{ route('katalog', ['jalur' => 'stok']) }}" class="tombol tombol-garis">Lihat stok toko</a>
                <a href="{{ route('katalog', ['jalur' => 'kustom']) }}" class="tombol tombol-navy">Lihat pesanan kustom</a>
            </div>
        </div>
    @else
        @if ($adaMasalah)
            <p class="mt-6 border-l-2 border-merah bg-merah-50 px-4 py-3 text-sm">
                Ada barang yang stoknya berubah sejak Anda memasukkannya. Sesuaikan
                jumlahnya atau keluarkan dari keranjang sebelum melanjutkan.
            </p>
        @endif

        <div class="mt-6 divide-y divide-line border border-line bg-field">
            @foreach ($isi as $b)
                <div class="flex flex-wrap items-start gap-4 p-4">
                    <div class="min-w-50 flex-1">
                        <a href="{{ route('produk', $b['produk']) }}" class="tautan font-semibold">
                            {{ $b['produk']->nama }}
                        </a>
                        @if ($b['varian']?->label)
                            <p class="text-sm text-muted">{{ $b['varian']->label }}</p>
                        @endif

                        {{-- Baris kustom berharga per ukuran (KEP-039): jumlahnya
                             berasal dari daftar ukuran, jadi daftar itu yang
                             ditampilkan, bukan satu angka jumlah. --}}
                        @if ($b['ukuran'] ?? null)
                            <ul class="mt-1 space-y-0.5 text-sm text-muted">
                                @foreach ($b['rincian']?->baris ?? [] as $u)
                                    <li class="tabular">
                                        {{ $u['jumlah'] }} pcs
                                        {{ \App\Enums\KategoriUkuran::tryFrom($u['kategori'])?->labelUkuran($u['ukuran']) ?? $u['ukuran'] }}
                                        &middot; {{ rupiah($u['harga_satuan']) }} / pcs
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        @if (($b['rincian']->potongan ?? 0) > 0)
                            <p class="mt-1 text-sm font-medium text-ok">
                                Potongan {{ $b['produk']->diskon_min_qty }} pcs ke atas:
                                −{{ rupiah($b['rincian']->potongan) }}
                            </p>
                        @endif

                        @foreach ($b['rincian']?->komponenPerItem ?? [] as $k)
                            <p class="text-sm text-muted tabular">
                                {{ $k['label'] }}: {{ rupiah($k['jumlah']) }} (sekali)
                            </p>
                        @endforeach

                        @if ($b['masalah'])
                            <p class="mt-1 text-sm text-merah">{{ $b['masalah'] }}</p>
                        @endif
                    </div>

                    @if ($b['ukuran'] ?? null)
                        {{-- Jumlahnya diubah dengan menyusun ulang daftar ukurannya,
                             supaya jumlah potong dan daftar pemain tidak berselisih. --}}
                        <div class="text-sm">
                            <p class="tabular font-medium">{{ $b['qty'] }} pcs</p>
                            <a href="{{ route('kustom.buat', $b['produk']) }}" class="tautan text-xs">Ubah pesanan</a>
                        </div>
                    @else
                        <form method="POST" action="{{ route('keranjang.ubah') }}" class="flex items-center gap-2">
                            @csrf @method('PUT')
                            <input type="hidden" name="kunci" value="{{ $b['kunci'] }}">
                            <label class="sr-only" for="qty-{{ $b['kunci'] }}">Jumlah {{ $b['produk']->nama }}</label>
                            <input type="number" id="qty-{{ $b['kunci'] }}" name="qty" value="{{ $b['qty'] }}"
                                   min="1" class="kotak-input w-20 tabular text-right">
                            <button type="submit" class="tautan text-sm">Perbarui</button>
                        </form>
                    @endif

                    <div class="w-32 text-right">
                        <p class="tabular font-semibold">
                            {{ rupiah(($b['rincian']?->subtotal ?? 0) - ($b['rincian']->potongan ?? 0)) }}
                        </p>
                        @if ($b['rincian'] && ! ($b['ukuran'] ?? null))
                            <p class="text-xs text-muted tabular">{{ rupiah($b['rincian']->hargaSatuan) }} / pcs</p>
                        @endif
                        <form method="POST" action="{{ route('keranjang.hapus') }}" class="mt-1">
                            @csrf @method('DELETE')
                            <input type="hidden" name="kunci" value="{{ $b['kunci'] }}">
                            <button type="submit" class="tautan text-xs text-muted">Keluarkan</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6 flex flex-wrap items-center justify-between gap-4 border border-line bg-field p-5">
            <div>
                <p class="text-sm text-muted">Total</p>
                <p class="numeral mt-1 text-3xl text-merah">{{ rupiah($total) }}</p>
                <p class="mt-2 text-sm text-muted">Diambil di toko, tanpa ongkos kirim.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('katalog') }}" class="tombol tombol-garis">Tambah barang lain</a>
                <a href="{{ route('checkout') }}"
                   class="tombol tombol-utama {{ $adaMasalah ? 'pointer-events-none opacity-50' : '' }}">
                    Lanjut ke data pemesan
                </a>
            </div>
        </div>
    @endif
</div>
@endsection
