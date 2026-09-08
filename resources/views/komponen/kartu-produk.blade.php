@php
    /** @var \App\Models\Product $produk */
    $stok = $produk->punyaJalurReadyStock();
    $kustom = $produk->punyaJalurKustom();
    $hargaKustom = $kustom ? $produk->priceTiers->min('harga_satuan') : null;
    $gambar = $produk->images->first();

    // Di tab Kustom, harga dihitung ulang mengikuti jumlah yang diketik
    // pelanggan. Yang dicari kapten tim bukan "mulai dari", tapi harga di
    // jumlah dia sendiri.
    $modeJumlah = ($modeJumlah ?? false) && $kustom;
    $tiers = $modeJumlah
        ? $produk->priceTiers->map(fn ($t) => [
            'min' => $t->min_qty, 'max' => $t->max_qty, 'harga' => $t->harga_satuan,
        ])->values()
        : null;

    // Satu baris pendek saja. Kartu marketplace selebar 160px tidak punya ruang
    // untuk kalimat, dan yang dicari pelanggan cuma "kapan bisa saya ambil".
    $waktu = match (true) {
        $stok && $kustom => 'Besok / '.$produk->lead_time_hari.' hari',
        $kustom => $produk->lead_time_hari.' hari kerja',
        default => 'Siap besok',
    };
@endphp

<a href="{{ route('produk', $produk) }}" class="kartu kartu-tautan group flex flex-col overflow-hidden">

    {{-- shrink-0 dan grow-0 wajib: sebagai anak flex, kotak berasio ini ikut
         meregang mengisi tinggi baris grid dan rasionya lepas. --}}
    <div class="relative aspect-square w-full shrink-0 grow-0 bg-navy-50">
        @if ($gambar?->thumb_url)
            {{-- Diposisikan absolut, bukan h-full. Tinggi persen tidak bisa
                 dipecahkan terhadap induk yang tingginya berasal dari rasio,
                 sehingga gambar memakai tinggi aslinya dan kotaknya ikut melar. --}}
            <img src="{{ $gambar->thumb_url }}" alt="{{ $gambar->alt ?? $produk->nama }}"
                 class="absolute inset-0 h-full w-full object-cover" loading="lazy" decoding="async">
        @else
            <span class="absolute inset-0 flex items-center justify-center text-2xl font-extrabold tracking-tight text-navy-300">
                {{ Str::upper(Str::substr($produk->nama, 0, 2)) }}
            </span>
        @endif

        {{-- Penanda jalur ditempel di gambar, bukan menempati baris teks
             sendiri: di kartu sesempit ini setiap baris berharga. --}}
        <div class="absolute top-2 left-2 flex flex-wrap gap-1">
            @if ($stok) <span class="chip chip-navy shadow-sm">Stok</span> @endif
            @if ($kustom) <span class="chip chip-merah shadow-sm">Kustom</span> @endif
        </div>
    </div>

    <div class="flex flex-1 flex-col gap-1 p-2.5 sm:p-3">
        <h3 class="line-clamp-2 text-sm leading-snug font-semibold">{{ $produk->nama }}</h3>

        <div class="mt-auto pt-1">
            @if ($modeJumlah)
                <div x-data="{ tiers: @js($tiers), moq: {{ $produk->moq }} }">
                    <p class="tabular text-[15px] font-extrabold">
                        <span x-text="rp(hargaDi(tiers))"></span><span class="text-xs font-medium text-muted">/pcs</span>
                    </p>
                    <p class="truncate text-xs" :class="qty < moq ? 'text-merah' : 'text-muted'"
                       x-text="qty < moq ? `Minimum ${moq} pcs` : rp(hargaDi(tiers) * qty) + ' total'"></p>
                </div>
            @elseif ($stok)
                <p class="tabular text-[15px] font-extrabold">{{ rupiah($produk->harga_dasar) }}</p>
                <p class="truncate text-xs text-muted">{{ $waktu }}</p>
            @elseif ($hargaKustom)
                <p class="tabular text-[15px] font-extrabold">
                    {{ rupiah($hargaKustom) }}<span class="text-xs font-medium text-muted">/pcs</span>
                </p>
                <p class="truncate text-xs text-muted">{{ $waktu }}</p>
            @else
                <p class="truncate text-xs text-muted">{{ $waktu }}</p>
            @endif
        </div>
    </div>
</a>
