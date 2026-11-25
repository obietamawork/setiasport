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
    public function __construct(
        private AlurPesanan $alur,
        private PengirimEmail $email,
    ) {}

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
        return $this->alasanTidakBisaDiajukan($order) === null;
    }

    /** Kenapa pelanggan tidak bisa mengajukan pembatalan, atau null kalau bisa. */
    public function alasanTidakBisaDiajukan(Order $order): ?string
    {
        return match (true) {
            // Paket yang sudah berangkat tidak bisa ditarik lewat tombol di web;
            // itu urusan retur, dan retur selalu lewat percakapan dengan toko.
            $order->status->final(), $order->status === OrderStatus::DIKIRIM => 'Pesanan ini tidak bisa diajukan pembatalan. Hubungi toko untuk membicarakannya.',
            $this->pengajuanTertunda($order) !== null => 'Pengajuan pembatalan pesanan ini sedang kami periksa.',
            // Pesanan kustom hanya bisa diajukan batal sampai tahap desain
            // (KEP-164): setelah disetujui, kain dicetak dengan nama tim.
            $this->tahap($order) === TahapPembatalan::PRODUKSI => 'Desain pesanan ini sudah disetujui dan masuk produksi, jadi pesanan tidak bisa dibatalkan lagi. '
                .'Hubungi toko kalau ada kendala.',
            default => null,
        };
    }

    /** Pesanan masih berjalan, tetapi pengajuannya tertutup karena sudah lewat tahap desain. */
    public function tertutupKarenaProduksi(Order $order): bool
    {
        return ! $order->status->final()
            && $order->status !== OrderStatus::DIKIRIM
            && $this->pengajuanTertunda($order) === null
            && $this->tahap($order) === TahapPembatalan::PRODUKSI;
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
        $penolakan = $this->alasanTidakBisaDiajukan($order);
        abort_if($penolakan !== null, 422, (string) $penolakan);

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

        $this->kabari($pembatalan);
    }

    /**
     * Keputusan dikirim ke email pelanggan (KEP-128). Kartu pengajuannya
     * berjanji "diberitahukan melalui email"; pelanggan tanpa email tetap
     * membacanya di halaman pesanan (PengirimEmail melewatinya).
     */
    private function kabari(Pembatalan $pembatalan): void
    {
        $this->email->kirim(Order::with('customer')->findOrFail($pembatalan->order_id), 'pembatalan-diputuskan');
    }

    /**
     * Toko yang membatalkan, misalnya pelanggan menelepon (KEP-098).
     *
     * Dicatat sebagai pembatalan yang langsung disetujui, dengan pengembalian
     * yang diketik admin, supaya pesanan berbayar yang batal selalu punya
     * jejak uangnya dan pelanggan membaca keputusan yang sama di halaman
     * pesanannya.
     */
    public function batalkanOlehToko(
        Order $order,
        string $alasan,
        int $refund,
        ?string $metode,
        ?int $olehUserId,
    ): Pembatalan {
        $order->loadMissing(['items.options', 'items.rosters', 'payments', 'adjustments']);
        $dibayar = $order->sudah_dibayar;
        $refund = max(0, min($refund, $dibayar));

        return DB::transaction(function () use ($order, $alasan, $refund, $metode, $olehUserId, $dibayar) {
            /** @var Pembatalan $pembatalan */
            $pembatalan = $order->pembatalans()->create([
                'status' => StatusPembatalan::DISETUJUI,
                'tahap' => $this->tahap($order),
                'alasan' => $alasan,
                'sudah_dibayar' => $dibayar,
                'refund_usulan' => $this->refundUsulan($order),
                'refund_jumlah' => $refund,
                'refund_metode' => $refund > 0 ? ($metode ?? 'TRANSFER') : null,
                'catatan_admin' => 'Dibatalkan oleh toko: '.$alasan,
                'diputuskan_oleh' => $olehUserId,
                'diputuskan_pada' => now(),
            ]);

            $this->alur->batalkan($order, $alasan, $olehUserId);

            return $pembatalan;
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

        $this->kabari($pembatalan);
    }
}
