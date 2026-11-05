@props([
    'pola',
    'tampak' => 'lengkap',
    'reaktif' => false,
    'celanaOpsional' => false,
])

@php
    /*
     * Gambar pola hitam putih (KEP-106): sketsa garis tampak depan dan belakang,
     * tanpa desain, seperti gambar teknis mentah di konveksi. Digambar sendiri
     * di kotak 240 satuan dengan sumbu tengah x = 120, jadi sisi kiri dan kanan
     * selalu simetris.
     *
     * tampak="lengkap": tiap bagian depan dan belakang, berlabel.
     * tampak="ringkas": paling banyak dua gambar tanpa label, untuk kartu
     *   produk. Satu bagian: depan dan belakang. Dua bagian: depan masing-masing.
     *
     * reaktif: bentuknya mengikuti pilihan di langkah pesan kustom lewat getter
     * Alpine polaLeher, polaLenganPanjang, polaSaku, dan polaCelana. Tanpa itu
     * yang digambar adalah bentuk bawaan polanya.
     *
     * @var \App\Enums\PolaPakaian $pola
     */
    $bagian = $pola->bagian();
    $leherBawaan = $pola->leherBawaan();

    $gambar = [];
    if ($tampak === 'ringkas') {
        $gambar = count($bagian) === 1
            ? [[$bagian[0], 'depan', null], [$bagian[0], 'belakang', null]]
            : array_map(fn ($b) => [$b, 'depan', null], $bagian);
    } else {
        foreach ($bagian as $b) {
            $gambar[] = [$b, 'depan', null];
            $gambar[] = [$b, 'belakang', null];
        }
        // Jersey yang celananya opsional ("Dengan Celana") baru menggambar
        // celana pendek kalau pilihan itu yang diambil.
        if ($reaktif && $celanaOpsional && $bagian === ['atasan']) {
            $gambar[] = ['celana_pendek', 'depan', 'polaCelana'];
            $gambar[] = ['celana_pendek', 'belakang', 'polaCelana'];
        }
    }

    // Gambar yang bentuknya tetap dipotong rapat supaya tidak tampak kecil di
    // kartu. Yang reaktif memakai kotak penuh: lengan panjang butuh ruang, dan
    // gambarnya tidak boleh melompat ukurannya saat pilihan berganti.
    $kotak = $reaktif ? [
        'atasan' => '0 0 240 180',
        'jaket' => '0 0 240 180',
        'celana_pendek' => '0 0 240 150',
        'celana_panjang' => '0 0 240 250',
    ] : [
        'atasan' => '38 10 164 148',
        'jaket' => '0 0 240 158',
        'celana_pendek' => '52 6 136 130',
        'celana_panjang' => '58 6 124 236',
    ];

    // Badan dan lengan atasan. Lengan panjang miring 45 derajat seperti
    // gambar teknis pada umumnya; tergantung lurus, lengannya terlihat kurus.
    $badanPendek = 'M102,18 L77,29 L44,58 L61,84 L73,68 L74,150 Q120,153 166,150 L167,68 L179,84 L196,58 L163,29 L138,18 Q120,24 102,18 Z';
    $badanPanjang = 'M102,18 L77,29 L12,94 L6.3,99.7 L20.3,113.7 L26,108 L73,68 L74,150 Q120,153 166,150 L167,68 L214,108 L219.7,113.7 L233.7,99.7 L228,94 L163,29 L138,18 Q120,24 102,18 Z';
    $jahitPendek = '<path class="j" d="M48,55 L65,81"/><path class="j" d="M192,55 L175,81"/>';
    $jahitPanjang = '<path class="g" d="M12,94 L26,108"/><path class="g" d="M228,94 L214,108"/>';
    $ketiak = '<path class="g" d="M77,29 Q70,48 73,68"/><path class="g" d="M163,29 Q170,48 167,68"/>';
    $kelim = '<path class="j" d="M74,144 Q120,147 166,144"/>';

    $leher = [
        'bulat' => [
            'depan' => '<path class="g" d="M138,18 C132,40 108,40 102,18"/><path class="g" d="M98,20 C105,46 135,46 142,20"/>',
            'belakang' => '<path class="g" d="M98,20 Q120,32 142,20"/>',
        ],
        'v' => [
            'depan' => '<path class="g" d="M138,18 L120,46 L102,18"/><path class="g" d="M98,20 L120,53 L142,20"/><path class="g" d="M120,46 L120,53"/>',
            'belakang' => '<path class="g" d="M98,20 Q120,32 142,20"/>',
        ],
        'kerah' => [
            'depan' => '<path d="M95,21 Q120,2 145,21 L138,18 Q120,26 102,18 Z"/>'
                .'<path d="M114,30 L114,74 L126,74 L126,30 Z"/>'
                .'<circle cx="120" cy="44" r="1.6"/><circle cx="120" cy="56" r="1.6"/><circle cx="120" cy="68" r="1.6"/>'
                .'<path d="M102,18 L95,21 L110,48 L120,31 Z"/><path d="M138,18 L145,21 L130,48 L120,31 Z"/>',
            'belakang' => '<path d="M95,21 Q120,36 145,21 L138,18 Q120,12 102,18 Z"/>',
        ],
    ];

    $saku = '<path d="M136,54 L154,54 L154,74 Q145,77 136,74 Z"/><path class="j" d="M136,58 L154,58"/>';

    $tali = '<circle cx="115" cy="19" r="1.4"/><circle cx="125" cy="19" r="1.4"/>'
        .'<path class="g" d="M115,20.4 Q112,34 108,44"/><path class="g" d="M125,20.4 Q128,34 132,44"/>';

    $label = ['atasan' => 'Atasan', 'jaket' => 'Jaket', 'celana_pendek' => 'Celana', 'celana_panjang' => 'Celana'];
    $banyakBagian = count($bagian) > 1 || ($reaktif && $celanaOpsional);
