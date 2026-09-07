@php
    /** @var \App\Models\Product $produk */
    $stok = $produk->punyaJalurReadyStock();
    $kustom = $produk->punyaJalurKustom();
    $hargaKustom = $kustom ? $produk->priceTiers->min('harga_satuan') : null;
@endphp

<a href="{{ route('produk', $produk) }}"
   class="group flex flex-col border border-line bg-field transition-colors hover:border-line-strong">

    <div class="flex aspect-4/3 items-center justify-center border-b border-line bg-paper">
        @if ($produk->images->isNotEmpty())
            <img src="{{ asset('storage/'.$produk->images->first()->path) }}"
                 alt="{{ $produk->images->first()->alt ?? $produk->nama }}"
                 class="h-full w-full object-cover" loading="lazy">
        @else
            {{-- Placeholder memakai inisial produk, bukan ikon generik. --}}
            <span class="t-wide text-4xl font-bold text-line-strong/80">{{ Str::upper(Str::substr($produk->nama, 0, 2)) }}</span>
        @endif
    </div>

    <div class="flex flex-1 flex-col gap-3 p-4">
        <div class="flex flex-wrap gap-1.5">
            @if ($stok)
                <span class="chip chip-stok">Stok toko</span>
            @endif
            @if ($kustom)
                <span class="chip chip-kustom">Kustom</span>
            @endif
        </div>

        <h3 class="t-wide text-base leading-snug font-bold">{{ $produk->nama }}</h3>

        <div class="mt-auto space-y-1">
            @if ($stok)
                <p class="tabular text-lg font-bold text-signal">{{ rupiah($produk->harga_dasar) }}</p>
            @elseif ($hargaKustom)
                <p class="tabular text-lg font-bold text-signal">
                    {{ rupiah($hargaKustom) }}<span class="text-sm font-medium text-muted"> / pcs</span>
                </p>
            @endif

            {{-- Waktu adalah informasi utama: pertanyaan pertama pelanggan selalu
                 "kapan bisa saya ambil", bukan "berapa harganya". --}}
            <p class="text-sm text-muted">
                @if ($stok && ! $kustom)
                    Siap diambil besok
                @elseif ($kustom && ! $stok)
                    {{ $produk->lead_time_hari }} hari kerja &middot; min. {{ $produk->moq }} pcs
                @else
                    Besok kalau ambil stok, {{ $produk->lead_time_hari }} hari kalau dikustom
                @endif
            </p>
        </div>
    </div>
</a>
