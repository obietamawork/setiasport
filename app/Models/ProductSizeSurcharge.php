<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tambahan harga untuk ukuran besar, misalnya 3XL dan 5XL (KEP-039). */
class ProductSizeSurcharge extends Model
{
    protected $fillable = ['product_id', 'ukuran', 'tambahan_harga'];

    protected function casts(): array
    {
        return ['tambahan_harga' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
