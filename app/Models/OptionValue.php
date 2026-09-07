<?php

namespace App\Models;

use App\Enums\PriceBasis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OptionValue extends Model
{
    protected $fillable = ['product_option_id', 'label', 'tambahan_harga', 'basis', 'urutan', 'aktif'];

    protected function casts(): array
    {
        return [
            'tambahan_harga' => 'integer',
            'basis' => PriceBasis::class,
            'aktif' => 'boolean',
        ];
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(ProductOption::class, 'product_option_id');
    }
}
