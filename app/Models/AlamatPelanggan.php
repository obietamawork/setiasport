<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Buku alamat pelanggan (KEP-157). Satu alamat bisa menjadi alamat utama,
 * yang langsung terpilih di checkout beserta areanya, sehingga tarif kirim
 * langsung dihitung tanpa mengetik ulang.
 *
 * @property bool $utama
 */
class AlamatPelanggan extends Model
{
    protected $table = 'alamat_pelanggan';

    public const BATAS = 10;

    protected $fillable = ['customer_id', 'label', 'alamat', 'area_kode', 'area_nama', 'kode_pos', 'tautan_lokasi', 'utama'];

    protected function casts(): array
    {
        return ['utama' => 'boolean'];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return array{id: int, label: string, alamat: string, area_kode: ?string, area_nama: ?string, kode_pos: ?string, tautan_lokasi: ?string, utama: bool} */
    public function untukCheckout(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'alamat' => $this->alamat,
            'area_kode' => $this->area_kode,
            'area_nama' => $this->area_nama,
            'kode_pos' => $this->kode_pos,
            'tautan_lokasi' => $this->tautan_lokasi,
            'utama' => $this->utama,
        ];
    }
}
