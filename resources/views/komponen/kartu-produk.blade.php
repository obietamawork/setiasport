@php
    /** @var \App\Models\Product $produk */
    $stok = $produk->punyaJalurReadyStock();
    $kustom = $produk->punyaJalurKustom();
    $hargaKustom = $kustom ? $produk->priceTiers->min('harga_satuan') : null;
    $gambar = $produk->images->first();
@endphp

<a href="{{ route('produk', $produk) }}" class="kartu kartu-tautan group flex flex-col overflow-hidden">

    <div class="flex aspect-4/3 items-center justify-center border-b border-line bg-navy-50">
        @if ($gambar?->thumb_url)
            <img src="{{ $gambar->thumb_url }}" alt="{{ $gambar->alt ?? $produk->nama }}"
                 width="{{ $gambar->lebar }}" height="{{ $gambar->tinggi }}"
                 class="h-full w-full object-cover" loading="lazy" decoding="async">
        @else
            <span class="text-3xl font-extrabold tracking-tight text-navy-300">
                {{ Str::upper(Str::substr($produk->nama, 0, 2)) }}
            </span>
        @endif
    </div>

    <div class="flex flex-1 flex-col gap-3 p-4">
        <div class="flex flex-wrap gap-1.5">
            @if ($stok) <span class="chip chip-navy">Stok toko</span> @endif
            @if ($kustom) <span class="chip chip-merah">Kustom</span> @endif
        </div>

        <h3 class="leading-snug font-bold">{{ $produk->nama }}</h3>

        <div class="mt-auto">
            @if ($stok)
                <p class="tabular text-lg font-extrabold">{{ rupiah($produk->harga_dasar) }}</p>
            @elseif ($hargaKustom)
                <p class="tabular text-lg font-extrabold">
                    {{ rupiah($hargaKustom) }}<span class="text-sm font-medium text-muted"> / pcs</span>
                </p>
            @endif

            {{-- Waktu adalah informasi utama: pertanyaan pertama pelanggan selalu
                 kapan bisa diambil, bukan berapa harganya. --}}
            <p class="mt-1 text-sm text-muted">
                @if ($stok && ! $kustom)
                    Siap diambil besok
                @elseif ($kustom && ! $stok)
                    {{ $produk->lead_time_hari }} hari kerja, min. {{ $produk->moq }} pcs
                @else
                    Besok kalau ambil stok, {{ $produk->lead_time_hari }} hari kalau dikustom
                @endif
            </p>
        </div>
    </div>
</a>
