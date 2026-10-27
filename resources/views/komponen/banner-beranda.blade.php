{{--
    Banner promo di puncak halaman depan (KEP-092), diatur di Panel > Banner beranda.

    Satu banner tampil diam. Dua atau lebih bisa digeser: jari (scroll-snap
    bawaan peramban), panah di layar lebar, titik, atau tombol panah papan
    ketik. Tidak pernah bergeser sendiri: slide yang berganti saat orang masih
    membaca hanya mengganggu, dan WCAG 2.2.2 mewajibkan tombol jeda kalau iya.
    Hanya gambar pertama yang dimuat segera (gambar terbesar di layar pertama);
    sisanya menyusul saat mendekat.
--}}
@php $banyak = $banner->count() > 1; @endphp

<div class="relative"
     @if ($banyak)
         x-data="carouselBanner({{ $banner->count() }})"
         @keydown.left.prevent="ke(aktif - 1)" @keydown.right.prevent="ke(aktif + 1)"
         role="region" aria-roledescription="carousel" aria-label="Promo toko"
     @endif>

    <div class="banner-jalur" @if ($banyak) x-ref="jalur" @scroll.debounce.60ms="sinkron()" @endif>
        @foreach ($banner as $i => $b)
            @php $tautan = $b->tautan; @endphp
            <div class="relative w-full shrink-0 snap-start"
                 @if ($banyak)
                     role="group" aria-roledescription="slide" aria-label="{{ $i + 1 }} dari {{ $banner->count() }}"
                     :inert="aktif !== {{ $i }}"
                 @endif>
                <picture>
                    @if ($b->gambar_hp_path)
                        <source media="(max-width: 639px)" srcset="{{ asset('storage/'.$b->gambar_hp_path) }}">
                    @endif
                    <img src="{{ asset('storage/'.$b->gambar_path) }}"
                         alt="{{ $b->teks_di_atas || $tautan ? '' : $b->judul }}"
                         width="1920" height="720"
                         @if ($i === 0) fetchpriority="high" @else loading="lazy" @endif
                         class="block aspect-square w-full object-cover sm:aspect-[8/3] sm:max-h-[36rem]">
                </picture>

                @if ($b->teks_di_atas)
                    {{-- Bayangan dari bawah di HP, dari kiri di layar lebar: tulisan
                         putih tetap terbaca di atas foto seterang apa pun. --}}
                    <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-navy-900/90 via-navy-900/35 to-transparent sm:bg-gradient-to-r sm:from-navy-900/85 sm:via-navy-900/40"></div>
                    <div class="absolute inset-0 mx-auto flex max-w-6xl items-end px-5 pb-12 sm:items-center sm:pb-0">
                        <div class="max-w-xl">
                            <h2 class="text-2xl leading-tight font-extrabold text-balance sm:text-4xl lg:text-5xl">{{ $b->judul }}</h2>
                            @if ($b->subjudul)
                                <p class="mt-2 text-sm leading-relaxed text-white/80 sm:mt-4 sm:text-lg">{{ $b->subjudul }}</p>
                            @endif
                            @if ($tautan && $b->teks_tombol)
                                <a href="{{ $tautan }}" class="tombol tombol-utama mt-4 sm:mt-6">
                                    {{ $b->teks_tombol }}
                                    <x-ikon nama="lanjut" ukuran="size-4" />
                                </a>
                            @elseif ($tautan)
                                <a href="{{ $tautan }}" class="absolute inset-0"><span class="sr-only">{{ $b->judul }}</span></a>
                            @endif
                        </div>
                    </div>
                @elseif ($tautan)
                    {{-- Tulisannya sudah di gambar: seluruh banner menjadi tautan. --}}
                    <a href="{{ $tautan }}" class="absolute inset-0"><span class="sr-only">{{ $b->judul }}</span></a>
                @endif
            </div>
        @endforeach
    </div>

    @if ($banyak)
        <button type="button" @click="ke(aktif - 1)" aria-label="Banner sebelumnya"
                class="banner-panah left-4"><x-ikon nama="kembali" ukuran="size-5" /></button>
        <button type="button" @click="ke(aktif + 1)" aria-label="Banner berikutnya"
                class="banner-panah right-4"><x-ikon nama="lanjut" ukuran="size-5" /></button>

        {{-- Titiknya kecil, kotak ketuknya tidak (24px, WCAG 2.5.8). --}}
        <div class="absolute inset-x-0 bottom-2 flex justify-center gap-1">
            @foreach ($banner as $i => $b)
                <button type="button" @click="ke({{ $i }})" class="group p-2"
                        aria-label="Banner {{ $i + 1 }}: {{ $b->judul }}" :aria-current="aktif === {{ $i }}">
                    <span class="block size-2.5 rounded-full transition-colors"
                          :class="aktif === {{ $i }} ? 'bg-white' : 'bg-white/45 group-hover:bg-white/70'"></span>
                </button>
            @endforeach
        </div>

        <p class="sr-only" aria-live="polite" x-text="`Banner ${aktif + 1} dari {{ $banner->count() }}`"></p>
    @endif
</div>
