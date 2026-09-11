@props(['terang' => false, 'tinggi' => 'h-8'])

@php
    /*
     * Logo asli dari klien (public/logo.png). Versi terangnya, dengan tulisan
     * navy diganti putih, dipakai di latar navy seperti kepala panel dan ruang
     * CS; logo navy di atas navy akan tenggelam. Kalau berkasnya hilang,
     * komponen jatuh ke wordmark CSS supaya kepala halaman tidak kosong.
     */
    $berkas = collect($terang ? ['logo-terang.png', 'logo.png'] : ['logo.png'])
        ->first(fn ($f) => file_exists(public_path($f)));
@endphp

@if ($berkas)
    <img src="{{ asset($berkas) }}" alt="{{ setting('toko_nama', 'Setia Sport') }} Wonogiri"
         width="634" height="160" class="{{ $tinggi }} w-auto">
@else
    <span class="wordmark {{ $terang ? 'wordmark-terang' : '' }} text-base whitespace-nowrap sm:text-xl">
        <span>SETIA SPORT</span>
        <span class="wordmark-kota hidden sm:inline">WONOGIRI</span>
    </span>
@endif
