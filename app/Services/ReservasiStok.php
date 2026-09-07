<?php

namespace App\Services;

use App\Enums\FulfillmentType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Menjaga agar dua pelanggan tidak bisa membeli barang terakhir yang sama.
 *
 * Selama order hidup, `stok_dipesan` naik sehingga `stok_tersedia` turun,
 * tapi `stok` fisik belum berubah, barangnya memang masih ada di rak.
 * `stok` baru berkurang saat barang benar-benar diserahkan.
 */
class ReservasiStok
{
    /** Dipanggil di dalam transaksi checkout. */
    public function pesan(OrderItem $item): void
    {
        if ($item->fulfillment_type !== FulfillmentType::READY_STOCK || ! $item->product_variant_id) {
            return;
        }

        $varian = ProductVariant::whereKey($item->product_variant_id)->lockForUpdate()->first();

        if (! $varian) {
            throw new RuntimeException('Varian produk tidak ditemukan.');
        }

        if ($varian->stok - $varian->stok_dipesan < $item->qty) {
            throw new RuntimeException(
                "Stok {$item->nama_produk} ({$varian->label}) tinggal ".
                max(0, $varian->stok - $varian->stok_dipesan).' pcs.'
            );
        }

        $varian->increment('stok_dipesan', $item->qty);
    }

    /** Order batal atau kadaluarsa: barang kembali bisa dijual. */
    public function lepaskan(Order $order): void
    {
        DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                if ($item->fulfillment_type !== FulfillmentType::READY_STOCK || ! $item->product_variant_id) {
                    continue;
                }

                $varian = ProductVariant::whereKey($item->product_variant_id)->lockForUpdate()->first();

                $varian?->update([
                    'stok_dipesan' => max(0, $varian->stok_dipesan - $item->qty),
                ]);
            }
        });
    }

    /**
     * Barang keluar dari rak saat serah terima, bukan saat pembayaran.
     * Sampai pelanggan datang, barangnya memang masih ada secara fisik.
     */
    public function keluarkan(Order $order): void
    {
        DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                if ($item->fulfillment_type !== FulfillmentType::READY_STOCK || ! $item->product_variant_id) {
                    continue;
                }

                $varian = ProductVariant::whereKey($item->product_variant_id)->lockForUpdate()->first();

                $varian?->update([
                    'stok' => max(0, $varian->stok - $item->qty),
                    'stok_dipesan' => max(0, $varian->stok_dipesan - $item->qty),
                ]);
            }
        });
    }
}
