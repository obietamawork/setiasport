<?php

namespace App\Services;

use App\Enums\PriceBasis;
use App\Models\OptionValue;
use App\Models\Product;
use App\Models\ProductVariant;
use InvalidArgumentException;

/**
 * Satu-satunya tempat harga dihitung (KEP-009).
 *
 * Konfigurator di sisi pelanggan dan proses checkout di server memakai kelas
 * yang sama, dan server selalu jadi otoritas — harga yang dikirim dari browser
 * tidak pernah dipercaya.
 *
 *   harga_satuan  = tier(product, qty) + SUM(opsi PER_PCS)
 *   subtotal_item = harga_satuan * qty + SUM(opsi PER_ITEM)
 */
class PriceCalculator
{
    /**
     * Jalur kustom / PreOrder.
     *
     * @param  array<int, int>  $optionValueIds
     */
    public function kustom(Product $product, int $qty, array $optionValueIds = []): PriceBreakdown
    {
        if (! $product->punyaJalurKustom()) {
            throw new InvalidArgumentException("Produk {$product->sku} tidak punya jalur kustom.");
        }

        if ($qty < $product->moq) {
            throw new InvalidArgumentException(
                "Minimum pemesanan {$product->nama} adalah {$product->moq} pcs."
            );
        }

        $hargaTier = $product->hargaTier($qty);

        if ($hargaTier === null) {
            throw new InvalidArgumentException(
                "Tidak ada tier harga untuk {$qty} pcs pada produk {$product->sku}."
            );
        }

        $values = $this->ambilOptionValues($product, $optionValueIds);

        $perPcs = [];
        $perItem = [];

        foreach ($values as $value) {
            $baris = [
                'label' => $value->option->nama,
                'nilai' => $value->label,
                'jumlah' => $value->tambahan_harga,
            ];

            if ($value->basis === PriceBasis::PER_PCS) {
                $perPcs[] = $baris;
            } else {
                $perItem[] = ['label' => $value->label, 'jumlah' => $value->tambahan_harga];
            }
        }

        $hargaSatuan = $hargaTier + array_sum(array_column($perPcs, 'jumlah'));
        $subtotal = $hargaSatuan * $qty + array_sum(array_column($perItem, 'jumlah'));

        return new PriceBreakdown(
            hargaTier: $hargaTier,
            komponenPerPcs: $perPcs,
            hargaSatuan: $hargaSatuan,
            komponenPerItem: $perItem,
            qty: $qty,
            subtotal: $subtotal,
        );
    }

    /**
     * Jalur ready stock. Tidak memakai tier sama sekali — kedua jalur pada
     * produk BOTH benar-benar terpisah dan tidak saling memengaruhi (KEP-011).
     */
    public function readyStock(ProductVariant $variant, int $qty): PriceBreakdown
    {
        $product = $variant->product;

        if (! $product->punyaJalurReadyStock()) {
            throw new InvalidArgumentException("Produk {$product->sku} tidak dijual ready stock.");
        }

        if ($qty > $variant->stok_tersedia) {
            throw new InvalidArgumentException(
                "Stok {$product->nama} ({$variant->label}) tinggal {$variant->stok_tersedia}."
            );
        }

        $hargaSatuan = $variant->harga;

        return new PriceBreakdown(
            hargaTier: $product->harga_dasar,
            komponenPerPcs: $variant->tambahan_harga === 0 ? [] : [[
                'label' => 'Varian',
                'nilai' => $variant->label,
                'jumlah' => $variant->tambahan_harga,
            ]],
            hargaSatuan: $hargaSatuan,
            komponenPerItem: [],
            qty: $qty,
            subtotal: $hargaSatuan * $qty,
        );
    }

    /**
     * Memuat option value sekaligus memastikan semuanya benar-benar milik
     * produk ini — mencegah harga dimanipulasi lewat id opsi dari produk lain.
     *
     * @param  array<int, int>  $ids
     * @return \Illuminate\Support\Collection<int, OptionValue>
     */
    private function ambilOptionValues(Product $product, array $ids)
    {
        if ($ids === []) {
            return collect();
        }

        $values = OptionValue::with('option')
            ->whereIn('id', $ids)
            ->where('aktif', true)
            ->get();

        foreach ($values as $value) {
            if ($value->option->product_id !== $product->id) {
                throw new InvalidArgumentException(
                    "Opsi \"{$value->label}\" bukan milik produk {$product->sku}."
                );
            }
        }

        if ($values->count() !== count(array_unique($ids))) {
            throw new InvalidArgumentException('Ada opsi yang tidak dikenali atau sudah nonaktif.');
        }

        return $values;
    }
}
