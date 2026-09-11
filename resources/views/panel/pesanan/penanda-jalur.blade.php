{{-- Penanda kecil yang menjelaskan kenapa satu pesanan muncul di tab ini.
     Pesanan campuran paling perlu ini: ia ada di tab Kustom, tapi isinya
     memuat barang stok yang ikut diserahkan bersamaan (KEP-027). --}}
@if ($o->punyaKustom() && $o->items->contains(fn ($i) => ! $i->kustom()))
    <span class="chip chip-kuning">Campuran</span>
@elseif ($o->punyaKustom())
    <span class="chip chip-navy">Kustom</span>
@endif

@if ($o->dikirim())
    <span class="chip chip-netral">{{ $o->no_resi ? 'Resi '.$o->no_resi : 'Dikirim' }}</span>
@endif
