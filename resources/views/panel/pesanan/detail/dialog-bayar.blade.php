{{--
    Catat pembayaran yang diterima di toko (KEP-124), dibuka dari bilah
    bawah atau kartu Pembayaran. Jenis dan nominalnya mengikuti tahap
    pesanan, supaya DP di kasir tidak tercatat sebagai pelunasan penuh.
--}}
@php
    use App\Enums\OrderStatus;

    $jenisBawaan = $pesanan->status !== OrderStatus::MENUNGGU_PEMBAYARAN
        ? 'PELUNASAN'
        : ($pesanan->tipe_pembayaran === 'DP' ? 'DP' : 'FULL');
@endphp

<div x-data @buka-catat-bayar.window="$refs.dialogBayar.showModal()">
    <dialog x-ref="dialogBayar" class="dialog w-[min(36rem,calc(100vw-2rem))]" aria-labelledby="judul-catat-bayar">
        <form method="POST" action="{{ route('panel.pesanan.pembayaran.catat', $pesanan) }}" class="p-5 sm:p-6">
            @csrf
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 id="judul-catat-bayar" class="text-lg font-bold">Catat pembayaran</h2>
                    <p class="mt-1 text-sm text-muted">
                        Uang yang diterima di toko. Sisa tagihan {{ rupiah($pesanan->sisa_tagihan) }}.
                    </p>
                </div>
                <button type="button" class="tombol-ikon border-transparent" @click="$refs.dialogBayar.close()" aria-label="Tutup">
                    <x-ikon nama="silang" ukuran="size-4" />
                </button>
            </div>

            <div class="mt-5 grid gap-4 sm:grid-cols-3">
                <div>
                    <label for="jumlah" class="label-form">Nominal</label>
                    <input type="number" id="jumlah" name="jumlah"
                           value="{{ $jenisBawaan === 'DP' ? min($pesanan->dpMinimum(), $pesanan->sisa_tagihan) : $pesanan->sisa_tagihan }}"
                           class="kotak-input tabular" min="1" required>
                </div>
                <div>
                    <label for="metode" class="label-form">Metode</label>
                    <select id="metode" name="metode" class="kotak-input">
                        <option value="TUNAI">Tunai</option>
                        <option value="QRIS">QRIS</option>
                        <option value="TRANSFER">Transfer bank</option>
                    </select>
                </div>
                <div>
                    <label for="jenis" class="label-form">Jenis</label>
                    <select id="jenis" name="jenis" class="kotak-input">
                        <option value="PELUNASAN" @selected($jenisBawaan === 'PELUNASAN')>Pelunasan</option>
                        <option value="DP" @selected($jenisBawaan === 'DP')>Uang muka</option>
                        <option value="FULL" @selected($jenisBawaan === 'FULL')>Lunas</option>
                    </select>
                </div>
            </div>

            <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button type="button" class="tombol tombol-garis" @click="$refs.dialogBayar.close()">Batal</button>
                <button type="submit" class="tombol tombol-navy">
                    <x-ikon nama="uang" ukuran="size-4" /> Catat pembayaran
                </button>
            </div>
        </form>
    </dialog>
</div>
