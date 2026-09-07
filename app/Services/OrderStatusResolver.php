<?php

namespace App\Services;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderStatusLog;

/**
 * Status order adalah AGREGAT dari status seluruh item (KEP-001), tidak pernah
 * disetel manual. Operator menyelesaikan pekerjaannya di antrian divisinya,
 * status item naik, dan status order menyusul dari sini — nol input ganda.
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
            return OrderStatus::SELESAI;
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
            'oleh' => auth()->id(),
            'catatan' => $catatan,
            'created_at' => now(),
        ]);

        return $baru;
    }
}
