@props(['terang' => false, 'tinggi' => 'h-8'])

@php
    /*
     * Berkas logo asli belum dipasang. Begitu tersedia, taruh di
     * public/logo.svg (atau logo.png) dan komponen ini memakainya otomatis
     * tanpa perlu menyentuh satu pun halaman.
     */
    $berkas = collect(['logo.svg', 'logo.png', 'logo.webp'])
        ->first(fn ($f) => file_exists(public_path($f)));
@endphp

@if ($berkas)
    <img src="{{ asset($berkas) }}" alt="{{ setting('toko_nama', 'Setia Sport') }}"
         class="{{ $tinggi }} w-auto">
@else
    <span class="wordmark {{ $terang ? 'wordmark-terang' : '' }} text-lg sm:text-xl">
        <span>SETIA SPORT</span>
        <span class="wordmark-kota">WONOGIRI</span>
    </span>
@endif
