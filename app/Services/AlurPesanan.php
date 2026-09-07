<?php

namespace App\Services;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductionStage;
use App\Enums\ProductionStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusLog;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * Perpindahan status pesanan. Semua jalan lewat sini supaya log, email, dan
 * status agregat tidak pernah tertinggal di salah satu tempat pemanggilan.
 */
class AlurPesanan
{
    public function __construct(
        private OrderStatusResolver $resolver,
        private PengirimEmail $email,
        private ReservasiStok $reservasi,
    ) {}

    /** Kasir memverifikasi pembayaran masuk. Pekerjaan baru dimulai di sini. */
    public function terimaPembayaran(Payment $payment, ?int $olehUserId = null): void
    {
        DB::transaction(function () use ($payment, $olehUserId) {
            $payment->update([
                'status' => PaymentStatus::LUNAS,
                'dibayar_pada' => $payment->dibayar_pada ?? now(),
                'diverifikasi_oleh' => $olehUserId,
            ]);

            $order = $payment->order->load('items');

            foreach ($order->items as $item) {
                if ($item->status_item !== OrderItemStatus::MENUNGGU_PEMBAYARAN) {
                    continue;
                }

                $this->setStatusItem(
                    $item,
                    $item->kustom() ? OrderItemStatus::ANTRIAN_DESAIN : OrderItemStatus::DIPROSES,
                    'Pembayaran diverifikasi.',
                    $olehUserId
                );
            }

            // Batas waktu bayar tidak lagi berlaku begitu uangnya masuk.
            $order->update(['kadaluarsa_pada' => null]);

            $this->sinkronkan($order->fresh(['items.options', 'payments', 'adjustments']));
        });

        $this->email->kirim($payment->order->fresh(), 'pembayaran-diterima');
    }

    /** Operator menyelesaikan satu tahap; status pelanggan naik sebagai akibatnya. */
    public function majukanItem(OrderItem $item, ?int $olehUserId = null): OrderItemStatus
    {
        $berikutnya = $item->status_item->berikutnya($item->fulfillment_type);

        abort_if($berikutnya === null, 422, 'Item ini sudah berada di tahap terakhir.');
        abort_if(
            $berikutnya === OrderItemStatus::SELESAI,
            422,
            'Item hanya berpindah ke Selesai lewat serah terima di kasir.'
        );

        /*
         * Gerbang persetujuan tidak boleh dilewati dari sisi toko. Kalau staf
         * bisa memajukannya sendiri, catatan siapa yang menyetujui dan versi
         * mockup mana akan kosong, dan justru itulah yang menyelesaikan sengketa
         * berbulan-bulan kemudian (docs/02).
         */
        abort_if(
            $item->status_item === OrderItemStatus::MENUNGGU_PERSETUJUAN,
            422,
            'Hanya pelanggan yang bisa menyetujui desain, dari halaman pesanannya. '
            .'Kirim ulang tautannya kalau mereka belum merespons.'
        );

        DB::transaction(function () use ($item, $berikutnya, $olehUserId) {
            // Tahap yang DITINGGALKAN-lah yang selesai; tahap yang dimasuki baru dimulai.
            $this->selesaikanJob($item, $item->status_item->tahapProduksi(), $olehUserId);
            $this->setStatusItem($item, $berikutnya, null, $olehUserId);
            $this->mulaiJob($item, $berikutnya->tahapProduksi(), $olehUserId);

            $this->sinkronkan($item->order->fresh(['items.options', 'payments', 'adjustments']));
        });

        return $berikutnya;
    }

    /**
     * Operator mengambil pekerjaan dari antrian divisinya.
     *
     * Status item menyatakan divisi mana yang sedang memegang barang; status
     * job menyatakan apa yang sedang divisi itu lakukan. Pemisahan ini membuat
     * "antri" dan "sedang dikerjakan" tetap jujur (docs/02).
     */
    public function mulaiKerja(OrderItem $item, int $olehUserId): void
    {
        $tahap = $item->status_item->tahapProduksi();

        abort_if($tahap === null, 422, 'Tahap ini tidak punya pekerjaan yang bisa dimulai.');

        $this->mulaiJob($item, $tahap, $olehUserId);
    }

    /** Serah terima di kasir: pelunasan dan pengambilan adalah satu tindakan (KEP-006). */
    public function serahkan(Order $order, string $diambilOleh, ?int $olehUserId = null): void
    {
        $order->load(['items.options', 'payments', 'adjustments']);

        abort_unless(
            $order->bolehDiserahkan(),
            422,
            'Pesanan belum bisa diserahkan: masih ada sisa tagihan atau belum siap.'
        );

        DB::transaction(function () use ($order, $diambilOleh, $olehUserId) {
            foreach ($order->items as $item) {
                if ($item->status_item === OrderItemStatus::SIAP) {
                    $this->setStatusItem($item, OrderItemStatus::SELESAI, 'Diserahkan ke pelanggan.', $olehUserId);
                }
            }

            $this->reservasi->keluarkan($order);

            $order->update([
                'diambil_pada' => now(),
                'diambil_oleh' => $diambilOleh,
                'diserahkan_oleh' => $olehUserId,
            ]);

            $this->sinkronkan($order->fresh(['items.options', 'payments', 'adjustments']));
        });
    }

    public function batalkan(Order $order, string $alasan, ?int $olehUserId = null): void
    {
        abort_if($order->status->final(), 422, 'Pesanan ini sudah selesai atau dibatalkan.');

        DB::transaction(function () use ($order, $alasan, $olehUserId) {
            $order->load('items');

            foreach ($order->items as $item) {
                if ($item->status_item !== OrderItemStatus::DIBATALKAN) {
                    $this->setStatusItem($item, OrderItemStatus::DIBATALKAN, $alasan, $olehUserId);
                }
            }

            $this->reservasi->lepaskan($order);

            $order->payments()
                ->where('status', PaymentStatus::MENUNGGU->value)
                ->update(['status' => PaymentStatus::KADALUARSA->value]);

            $this->sinkronkan($order->fresh(['items.options', 'payments', 'adjustments']), $alasan);
        });
    }

    private function setStatusItem(OrderItem $item, OrderItemStatus $ke, ?string $catatan, ?int $oleh): void
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
    }

    private function selesaikanJob(OrderItem $item, ?ProductionStage $tahap, ?int $oleh): void
    {
        if (! $tahap) {
            return;
        }

        $item->productionJobs()
            ->where('tahap', $tahap->value)
            ->where('status', '!=', ProductionStatus::SELESAI->value)
            ->update([
                'status' => ProductionStatus::SELESAI->value,
                'diselesaikan_oleh' => $oleh,
                'selesai_pada' => now(),
            ]);
    }

    private function mulaiJob(OrderItem $item, ?ProductionStage $tahap, ?int $oleh): void
    {
        if (! $tahap) {
            return;
        }

        $item->productionJobs()
            ->where('tahap', $tahap->value)
            ->where('status', ProductionStatus::ANTRI->value)
            ->update([
                'status' => ProductionStatus::DIKERJAKAN->value,
                'ditugaskan_ke' => $oleh,
                'dimulai_pada' => now(),
            ]);
    }

    private function sinkronkan(Order $order, ?string $catatan = null): void
    {
        $sebelum = $order->status;
        $sesudah = $this->resolver->sinkronkan($order, $catatan);

        if ($sesudah === OrderStatus::SIAP_DIAMBIL && $sebelum !== OrderStatus::SIAP_DIAMBIL) {
            $this->email->kirim($order->fresh(), 'pesanan-siap');
        }
    }
}
