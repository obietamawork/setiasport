<?php

namespace App\Models;

use App\Enums\KategoriUkuran;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu baris panduan ukuran produk (KEP-049). */
class ProductSizeChart extends Model
{
    protected $fillable = ['product_id', 'kategori', 'ukuran', 'ukuran_cm', 'urutan'];

    protected function casts(): array
    {
        return [
            'kategori' => KategoriUkuran::class,
            'ukuran_cm' => 'array',
            'urutan' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
