{{--
    Daftar pekerjaan (KEP-114), dipakai di halaman antrian dan di halaman
    daftar lengkapnya. Klien memintanya "dinggo pengingat pesanan": yang
    dicari mata adalah mana yang hampir telat, jadi target selesai ditandai
    merah begitu lewat dan kuning dua hari sebelumnya.

    @var \Illuminate\Support\Collection<int, \App\Models\OrderItem> $pekerjaan
--}}
@php
    $hariIni = now()->startOfDay();

    $penanda = function (?\Carbon\CarbonInterface $target) use ($hariIni): ?array {
        if (! $target) {
            return null;
        }

        $selisih = (int) $hariIni->diffInDays($target->copy()->startOfDay(), false);

        return match (true) {
            $selisih < 0 => ['Lewat target', 'chip-merah'],
            $selisih === 0 => ['Hari ini', 'chip-kuning'],
            $selisih === 1 => ['Besok', 'chip-kuning'],
            $selisih === 2 => ['2 hari lagi', 'chip-kuning'],
            default => null,
        };
    };
@endphp

{{-- Kartu di layar sempit: operator membuka antrian dari HP di dekat mesin. --}}
<ul class="divide-y divide-line md:hidden">
    @foreach ($pekerjaan as $item)
        @php
            $tahap = \App\Enums\ProductionStage::dariStatus($item->status_item);
            $target = $item->order->target_siap_pada;
            $tanda = $penanda($target);
        @endphp
        <li>
            <a href="{{ route('panel.pesanan.show', $item->order) }}" class="block px-4 py-3 hover:bg-navy-50">
                <div class="flex items-baseline justify-between gap-3">
                    <span class="tabular text-sm font-semibold text-navy-700">{{ $item->order->kode }}</span>
                    <span class="chip {{ $tahap?->warna() ?? 'chip-netral' }}">
                        @if ($tahap) <x-ikon :nama="$tahap->ikon()" ukuran="size-3.5" /> @endif
                        {{ $item->status_item->labelStaf() }}
                    </span>
                </div>
                <p class="mt-1 text-sm">{{ $item->nama_produk }} <span class="tabular text-muted">&middot; {{ $item->qty }} pcs</span></p>
                <p class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-muted">
                    {{ $item->order->customer->nama }}
                    <span>&middot;</span>
                    <span class="tabular">Target {{ $target?->translatedFormat('j M Y') ?? 'belum ditentukan' }}</span>
                    @if ($tanda)
                        <span class="chip {{ $tanda[1] }}">{{ $tanda[0] }}</span>
                    @endif
                </p>
            </a>
        </li>
    @endforeach
</ul>

{{-- Tanda tenggat di bawah tanggal, bukan di sampingnya: sebaris, kolom ini
     selebar 216px dan tabelnya terpotong di layar 64rem (KEP-143). Pembungkus
     geser hanya jaring pengaman; tabel ini tanpa menu tarik. --}}
<div class="hidden overflow-x-auto md:block">
<table class="tabel">
    <thead>
        <tr>
            <th>Kode pesanan</th>
            <th>Pelanggan</th>
            <th>Produk</th>
            <th>Tahap saat ini</th>
            <th class="text-right">Jumlah</th>
            <th>Target selesai</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($pekerjaan as $item)
            @php
                $tahap = \App\Enums\ProductionStage::dariStatus($item->status_item);
                $target = $item->order->target_siap_pada;
                $tanda = $penanda($target);
            @endphp
            <tr class="hover:bg-navy-50" data-tautan="{{ route('panel.pesanan.show', $item->order) }}">
                <td>
                    <a href="{{ route('panel.pesanan.show', $item->order) }}" class="tabular font-semibold text-navy-700">
                        {{ $item->order->kode }}
                    </a>
                </td>
                <td>{{ $item->order->customer->nama }}</td>
                <td>{{ $item->nama_produk }}</td>
                <td>
                    <span class="chip {{ $tahap?->warna() ?? 'chip-netral' }}">
                        @if ($tahap) <x-ikon :nama="$tahap->ikon()" ukuran="size-3.5" /> @endif
                        {{ $item->status_item->labelStaf() }}
                    </span>
                </td>
                <td class="tabular text-right whitespace-nowrap">{{ $item->qty }} pcs</td>
                <td>
                    <span class="tabular block whitespace-nowrap">{{ $target?->translatedFormat('j M Y') ?? '-' }}</span>
                    @if ($tanda)
                        <span class="chip {{ $tanda[1] }} mt-1">{{ $tanda[0] }}</span>
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
</div>