@endphp

<div {{ $attributes->class([
        'pola-sketsa text-ink',
        'grid grid-cols-2 gap-x-3 gap-y-4' => $tampak === 'lengkap',
        'flex h-full w-full items-center justify-center gap-[6%]' => $tampak === 'ringkas',
    ]) }}
     @if ($tampak === 'lengkap')
         role="img" aria-label="Sketsa pola {{ Str::lcfirst($pola->label()) }}, tampak depan dan belakang, tanpa desain"
     @else
         aria-hidden="true"
     @endif>
    @foreach ($gambar as [$b, $sisi, $syarat])
        <figure @class(['flex flex-col items-center', 'h-[80%] w-[44%]' => $tampak === 'ringkas'])
                @if ($syarat) x-show="{{ $syarat }}" x-cloak @endif>
            <svg viewBox="{{ $kotak[$b] }}" aria-hidden="true" focusable="false"
                 @class(['pola', 'h-full w-full' => $tampak === 'ringkas', 'h-auto w-full' => $tampak === 'lengkap'])>
                @if ($b === 'atasan')
                    @if ($reaktif)
                        <g x-show="!polaLenganPanjang"><path d="{{ $badanPendek }}"/>{!! $jahitPendek !!}</g>
                        <g x-show="polaLenganPanjang" x-cloak><path d="{{ $badanPanjang }}"/>{!! $jahitPanjang !!}</g>
                    @else
                        <path d="{{ $badanPendek }}"/>{!! $jahitPendek !!}
                    @endif
                    {!! $ketiak !!}{!! $kelim !!}

                    @if ($reaktif)
                        @foreach ($leher as $jenis => $bentuk)
                            <g x-show="polaLeher === '{{ $jenis }}'" @if ($jenis !== $leherBawaan) x-cloak @endif>{!! $bentuk[$sisi] !!}</g>
                        @endforeach
                        @if ($sisi === 'depan')
                            <g x-show="polaSaku" x-cloak>{!! $saku !!}</g>
                        @endif
                    @else
                        {!! $leher[$leherBawaan][$sisi] !!}
                    @endif
                @elseif ($b === 'jaket')
                    <path d="{{ $badanPanjang }}"/>{!! $jahitPanjang !!}{!! $ketiak !!}
                    <path class="g" d="M74,138 Q120,141 166,138"/>
                    @if ($sisi === 'depan')
                        <path class="g" d="M120,28 L120,151.5"/>
                        <path d="M102,18 L100,6 Q110,4 120,8 L120,28 Q110,27 102,18 Z"/>
                        <path d="M138,18 L140,6 Q130,4 120,8 L120,28 Q130,27 138,18 Z"/>
                        <rect x="117.5" y="29" width="5" height="9" rx="1.5"/>
                        <path class="g" d="M86,96 L95,122"/><path class="g" d="M154,96 L145,122"/>
                    @else
                        <path d="M102,18 L100,6 Q120,1 140,6 L138,18 Q120,24 102,18 Z"/>
                    @endif
                @elseif ($b === 'celana_pendek')
                    <path d="M68,26 L172,26 L180,124 L126,130 L120,84 L114,130 L60,124 Z"/>
                    <path d="M68,12 L172,12 L172,26 L68,26 Z"/>
                    <path class="g" d="M120,26 L120,84"/>
                    <path class="j" d="M61,118 L114,124"/><path class="j" d="M126,124 L179,118"/>
                    @if ($sisi === 'depan') {!! $tali !!} @endif
                @else
                    <path d="M70,26 L170,26 L174,72 L158,222 L156,236 L130,236 L130,222 L120,92 L110,222 L110,236 L84,236 L82,222 L66,72 Z"/>
                    <path d="M70,12 L170,12 L170,26 L70,26 Z"/>
                    <path class="g" d="M120,26 L120,92"/>
                    <path class="g" d="M82,222 L110,222"/><path class="g" d="M130,222 L158,222"/>
                    @if ($sisi === 'depan')
                        <path class="g" d="M71,32 L80,62"/><path class="g" d="M169,32 L160,62"/>
                        {!! $tali !!}
                    @endif
                @endif
            </svg>
            @if ($tampak === 'lengkap')
                <figcaption class="mt-1.5 text-xs text-muted">
                    {{ $banyakBagian ? $label[$b].' ' : '' }}{{ $sisi === 'depan' ? ($banyakBagian ? 'depan' : 'Depan') : ($banyakBagian ? 'belakang' : 'Belakang') }}
                </figcaption>
            @endif
        </figure>
    @endforeach
</div>
