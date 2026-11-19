{{--
    Ringkasan total (KEP-124), menggantikan kartu "Uang" di samping. Subtotal
    dihitung balik dari total supaya selalu cocok dengan tagihan, apa pun
    yang ikut di dalamnya.
--}}
@php
    $penyesuaian = (int) $pesanan->adjustments->sum('jumlah');
    $subtotal = $pesanan->total - $penyesuaian - (int) $pesanan->biaya_layanan - (int) $pesanan->ongkir + (int) $pesanan->diskon;
@endphp

<section class="kartu-bayar">
    <h2 class="judul-kartu"><x-ikon nama="uang" class="text-navy-400" /> Ringkasan total</h2>

    <dl class="mt-4 space-y-2.5 text-sm">
        <div class="flex justify-between gap-3">
            <dt>Subtotal pesanan</dt>
            <dd class="tabular font-medium">{{ rupiah($subtotal) }}</dd>
        </div>
        <div class="flex justify-between gap-3">
            <dt>Penyesuaian biaya</dt>
            <dd class="tabular font-medium {{ $penyesuaian !== 0 ? 'text-ok' : '' }}">
                {{ $penyesuaian < 0 ? '−' : ($penyesuaian > 0 ? '+' : '') }}{{ rupiah(abs($penyesuaian)) }}
            </dd>
        </div>
        @if ($pesanan->diskon > 0)
            <div class="flex justify-between gap-3">
                <dt>Diskon</dt>
                <dd class="tabular font-medium text-ok">−{{ rupiah($pesanan->diskon) }}</dd>
            </div>
        @endif
        @if ($pesanan->biaya_layanan > 0)
            <div class="flex justify-between gap-3">
                <dt>Biaya layanan</dt>
                <dd class="tabular font-medium">{{ rupiah($pesanan->biaya_layanan) }}</dd>
            </div>
        @endif
        @if ($pesanan->dikirim())
            <div class="flex justify-between gap-3">
                <dt>Ongkos kirim</dt>
                <dd class="tabular font-medium {{ $pesanan->ongkir > 0 ? '' : 'text-merah' }}">
                    {{ $pesanan->ongkir > 0 ? rupiah($pesanan->ongkir) : 'belum diisi' }}
                </dd>
            </div>
        @endif

        <div class="flex items-center justify-between gap-3 rounded-[10px] bg-merah-50 px-3 py-3">
            <dt class="text-base font-bold text-merah">Total tagihan</dt>
            <dd class="tabular text-xl font-extrabold text-merah">{{ rupiah($pesanan->total) }}</dd>
        </div>

        <div class="flex justify-between gap-3 pt-1">
            <dt class="text-muted">Sudah dibayar</dt>
            <dd class="tabular font-medium text-ok">{{ rupiah($pesanan->sudah_dibayar) }}</dd>
        </div>
        <div class="flex justify-between gap-3">
            <dt class="font-semibold">Sisa</dt>
            <dd class="tabular font-semibold {{ $pesanan->sisa_tagihan > 0 ? 'text-merah' : 'text-ok' }}">
                {{ $pesanan->sisa_tagihan > 0 ? rupiah($pesanan->sisa_tagihan) : 'Lunas' }}
            </dd>
        </div>

        {{-- Sisa berhenti di nol, jadi kelebihan bayar setelah pesanan
             dikurangi (KEP-044) tidak pernah muncul sendiri. --}}
        @if ($pesanan->sudah_dibayar > $pesanan->total)
            <div class="flex justify-between gap-3 border-t border-line pt-2">
                <dt class="font-semibold">Kelebihan bayar</dt>
                <dd class="tabular font-semibold text-merah">{{ rupiah($pesanan->sudah_dibayar - $pesanan->total) }}</dd>
            </div>
        @endif
    </dl>

    @if ($pesanan->kode_ambil)
        <div class="mt-4 border-t border-line pt-3">
            <p class="text-sm text-muted">Kode ambil</p>
            <p class="numeral mt-1 text-2xl">{{ $pesanan->kode_ambil }}</p>
        </div>
    @endif
</section>
