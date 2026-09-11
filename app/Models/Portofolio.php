<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu pekerjaan yang pernah diselesaikan, dipajang di halaman depan (KEP-024).
 */
class Portofolio extends Model
{
    protected $fillable = [
        'judul', 'slug', 'instansi', 'kategori', 'tahun', 'jumlah_pcs',
        'ringkasan', 'gambar_path', 'thumb_path', 'aktif', 'urutan', 'contoh',
    ];

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
            'contoh' => 'boolean',
            'tahun' => 'integer',
            'jumlah_pcs' => 'integer',
        ];
    }

    public function scopeAktif(Builder $q): Builder
    {
        return $q->where('aktif', true);
    }

    public function scopeTampilan(Builder $q): Builder
    {
        return $q->aktif()->orderBy('urutan')->orderByDesc('tahun')->orderByDesc('id');
    }

    /**
     * Baris keterangan di bawah judul, tanpa koma menggantung ketika sebagian
     * kolomnya kosong. Pemilik toko tidak selalu tahu tahun atau jumlahnya.
     */
    public function keterangan(): string
    {
        return implode(' · ', array_filter([
            $this->instansi,
            $this->tahun,
            $this->jumlah_pcs ? $this->jumlah_pcs.' pcs' : null,
        ]));
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
