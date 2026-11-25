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

    /** @return HasMany<OptionValue, $this> */
    public function values(): HasMany
    {
        return $this->hasMany(OptionValue::class)->where('aktif', true)->orderBy('urutan');
    }

    /**
     * Opsi potongan badan Pria / Wanita (KEP-161). Wizard menampilkannya dengan
     * gambar pembanding, karena pelanggan sering lupa jersey wanita dipotong
     * berbeda. Dikenali dari labelnya: opsi diisi pemilik toko sendiri.
     */
    public function potonganBadan(): bool
    {
        $label = $this->values->map(fn (OptionValue $v) => mb_strtolower(trim($v->label)));

        return $label->contains('pria') && $label->contains('wanita');
    }
}
