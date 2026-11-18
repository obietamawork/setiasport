<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Kamus bahan lintas produk (KEP-072). Lihat migrasinya untuk alasan
 * kenapa deskripsi disimpan terpusat, bukan per produk.
 *
 * Fotonya (KEP-110) memperlihatkan emboss dan tekstur kain, hal yang tidak
 * bisa diceritakan lewat kalimat. Jalur berkasnya tidak bisa diisi massal:
 * hanya BahanController yang menyimpannya, setelah gambar diolah.
 */
class Material extends Model
{
    protected $fillable = ['nama', 'deskripsi'];

    public function optionValues(): HasMany
    {
        return $this->hasMany(OptionValue::class);
    }

    public function getGambarUrlAttribute(): ?string
    {
        return $this->gambar_path ? Storage::disk('public')->url($this->gambar_path) : null;
    }

    /** Versi kecil untuk daftar dan penjelasan bahan; jatuh ke foto utama bila tidak ada. */
    public function getThumbUrlAttribute(): ?string
    {
        return $this->thumb_path ? Storage::disk('public')->url($this->thumb_path) : $this->gambar_url;
    }
}
