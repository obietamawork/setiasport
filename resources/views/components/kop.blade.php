@props(['judul', 'catatan' => null, 'kembali' => null, 'kembaliLabel' => 'Kembali'])

{{--
    Kepala halaman panel.

    Ia menempel di bawah bilah atas karena halaman panel panjang: tombol aksi
    utama dan kotak pencarian dulu tertinggal di puncak, sehingga menekan
    "Catat pembayaran" berarti menggulung balik melewati sepuluh layar.

    Saat melayang, keterangannya disembunyikan. Yang dibutuhkan orang yang
    sedang menggulung adalah judul dan tombolnya, bukan kalimat penjelas yang
    sudah dibaca sekali di atas.
--}}
<div class="kop-lekat" x-data="{ lepas: false }"
     x-init="lepas = window.scrollY > 8"
     @scroll.window.throttle.50ms="lepas = window.scrollY > 8"
     :class="{ 'melayang': lepas }">
    <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-3">
        <div class="min-w-0">
            @if ($kembali)
                <a href="{{ $kembali }}"
                   class="mb-1 inline-flex items-center gap-1 text-sm font-medium text-muted hover:text-navy">
                    <x-ikon nama="kembali" ukuran="size-4" />
                    {{ $kembaliLabel }}
                </a>
            @endif

            <h1 class="kop-judul">{{ $judul }}</h1>

            @if ($catatan)
                <p class="kop-catatan" x-show="! lepas">{{ $catatan }}</p>
            @endif

            @isset($bawahJudul)
                <div class="mt-1">{{ $bawahJudul }}</div>
            @endisset
        </div>

        @if (trim($slot) !== '')
            <div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>
        @endif
    </div>
</div>
