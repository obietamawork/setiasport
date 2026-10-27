<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Banner promo di puncak halaman depan (KEP-092).
 *
 * @property \Illuminate\Support\Carbon|null $mulai_pada
 * @property \Illuminate\Support\Carbon|null $selesai_pada
 */
class Banner extends Model
{
    protected $fillable = [
        'judul', 'subjudul', 'teks_tombol', 'tautan', 'gambar_path', 'gambar_hp_path',
        'teks_di_atas', 'mulai_pada', 'selesai_pada', 'aktif', 'urutan',
    ];

    protected function casts(): array
    {
        return [
            'teks_di_atas' => 'boolean',
            'aktif' => 'boolean',
            'mulai_pada' => 'date',
            'selesai_pada' => 'date',
        ];
    }

    /** Yang tayang hari ini: aktif dan berada di dalam periodenya (kedua ujung ikut). */
    public function scopeTayang(Builder $q): Builder
    {
        $hari = today()->toDateString();

        return $q->where('aktif', true)
            ->where(fn ($w) => $w->whereNull('mulai_pada')->orWhereDate('mulai_pada', '<=', $hari))
            ->where(fn ($w) => $w->whereNull('selesai_pada')->orWhereDate('selesai_pada', '>=', $hari))
            ->orderBy('urutan')
            ->orderByDesc('id');
    }

    /**
     * Keadaan untuk daftar di panel: kenapa banner ini tampil atau tidak.
     *
     * @return array{0: string, 1: string} [label, kelas chip]
     */
    public function keadaan(): array
    {
        return match (true) {
            ! $this->aktif => ['Disembunyikan', 'chip-netral'],
            $this->mulai_pada?->isFuture() => ['Mulai '.$this->mulai_pada->translatedFormat('j M Y'), 'chip-kuning'],
            $this->selesai_pada !== null && $this->selesai_pada->endOfDay()->isPast() => ['Selesai '.$this->selesai_pada->translatedFormat('j M Y'), 'chip-netral'],
            default => ['Tayang', 'chip-ok'],
        };
    }
}
