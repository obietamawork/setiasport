@php
    /** @var \App\Models\Product $produk */
    $stok = $produk->punyaJalurReadyStock();
    $kustom = $produk->punyaJalurKustom();
    /*
     * Harga kustom punya dua bentuk: tier menurut jumlah, dan matriks menurut
     * ukuran (KEP-039). Dulu kartu ini hanya membaca tier, sehingga produk yang
     * harganya diisi lewat matriks — dan itu sebagian besar produk klien —
     * tampil "Rp 0" di katalog.
     */
    $perUkuran = $kustom && $produk->relationLoaded('prices') && $produk->prices->isNotEmpty();
    $hargaKustom = match (true) {
        ! $kustom => null,
        $perUkuran => $produk->prices->min('harga_satuan'),
        default => $produk->priceTiers->min('harga_satuan'),
    };
    $gambar = $produk->images->first();

    // Di tab Kustom, harga dihitung ulang mengikuti jumlah yang diketik
    // pelanggan. Yang dicari kapten tim bukan "mulai dari", tapi harga di
    // jumlah dia sendiri.
    $modeJumlah = ($modeJumlah ?? false) && $kustom;
    $tiers = $modeJumlah && ! $perUkuran
        ? $produk->priceTiers->map(fn ($t) => [
            'min' => $t->min_qty, 'max' => $t->max_qty, 'harga' => $t->harga_satuan,
        ])->values()
        : null;

    // Seperti gambar klien (KEP-132): barang stok menyebut ketersediaannya,
    // barang kustom "±N hari kerja" berikon kalender. Waktu kustom dari
    // produknya (KEP-091).
    $varianAktif = $produk->relationLoaded('variants')
        ? $produk->variants->where('aktif', true)->values()
        : null;
    $tersedia = $varianAktif?->sum(fn ($v) => max(0, $v->stok_tersedia));
    $teksStok = match (true) {
        $tersedia === null => 'Siap '.siap_stok(),
        $tersedia > 0 => 'Stok tersedia',
        default => 'Stok habis',
    };
    $hariKerja = '±'.$produk->lead_time_hari.' hari kerja';

    // Tombol keranjang: barang stok yang hanya punya satu varian bersisa
    // langsung masuk keranjang. Yang perlu memilih ukuran atau warna, dan
    // barang kustom, dibuka halamannya: di sanalah pilihannya.
    $tambahLangsung = $stok && ! $kustom && $varianAktif?->count() === 1 && $tersedia > 0;
    $tujuan = $kustom && ! $stok ? route('kustom.buat', $produk) : route('produk', $produk);
@endphp

{{-- Kartu berupa blok, bukan satu tautan besar: tombol keranjang di dalamnya
     tidak boleh berada di dalam <a>. Judulnya membawa tautan yang direntangkan
     menutupi kartu. --}}
