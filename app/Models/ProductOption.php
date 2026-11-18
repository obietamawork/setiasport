<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductOption extends Model
{
    protected $fillable = ['product_id', 'nama', 'wajib', 'per_pemain', 'urutan'];

    protected function casts(): array
    {
        return ['wajib' => 'boolean', 'per_pemain' => 'boolean'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(OptionValue::class)->where('aktif', true)->orderBy('urutan');
    }
}
