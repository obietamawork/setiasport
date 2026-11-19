{{--
    Riwayat aktivitas (KEP-124): pesanan dibuat, setiap perpindahan tahap,
    dan penyesuaian biaya, dari yang terlama. Catatan staf (alasan mundur,
    catatan Perbarui progres) ikut terbaca di sini, tidak di halaman pelanggan.
--}}
@php
    use App\Enums\OrderItemStatus;
    use App\Enums\OrderStatus;

    $banyakBarang = $pesanan->items->count() > 1;

    $riwayat = collect([[
        'waktu' => $pesanan->created_at,
        'judul' => 'Pesanan dibuat',
        'catatan' => null,
        'oleh' => null,
    ]]);

    foreach ($pesanan->statusLogs as $log) {
        $hanyaCatatan = $log->dari === $log->ke && $log->catatan;
        $label = OrderItemStatus::tryFrom($log->ke)?->labelStaf() ?? OrderStatus::tryFrom($log->ke)?->label() ?? $log->ke;

        $riwayat->push([
            'waktu' => $log->created_at,
            'judul' => $hanyaCatatan
                ? $log->catatan
                : ($banyakBarang && $log->orderItem ? $log->orderItem->nama_produk.': ' : '').$label,
            'catatan' => $hanyaCatatan ? null : $log->catatan,
            'oleh' => $log->pelaku?->name,
        ]);
    }

    foreach ($pesanan->adjustments as $p) {
        $riwayat->push([
            'waktu' => $p->created_at,
            'judul' => 'Penyesuaian biaya: '.$p->deskripsi.' ('.($p->jumlah < 0 ? '−' : '+').rupiah(abs($p->jumlah)).')',
            'catatan' => null,
            'oleh' => $p->pembuat?->name,
        ]);
    }

    $riwayat = $riwayat->filter(fn ($r) => $r['waktu'] !== null)->sortBy('waktu')->values();
@endphp

<section class="kartu-bayar">
    <h2 class="judul-kartu"><x-ikon nama="jam" class="text-navy-400" /> Riwayat aktivitas</h2>

    <ol class="garis-waktu mt-4 max-h-[28rem] overflow-y-auto pr-1">
        @foreach ($riwayat as $r)
            <li @class(['garis-waktu-kini' => $loop->last])>
                <p class="text-sm font-semibold">{{ $r['judul'] }}</p>
                @if ($r['catatan'])
                    <p class="text-xs text-muted">{{ $r['catatan'] }}</p>
                @endif
                <p class="tabular text-xs text-muted">
                    {{ $r['waktu']->translatedFormat('j M Y, H.i') }}{{ $r['oleh'] ? ' (oleh '.$r['oleh'].')' : '' }}
                </p>
            </li>
        @endforeach
    </ol>
</section>
