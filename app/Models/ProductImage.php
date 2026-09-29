<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    protected $fillable = [
        'product_id', 'path', 'external_url', 'thumb_path', 'lebar', 'tinggi', 'alt', 'urutan', 'warna',
    ];

    protected function casts(): array
    {
        return ['lebar' => 'integer', 'tinggi' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * URL luar menang atas berkas lokal. Dengan begitu toko bisa memindahkan
     * gambar ke CDN kapan saja tanpa mengubah apa pun di halaman.
     */
    public function getUrlAttribute(): ?string
    {
        if ($this->external_url) {
            return $this->external_url;
        }

        return $this->path ? Storage::url($this->path) : null;
    }

    /** Versi kecil untuk kartu katalog; jatuh kembali ke gambar utama bila tidak ada. */
    public function getThumbUrlAttribute(): ?string
    {
        if ($this->thumb_path) {
            return Storage::url($this->thumb_path);
        }

        return $this->url;
    }

    public function dariCdn(): bool
    {
        return filled($this->external_url);
    }
}
