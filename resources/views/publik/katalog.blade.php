@extends('layouts.publik')
@section('judul', $judulTab)
@section('subjudul', 'Katalog')
{{-- Kategori, jalur, dan halaman memang halaman berbeda; kata pencarian bukan. --}}
@section('kanonik', route('katalog', array_filter(request()->only('kategori', 'jalur', 'page'))))
@section('subcatatan', 'Pilih produk, lihat harganya, lalu pesan dari halaman produknya.')

@section('isi')
<div class="mx-auto max-w-6xl px-5 py-10">

    <h1 class="text-3xl font-extrabold sm:text-4xl">{{ $judul }}</h1>
    @if ($subjudul)
        <p class="kolom-baca mt-3 leading-relaxed text-muted">{{ $subjudul }}</p>
    @endif

    {{-- Perbedaan dua jalur belanja. Dulu satu bidang penuh setinggi layar, dan
         itu berarti pengunjung harus menggulir melewati penjelasan sebelum
         melihat satu pun produk. Isinya tetap, bentuknya diringkas jadi satu
         baris; yang ingin rinciannya sudah membacanya di halaman depan. --}}
    @unless (request()->hasAny(['jalur', 'kategori', 'q']))
        <div class="kartu mt-6 grid divide-y divide-line sm:grid-cols-2 sm:divide-x sm:divide-y-0">
            <a href="{{ route('katalog', ['jalur' => 'stok']) }}"
               class="group flex items-center gap-3 p-4 hover:bg-navy-50">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-[10px] bg-navy-50 text-navy">
                    <x-ikon nama="toko" />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block font-semibold">Stok toko &middot; siap {{ siap_stok() }}</span>
                    <span class="block text-sm text-muted">
                        Sudah ada di rak, {{ siap_stok() === 'hari ini' ? 'bayar dan ambil hari itu juga' : 'bayar hari ini, ambil '.siap_stok() }}.
                    </span>
                </span>
                <x-ikon nama="lanjut" ukuran="size-4" class="text-navy-300 group-hover:text-navy" />
            </a>

            <a href="{{ route('katalog', ['jalur' => 'kustom']) }}"
               class="group flex items-center gap-3 p-4 hover:bg-navy-50">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-[10px] bg-merah-50 text-merah">
                    <x-ikon nama="kategori" />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block font-semibold">Kustom{{ $waktuKustom ? ' · '.$waktuKustom : '' }}</span>
                    <span class="block text-sm text-muted">
                        Dihitung sejak desain Anda setujui, bukan sejak pesanan masuk.
                    </span>
                </span>
                <x-ikon nama="lanjut" ukuran="size-4" class="text-navy-300 group-hover:text-navy" />
            </a>
        </div>
    @endunless

    {{-- Stok toko dan Kustom adalah dua cara belanja yang berbeda, bukan
         sekadar dua nilai filter. Dipisah sebagai tab supaya perbedaannya
         terlihat sebelum pelanggan mulai menjelajah. --}}
    <div class="mt-6 flex gap-1 border-b border-line" role="tablist">
        @foreach ([['', 'Semua'], ['stok', 'Stok toko'], ['kustom', 'Kustom']] as [$nilai, $label])
            @php $aktifTab = request('jalur', '') === $nilai; @endphp
            <a href="{{ route('katalog', array_filter(['jalur' => $nilai, 'kategori' => request('kategori'), 'q' => request('q')])) }}"
               role="tab" aria-selected="{{ $aktifTab ? 'true' : 'false' }}"
               class="-mb-px px-4 py-2.5 text-sm font-semibold transition-colors
                      {{ $aktifTab ? 'garis-aktif text-navy' : 'text-muted hover:text-ink' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    {{-- Satu baris penyaring, bukan dua formulir bertumpuk. Kategori mengirim
         formulirnya sendiri saat dipilih: tombol "Terapkan" yang berdiri
         terpisah dari kotak pencarian membuat orang mengira keduanya dua
         pencarian yang berbeda. --}}
    <form method="GET" action="{{ route('katalog') }}" class="mt-5 flex flex-wrap items-center gap-2">
        {{-- Tab yang sedang aktif ikut terbawa saat filter dikirim. --}}
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
            <a href="{{ route('katalog', array_filter(['jalur' => request('jalur')])) }}"
               class="tombol tombol-halus">Hapus filter</a>
        @endif
    </form>

@php $modeJumlah = request('jalur') === 'kustom'; @endphp

    <div @if ($modeJumlah) x-data="katalogJumlah({{ $qtyAwal }})" @endif>

        <div class="mt-6 flex flex-wrap items-center justify-between gap-4">
            <p class="tabular text-sm text-muted">{{ $produk->total() }} produk</p>

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
