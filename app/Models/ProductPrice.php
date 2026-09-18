<?php

namespace App\Models;

use App\Enums\KategoriUkuran;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu sel di matriks harga produk kustom (KEP-039): harga per potong untuk
 * satu kategori ukuran dan satu bahan.
 */
class ProductPrice extends Model
{
    protected $fillable = ['product_id', 'kategori', 'option_value_id', 'harga_satuan'];

    protected function casts(): array
    {
        return [
            'kategori' => KategoriUkuran::class,
            'harga_satuan' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Bahan penentu harga; null berarti berlaku untuk semua bahan. */
    public function optionValue(): BelongsTo
    {
        return $this->belongsTo(OptionValue::class);
    }
}
