<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu varian dalam satu kiriman supplier (KEP-120). Stoknya ditambahkan
 * StokController::masuk dalam transaksi yang sama dengan baris ini.
 */
class BarangMasuk extends Model
{
    protected $table = 'barang_masuk';

    protected $fillable = [
        'product_variant_id', 'jumlah', 'supplier', 'harga_beli', 'tanggal', 'catatan', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'jumlah' => 'integer',
            'harga_beli' => 'integer',
            'tanggal' => 'date',
        ];
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function varian(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /** @return BelongsTo<User, $this> */
    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
