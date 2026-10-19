<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kamus bahan lintas produk (KEP-072). Lihat migrasinya untuk alasan
 * kenapa deskripsi disimpan terpusat, bukan per produk.
 */
class Material extends Model
{
    protected $fillable = ['nama', 'deskripsi'];

    public function optionValues(): HasMany
    {
        return $this->hasMany(OptionValue::class);
    }
}
