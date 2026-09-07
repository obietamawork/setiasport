<?php

namespace App\Services;

use App\Enums\DesignAssetType;
use App\Enums\OrderItemStatus;
use App\Enums\ProductionStage;
use App\Enums\ProductionStatus;
use App\Models\DesignAsset;
use App\Models\OrderItem;
use App\Models\OrderStatusLog;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

/**
 * Gerbang persetujuan desain — titik komersial paling penting di seluruh sistem.
 *
 * Sebelum disetujui: revisi gratis sampai batas tertentu, dan jam produksi
 * belum berjalan. Sesudah disetujui: desain terkunci, roster terkunci, dan
 * estimasi tanggal siap baru dihitung dari SAAT ITU — bukan dari tanggal
 * pesanan masuk (docs/02).
 */
class AlurDesain
{
    public function __construct(private OrderStatusResolver $resolver) {}

    /** Desainer mengunggah mockup baru; pelanggan diminta menyetujuinya. */
    public function unggahMockup(
        OrderItem $item,
        ?string $path,
        ?string $externalUrl,
        ?string $catatan,
        int $olehUserId,
    ): DesignAsset {
        return DB::transaction(function () use ($item, $path, $externalUrl, $catatan, $olehUserId) {
            $versi = (int) $item->designAssets()
                ->where('tipe', DesignAssetType::MOCKUP->value)
                ->max('versi') + 1;

            $aset = $item->designAssets()->create([
                'tipe' => DesignAssetType::MOCKUP,
                'versi' => $versi,
                'path' => $path,
                'external_url' => $externalUrl,
                'catatan' => $catatan,
                'diunggah_oleh' => $olehUserId,
            ]);

            // Pekerjaan desainer selesai di sini; bolanya berpindah ke pelanggan.
            // Tanpa baris ini job DESAIN tertinggal berstatus antri selamanya.
            $item->productionJobs()
                ->where('tahap', ProductionStage::DESAIN->value)
                ->update([
                    'status' => ProductionStatus::SELESAI->value,
                    'diselesaikan_oleh' => $olehUserId,
                    'selesai_pada' => now(),
                ]);

            $this->pindah($item, OrderItemStatus::MENUNGGU_PERSETUJUAN,
                "Mockup v{$versi} dikirim ke pelanggan.", $olehUserId);

            return $aset;
        });
    }

    /**
     * Pelanggan menyetujui. Nama yang diketik dan alamat IP disimpan bersama
     * versi mockup yang disetujui — inilah yang menyelesaikan sengketa
     * "saya menyetujui yang mana" berbulan-bulan kemudian.
     */
    public function setujui(OrderItem $item, string $namaDiketik, ?string $ip): void
    {
        abort_unless(
            $item->status_item === OrderItemStatus::MENUNGGU_PERSETUJUAN,
            422,
            'Item ini sedang tidak menunggu persetujuan.'
        );

        DB::transaction(function () use ($item, $namaDiketik, $ip) {
            $mockup = $item->designAssets()
                ->where('tipe', DesignAssetType::MOCKUP->value)
                ->orderByDesc('versi')
                ->first();

            $item->update([
                'approved_design_asset_id' => $mockup?->id,
                'approved_at' => now(),
                'approved_by_name' => $namaDiketik,
                'approved_ip' => $ip,
            ]);

            // Jam produksi baru berjalan sekarang. Estimasi yang dihitung dari
            // tanggal pesanan akan selalu meleset dan jadi sumber komplain.
            $lead = (int) $item->product->lead_time_hari;
            $target = now()->addWeekdays(max(1, $lead))->toDateString();

            $order = $item->order;

            if (! $order->target_siap_pada || $order->target_siap_pada->toDateString() < $target) {
                $order->update(['target_siap_pada' => $target]);
            }

            $this->pindah($item, OrderItemStatus::ANTRIAN_PRODUKSI,
                "Desain disetujui oleh \"{$namaDiketik}\".", null);
        });
    }

    public function mintaRevisi(OrderItem $item, string $catatan): void
    {
        abort_unless(
            $item->status_item === OrderItemStatus::MENUNGGU_PERSETUJUAN,
            422,
            'Item ini sedang tidak menunggu persetujuan.'
        );

        DB::transaction(function () use ($item, $catatan) {
            $item->increment('jumlah_revisi');

            // Pekerjaan desain dibuka kembali.
            $item->productionJobs()
                ->where('tahap', ProductionStage::DESAIN->value)
                ->update([
                    'status' => ProductionStatus::DIKERJAKAN->value,
                    'selesai_pada' => null,
                ]);

            $this->pindah($item, OrderItemStatus::PROSES_DESAIN,
                'Pelanggan meminta revisi: '.$catatan, null);
        });
    }

    /** Sisa jatah revisi gratis. Nol berarti revisi berikutnya berbiaya. */
    public function sisaRevisiGratis(OrderItem $item): int
    {
        return max(0, (int) Setting::ambil('revisi_gratis_maks', 3) - $item->jumlah_revisi);
    }

    private function pindah(OrderItem $item, OrderItemStatus $ke, ?string $catatan, ?int $oleh): void
    {
        $dari = $item->status_item;
        $item->update(['status_item' => $ke]);

        OrderStatusLog::create([
            'order_id' => $item->order_id,
            'order_item_id' => $item->id,
            'dari' => $dari->value,
            'ke' => $ke->value,
            'oleh' => $oleh,
            'catatan' => $catatan,
            'created_at' => now(),
        ]);

        $this->resolver->sinkronkan($item->order->fresh(['items']));
    }
}
