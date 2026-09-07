<?php

namespace App\Models;

use App\Enums\PriceBasis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Snapshot opsi beserta basis-nya, agar rincian harga lama tetap bisa dijelaskan. */
class OrderItemOption extends Model
{
    protected $fillable = ['order_item_id', 'option_nama', 'option_value', 'tambahan_harga', 'basis'];

    protected function casts(): array
    {
        return [
            'tambahan_harga' => 'integer',
            'basis' => PriceBasis::class,
        ];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
