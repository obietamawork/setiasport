<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Kutipan pelanggan yang dimasukkan admin lewat panel (KEP-024). Tidak ada
 * jalur publik untuk menulis ini; lihat migrasi untuk alasannya.
 */
class Testimoni extends Model
{
    protected $fillable = ['nama', 'peran', 'isi', 'bintang', 'aktif', 'urutan', 'contoh'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean', 'bintang' => 'integer', 'contoh' => 'boolean'];
    }

    public function scopeAktif(Builder $q): Builder
    {
        return $q->where('aktif', true);
    }

    public function scopeTampilan(Builder $q): Builder
    {
        return $q->aktif()->orderBy('urutan')->orderByDesc('id');
    }

    /** Dua huruf awal nama untuk lingkaran pengganti foto: "Budi Santoso" jadi "BS". */
    public function inisial(): string
    {
        return Str::of($this->nama)->squish()->explode(' ')->take(2)
            ->map(fn (string $kata) => Str::upper(Str::substr($kata, 0, 1)))
            ->implode('');
    }
}