<div class="kartu kartu-tautan group relative flex flex-col overflow-hidden">

    {{-- shrink-0 dan grow-0 wajib: sebagai anak flex, kotak berasio ini ikut
         meregang mengisi tinggi baris grid dan rasionya lepas. --}}
    <div @class(['relative aspect-square w-full shrink-0 grow-0',
                 'bg-white' => ! $gambar?->thumb_url && $produk->pola,
                 'bg-navy-50' => $gambar?->thumb_url || ! $produk->pola])>
        @if ($gambar?->thumb_url)
            {{-- Diposisikan absolut, bukan h-full. Tinggi persen tidak bisa
                 dipecahkan terhadap induk yang tingginya berasal dari rasio,
                 sehingga gambar memakai tinggi aslinya dan kotaknya ikut melar. --}}
            <img src="{{ $gambar->thumb_url }}" alt="{{ $gambar->alt ?? $produk->nama }}"
                 class="absolute inset-0 h-full w-full object-cover" loading="lazy" decoding="async">
        @elseif ($produk->pola)
            {{-- Sketsa pola hitam putih (KEP-106) selama belum ada foto: lebih
                 jujur daripada foto contoh, dan tetap menunjukkan bentuknya. --}}
            <div class="absolute inset-x-0 top-[12%] bottom-[4%]">
                <x-pola :pola="$produk->pola" tampak="ringkas" />
            </div>
        @else
            {{-- Ikon jenis barang, bukan inisial seperti "BO" (KEP-167). Hiasan:
                 namanya sudah tertulis di bawah, jadi disembunyikan dari pembaca layar. --}}
            @php
                $ikonBarang = ikon_barang($produk->nama);
            @endphp
            <span aria-hidden="true" class="absolute inset-0 flex items-center justify-center">
                <span class="flex size-16 items-center justify-center rounded-full bg-field text-navy shadow-sm">
                    <x-ikon :nama="$ikonBarang" ukuran="size-8" />
                </span>
            </span>
        @endif

        {{-- Penanda jalur ditempel di gambar, bukan menempati baris teks
             sendiri: di kartu sesempit ini setiap baris berharga. Katanya dari
             klien (KEP-132), warnanya dari Kit Tim (KEP-167): putih bertitik
             hijau untuk stok, navy untuk pesanan kustom. --}}
        <div class="absolute top-2 left-2 flex flex-wrap gap-1">
            @if ($stok) <span class="lencana lencana-stok">Ready Stock</span> @endif
            @if ($kustom) <span class="lencana lencana-kustom">Pre Order</span> @endif
        </div>
    </div>

    <div class="flex flex-1 flex-col gap-1 p-2.5 sm:p-3">
        <h3 class="line-clamp-2 text-sm leading-snug font-bold">
            <a href="{{ route('produk', $produk) }}" class="before:absolute before:inset-0 before:content-['']">{{ $produk->nama }}</a>
        </h3>

        {{-- Harga satu baris penuh; di bawahnya ketersediaan atau hari kerja
             sejajar dengan tombol keranjang, seperti gambar klien. --}}
        <div class="mt-auto pt-1">
            @if ($modeJumlah && $perUkuran)
                {{-- Harga matriks tidak berubah menurut jumlah, melainkan menurut
                     ukuran badan. Yang ditampilkan harga termurahnya, dengan kata
                     "mulai" supaya tidak terbaca sebagai harga pasti untuk semua
                     ukuran. --}}
                <div x-data="{ moq: {{ $produk->moq }}, mulai: {{ (int) $hargaKustom }} }">
                    <p class="tabular text-[15px] font-extrabold">
                        <span class="text-xs font-medium text-muted">Mulai</span>
                        {{ rupiah($hargaKustom) }}<span class="text-xs font-medium text-muted">/pcs</span>
                    </p>
                    <p class="truncate text-xs" :class="qty < moq ? 'font-semibold text-warn' : 'text-muted'"
                       x-text="qty < moq ? `Minimum ${moq} pcs` : 'mulai ' + rp(mulai * qty) + ' total'"></p>
                </div>
            @elseif ($modeJumlah)
                <div x-data="{ tiers: @js($tiers), moq: {{ $produk->moq }} }">
                    <p class="tabular text-[15px] font-extrabold">
                        <span x-text="rp(hargaDi(tiers))"></span><span class="text-xs font-medium text-muted">/pcs</span>
                    </p>
                    <p class="truncate text-xs" :class="qty < moq ? 'font-semibold text-warn' : 'text-muted'"
                       x-text="qty < moq ? `Minimum ${moq} pcs` : rp(hargaDi(tiers) * qty) + ' total'"></p>
                </div>
            @elseif ($stok)
                <p class="tabular text-[15px] font-extrabold">{{ rupiah($produk->harga_dasar) }}</p>
            @elseif ($hargaKustom)
                <p class="tabular text-[15px] font-extrabold">
                    @if ($perUkuran)
                        <span class="text-xs font-medium text-muted">Mulai</span>
                    @endif
                    {{ rupiah($hargaKustom) }}<span class="text-xs font-medium text-muted">/pcs</span>
                </p>
            @endif

        <div class="mt-1 flex items-center justify-between gap-2">
            <div class="min-w-0">
                @if ($stok)
                    <p @class(['truncate text-xs', 'text-muted' => $tersedia !== 0, 'font-semibold text-ink' => $tersedia === 0])>{{ $teksStok }}</p>
                @endif
                @if ($kustom)
                    <p class="flex items-center gap-1 truncate text-xs font-semibold text-ink">
                        <x-ikon nama="kalender" ukuran="size-3.5" class="shrink-0 text-muted" />
                        {{ $hariKerja }}
                    </p>
                @endif
            </div>

        {{-- Di atas tautan yang direntangkan (z-10), supaya bisa ditekan sendiri. --}}
        @if ($tambahLangsung)
            <form method="POST" action="{{ route('keranjang.tambah') }}" class="relative z-10 shrink-0">
                @csrf
                <input type="hidden" name="variant_id" value="{{ $varianAktif->first()->id }}">
                <input type="hidden" name="qty" value="1">
                <button type="submit" class="tombol-keranjang" title="Tambah ke keranjang">
                    <x-ikon nama="keranjang" ukuran="size-4" />
                    <span class="sr-only">Tambah {{ $produk->nama }} ke keranjang</span>
                </button>
            </form>
        @elseif ($kustom || ($tersedia ?? 1) > 0)
            <a href="{{ $tujuan }}" class="tombol-keranjang relative z-10 shrink-0"
               title="{{ $kustom ? 'Pesan' : 'Pilih ukuran' }}">
                <x-ikon nama="keranjang" ukuran="size-4" />
                <span class="sr-only">{{ $kustom ? 'Pesan' : 'Pilih ukuran' }} {{ $produk->nama }}</span>
            </a>
        @endif
        </div>
        </div>
    </div>
</div>
