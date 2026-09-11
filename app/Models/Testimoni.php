<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Kutipan pelanggan yang dimasukkan admin lewat panel (KEP-024). Tidak ada
 * jalur publik untuk menulis ini; lihat migrasi untuk alasannya.
 */
class Testimoni extends Model
{
    protected $fillable = ['nama', 'peran', 'isi', 'bintang', 'aktif', 'urutan'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean', 'bintang' => 'integer'];
    }

    public function scopeAktif(Builder $q): Builder
    {
        return $q->where('aktif', true);
    }

    public function scopeTampilan(Builder $q): Builder
    {
        return $q->aktif()->orderBy('urutan')->orderByDesc('id');
    }
}
