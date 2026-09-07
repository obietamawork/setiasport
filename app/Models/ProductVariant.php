<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id', 'sku', 'ukuran', 'warna',
        'tambahan_harga', 'stok', 'stok_dipesan', 'aktif',
    ];

    protected function casts(): array
    {
        return [
            'tambahan_harga' => 'integer',
            'stok' => 'integer',
            'stok_dipesan' => 'integer',
            'aktif' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Stok yang benar-benar bisa dijual. Selisih dengan `stok` adalah barang
     * yang sudah dipesan tapi ordernya belum dibayar, tanpa pemisahan ini,
     * dua pelanggan bisa membeli barang terakhir yang sama.
     */
    public function getStokTersediaAttribute(): int
    {
        return max(0, $this->stok - $this->stok_dipesan);
    }

    public function getHargaAttribute(): int
    {
        return $this->product->harga_dasar + $this->tambahan_harga;
    }

    public function getLabelAttribute(): string
    {
        return trim(implode(' / ', array_filter([$this->ukuran, $this->warna])));
    }
}
