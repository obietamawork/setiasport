@props(['tahap', 'ukuran' => 'size-12'])

{{--
    Gambar alat kerja tiap meja produksi untuk lembar kerja (KEP-160): pekerja
    mengenali mejanya dari gambar dulu, baru dari tulisannya.

    @var \App\Enums\ProductionStage $tahap
--}}
@php
    use App\Enums\ProductionStage;

    $jalur = match ($tahap) {
        // Pena di atas tablet gambar.
        ProductionStage::DESAIN => '<rect x="6" y="10" width="30" height="24" rx="3"/><path d="M6 28h30"/><path d="M27 42l4-11 10-10 4 4-10 10z"/><path d="M38 24l4 4"/>',
        // Printer dengan kertas keluar.
        ProductionStage::PRINT => '<rect x="13" y="6" width="22" height="10" rx="1.5"/><rect x="6" y="16" width="36" height="16" rx="3"/><rect x="13" y="27" width="22" height="15" rx="1.5"/><path d="M18 33h12M18 37h8"/><path d="M35 22h2"/>',
        // Mesin press panas: alas, lengan, dan pelat atas.
        ProductionStage::PRESS => '<rect x="6" y="37" width="34" height="5" rx="1.5"/><rect x="9" y="31" width="24" height="6" rx="1"/><path d="M37 37V11"/><path d="M37 13H23"/><path d="M23 13v5"/><rect x="9" y="18" width="26" height="6" rx="1"/><path d="M37 11l7-6"/><path d="M14 28h14" stroke-dasharray="2 3"/>',
        // Gunting.
        ProductionStage::POTONG => '<circle cx="13" cy="35" r="5"/><circle cx="13" cy="13" r="5"/><path d="M17 16l24 20"/><path d="M17 32l24-20"/>',
        // Mesin jahit.
        ProductionStage::JAHIT => '<path d="M5 42h38"/><path d="M10 42V17a5 5 0 0 1 5-5h20a5 5 0 0 1 5 5v7H21v18"/><path d="M30 24v9"/><path d="M27 33h6"/><circle cx="34" cy="18" r="1.5"/>',
        // Lencana centang.
        ProductionStage::QC => '<path d="M24 5l5 4h6l2 6 5 4-2 6 2 6-5 4-2 6h-6l-5 4-5-4h-6l-2-6-5-4 2-6-2-6 5-4 2-6h6z"/><path d="M17 24l5 5 9-10"/>',
        // Kardus.
        ProductionStage::PACKING => '<path d="M7 15l17-8 17 8v19l-17 8-17-8z"/><path d="M7 15l17 8 17-8"/><path d="M24 23v19"/><path d="M15.5 11l17 8"/>',
    };
@endphp
<svg {{ $attributes->merge(['class' => $ukuran]) }} viewBox="0 0 48 48" fill="none" stroke="currentColor"
     stroke-width="2.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $jalur !!}</svg>
