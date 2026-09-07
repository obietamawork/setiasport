<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPriceTier extends Model
{
    protected $fillable = ['product_id', 'min_qty', 'max_qty', 'harga_satuan'];

    protected function casts(): array
    {
        return [
            'min_qty' => 'integer',
            'max_qty' => 'integer',
            'harga_satuan' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getLabelAttribute(): string
    {
        return $this->max_qty === null
            ? "{$this->min_qty}+ pcs"
            : "{$this->min_qty}-{$this->max_qty} pcs";
    }
}
