<?php

namespace App\Services;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderStatusLog;

/**
 * Status order adalah AGREGAT dari status seluruh item (KEP-001), tidak pernah
 * disetel manual. Operator menyelesaikan pekerjaannya di antrian divisinya,
 * status item naik, dan status order menyusul dari sini, nol input ganda.
 *
 * Aturan pentingnya: order baru "Siap Diambil" ketika SEMUA item siap. Pelanggan
 * datang sekali dan mengambil semuanya.
 */
class OrderStatusResolver
{
    public function hitung(Order $order): OrderStatus
    {
        $status = $order->items->pluck('status_item');

        if ($status->isEmpty()) {
            return OrderStatus::MENUNGGU_PEMBAYARAN;
        }

        $aktif = $status->reject(fn (OrderItemStatus $s) => $s === OrderItemStatus::DIBATALKAN);

        if ($aktif->isEmpty()) {
            return OrderStatus::DIBATALKAN;
        }

        if ($aktif->contains(OrderItemStatus::MENUNGGU_PEMBAYARAN)) {
            return OrderStatus::MENUNGGU_PEMBAYARAN;
        }

        if ($aktif->every(fn (OrderItemStatus $s) => $s === OrderItemStatus::SELESAI)) {
            /*
             * Barang sudah keluar dari toko, tapi untuk pesanan yang dikirim itu
             * belum berarti sampai (KEP-026). Selama belum ada konfirmasi
             * diterima, pesanan berhenti di DIKIRIM dan tetap terhitung aktif,
             * supaya paket yang nyangkut di kurir tidak hilang dari pandangan.
             */
            return $order->dikirim() && $order->diterima_pada === null
                ? OrderStatus::DIKIRIM
                : OrderStatus::SELESAI;
        }

        /*
         * Pekerjaan sudah beres, tapi uangnya belum (KEP-028). Satu item kustom
         * yang menunggu pelunasan menahan seluruh pesanan, termasuk barang stok
         * di dalamnya: pelanggan datang sekali dan mengambil semuanya, dan kode
         * ambil baru terbit setelah lunas.
         */
        $beres = $aktif->every(fn (OrderItemStatus $s) => in_array(
            $s, [OrderItemStatus::MENUNGGU_PELUNASAN, OrderItemStatus::SIAP, OrderItemStatus::SELESAI], true
        ));

        if ($beres && $aktif->contains(OrderItemStatus::MENUNGGU_PELUNASAN)) {
            return OrderStatus::MENUNGGU_PELUNASAN;
        }

        $siap = $aktif->every(fn (OrderItemStatus $s) => in_array(
            $s, [OrderItemStatus::SIAP, OrderItemStatus::SELESAI], true
        ));

        return $siap ? OrderStatus::SIAP_DIAMBIL : OrderStatus::DIPROSES;
    }

    /** Menyelaraskan kolom status order dengan hasil hitungan, lalu mencatat perubahannya. */
    public function sinkronkan(Order $order, ?string $catatan = null): OrderStatus
    {
        $order->loadMissing('items');

        $baru = $this->hitung($order);
        $lama = $order->status;

        if ($baru === $lama) {
            return $lama;
        }

        $order->status = $baru;

        if ($baru === OrderStatus::SIAP_DIAMBIL && $order->siap_pada === null) {
            $order->siap_pada = now();
            $order->kode_ambil ??= Order::kodeAmbilBaru();
        }

        $order->save();

        OrderStatusLog::create([
            'order_id' => $order->id,
            'dari' => $lama?->value,
            'ke' => $baru->value,
            'oleh' => auth('web')->id(),
            'catatan' => $catatan,
            'created_at' => now(),
        ]);

        return $baru;
    }
}
