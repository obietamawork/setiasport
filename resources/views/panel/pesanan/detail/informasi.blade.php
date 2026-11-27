{{-- Informasi pesanan (KEP-124): ringkasan yang ditanyakan orang di telepon. --}}
@php
    $aktif = $pesanan->items->reject(fn ($i) => $i->status_item === \App\Enums\OrderItemStatus::DIBATALKAN);
    $satu = $aktif->count() === 1 ? $aktif->first() : null;
    $kategori = $aktif->map(fn ($i) => $i->product?->category?->nama)->filter()->unique()->implode(', ');

    $baris = array_filter([
        ['Jenis pesanan', $satu ? $satu->nama_produk : $aktif->count().' barang'],
        $kategori !== '' ? ['Kategori', $kategori] : null,
        ['Jumlah', $aktif->sum('qty').' pcs'],
        $satu ? ['Harga satuan', rupiah($satu->harga_satuan)] : null,
        ['Pengambilan', $pesanan->metode_ambil->label()],
        ['Target siap', $pesanan->target_siap_pada?->translatedFormat('j M Y') ?? 'Belum ditentukan'],
        ['Pembayaran', $pesanan->tipe_pembayaran === 'DP' ? 'Uang muka, sisanya saat diambil' : 'Lunas di muka'],
    ]);
@endphp

<section class="kartu-bayar">
    <h2 class="judul-kartu"><x-ikon nama="daftar" class="text-navy-400" /> Informasi pesanan</h2>

    <dl class="mt-4 text-sm">
        @foreach ($baris as [$label, $nilai])
            <div class="grid grid-cols-[8rem_minmax(0,1fr)] gap-3 border-b border-line py-2">
                <dt class="text-muted">{{ $label }}</dt>
                <dd class="font-semibold">{{ $nilai }}</dd>
            </div>
        @endforeach
        <div class="mt-2 grid grid-cols-[8rem_minmax(0,1fr)] gap-3 rounded-[10px] bg-navy-50 px-3 py-2.5">
            <dt class="font-semibold">Total</dt>
            <dd class="tabular text-lg font-extrabold text-ink">{{ rupiah($pesanan->total) }}</dd>
        </div>
    </dl>
</section>
