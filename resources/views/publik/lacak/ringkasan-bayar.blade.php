{{--
    Ringkasan pembayaran di halaman progres (KEP-122): total, yang sudah
    dibayar, dan sisanya, tanpa rincian. Rinciannya tetap ada di "Rincian
    biaya" di bawah.

    @var \App\Models\Order $order
--}}
@php
    use App\Enums\PaymentStatus;
    use App\Enums\PaymentType;

    $lunas = $order->payments->where('status', PaymentStatus::LUNAS);
    $hanyaDp = $lunas->isNotEmpty() && $lunas->every(fn ($p) => $p->jenis === PaymentType::DP);
    $persenDp = $hanyaDp && $order->total > 0 ? (int) round($order->sudah_dibayar / $order->total * 100) : null;
@endphp

<section class="kartu-bayar mt-6">
    <h2 class="flex items-center gap-2.5 text-lg font-extrabold"><x-ikon nama="uang" class="text-navy-400" /> Ringkasan pembayaran</h2>
    <dl class="mt-4 divide-y divide-line text-sm">
        <div class="flex items-center justify-between gap-3 py-2.5">
            <dt class="text-muted">Total pesanan</dt>
            <dd class="tabular font-semibold">{{ rupiah($order->total) }}</dd>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-3 py-2.5">
            <dt class="text-muted">{{ $hanyaDp ? 'Uang muka'.($persenDp ? ' ('.$persenDp.'%)' : '') : 'Sudah dibayar' }}</dt>
            <dd class="flex items-center gap-3">
                <span class="tabular font-bold">{{ rupiah($order->sudah_dibayar) }}</span>
                @if ($order->sudah_dibayar > 0)
                    <span class="chip chip-ok"><x-ikon nama="centang" ukuran="size-3.5" /> Lunas</span>
                @endif
            </dd>
        </div>
        <div class="flex items-center justify-between gap-3 py-2.5">
            <dt class="text-muted">{{ $order->sisa_tagihan > 0 ? 'Sisa pelunasan' : 'Sisa' }}</dt>
            <dd class="tabular font-bold {{ $order->sisa_tagihan > 0 ? 'text-ink' : 'text-ok' }}">
                {{ $order->sisa_tagihan > 0 ? rupiah($order->sisa_tagihan) : 'Lunas' }}
            </dd>
        </div>
    </dl>
    @if ($order->sisa_tagihan > 0 && $order->sudah_dibayar > 0)
        <p class="mt-3 flex items-start gap-2 rounded-[10px] bg-kuning-50 px-4 py-3 text-sm leading-relaxed">
            <x-ikon nama="kalender" ukuran="size-4" class="mt-0.5 text-warn" />
            Pelunasan dilakukan setelah pesanan selesai, atau saat pengambilan dan pengiriman.
        </p>
    @endif
</section>
