<?php

namespace App\Services;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\PriceBasis;
use App\Enums\StatusPembatalan;
use App\Enums\TahapPembatalan;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Pembatalan;
use Illuminate\Support\Facades\DB;

/**
 * Pembatalan pesanan dan pengembalian uangnya (KEP-041).
 *
 * Pelanggan mengajukan, toko yang memutuskan. Perhitungannya diusulkan sistem
 * supaya admin tidak menghitung sendiri setiap kali, tetapi angka akhirnya
 * tetap diketik admin: uang keluar adalah keputusan toko, bukan keputusan
 * program.
 */
class KebijakanPembatalan
{
    public function __construct(private AlurPesanan $alur) {}

    /**
     * Tahap terjauh yang sudah dicapai pesanan.
     *
     * Barang stok yang baru disiapkan atau dipacking tetap dihitung AWAL:
     * barangnya utuh dan bisa dikembalikan ke rak, berbeda dengan kain yang
     * sudah tercetak nama tim.
     */
    public function tahap(Order $order): TahapPembatalan
    {
        $produksi = [
            OrderItemStatus::ANTRIAN_PRODUKSI, OrderItemStatus::PRINTING, OrderItemStatus::PRESS,
            OrderItemStatus::POTONG, OrderItemStatus::JAHIT, OrderItemStatus::QC,
            OrderItemStatus::MENUNGGU_PELUNASAN,
        ];

        $item = $order->items;

        if ($item->contains(fn (OrderItem $i) => $i->approved_at !== null
            || in_array($i->status_item, $produksi, true))) {
            return TahapPembatalan::PRODUKSI;
        }

        if ($item->contains(fn (OrderItem $i) => in_array($i->status_item, [
            OrderItemStatus::PROSES_DESAIN, OrderItemStatus::MENUNGGU_PERSETUJUAN,
        ], true))) {
            return TahapPembatalan::DESAIN;
        }

        return TahapPembatalan::AWAL;
    }

    /**
     * Biaya yang tetap keluar walau pesanan batal: setup desain dan setup
     * sablon, yang memang ditagihkan sekali per baris, bukan per potong.
     */
    public function biayaSetup(Order $order): int
    {
        return (int) $order->items
            ->flatMap(fn (OrderItem $i) => $i->options)
            ->where('basis', PriceBasis::PER_ITEM)
            ->sum('tambahan_harga');
    }

    public function refundUsulan(Order $order): int
    {
        $dibayar = $order->sudah_dibayar;

        return match ($this->tahap($order)) {
            TahapPembatalan::AWAL => $dibayar,
            TahapPembatalan::DESAIN => max(0, $dibayar - $this->biayaSetup($order)),
            TahapPembatalan::PRODUKSI => 0,
        };
    }

    public function bolehDiajukan(Order $order): bool
    {
        // Paket yang sudah berangkat tidak bisa ditarik lewat tombol di web;
        // itu urusan retur, dan retur selalu lewat percakapan dengan toko.
        return ! $order->status->final()
            && $order->status !== OrderStatus::DIKIRIM
            && $this->pengajuanTertunda($order) === null;
    }

    public function pengajuanTertunda(Order $order): ?Pembatalan
    {
        return $order->pembatalans()
            ->where('status', StatusPembatalan::DIAJUKAN->value)
            ->latest('id')
            ->first();
    }

    public function ajukan(Order $order, string $alasan): Pembatalan
    {
        abort_unless($this->bolehDiajukan($order), 422,
            'Pesanan ini tidak bisa diajukan pembatalan. Hubungi toko untuk membicarakannya.');

        return $order->pembatalans()->create([
            'status' => StatusPembatalan::DIAJUKAN,
            'tahap' => $this->tahap($order),
            'alasan' => $alasan,
            'sudah_dibayar' => $order->sudah_dibayar,
            'refund_usulan' => $this->refundUsulan($order),
        ]);
    }

    /**
     * Pesanan benar-benar dibatalkan di sini, bukan saat diajukan: selama toko
     * belum memutuskan, pekerjaan berjalan terus dan stok tetap dipesan.
     */
    public function setujui(
        Pembatalan $pembatalan,
        int $refund,
        ?string $metode,
        ?string $catatan,
        ?int $olehUserId,
    ): void {
        abort_unless($pembatalan->menunggu(), 422, 'Pengajuan ini sudah diputuskan.');

        // Tidak mungkin mengembalikan lebih banyak daripada yang pernah masuk.
        $refund = max(0, min($refund, $pembatalan->sudah_dibayar));

        DB::transaction(function () use ($pembatalan, $refund, $metode, $catatan, $olehUserId) {
            $this->alur->batalkan(
                $pembatalan->order,
                'Pembatalan disetujui: '.$pembatalan->alasan,
                $olehUserId,
            );

            $pembatalan->update([
                'status' => StatusPembatalan::DISETUJUI,
                'refund_jumlah' => $refund,
                'refund_metode' => $refund > 0 ? $metode : null,
                'catatan_admin' => $catatan,
                'diputuskan_oleh' => $olehUserId,
                'diputuskan_pada' => now(),
            ]);
        });
    }

    public function tolak(Pembatalan $pembatalan, string $catatan, ?int $olehUserId): void
    {
        abort_unless($pembatalan->menunggu(), 422, 'Pengajuan ini sudah diputuskan.');

        $pembatalan->update([
            'status' => StatusPembatalan::DITOLAK,
            'catatan_admin' => $catatan,
            'diputuskan_oleh' => $olehUserId,
            'diputuskan_pada' => now(),
        ]);
    }
}
