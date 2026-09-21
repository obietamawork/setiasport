<?php

namespace App\Models;

use App\Enums\KategoriUkuran;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris panduan ukuran produk (KEP-049).
 *
 * Kolomnya disimpan sebagai DAFTAR berurutan `[{nama, nilai}, ...]`, bukan peta
 * `{nama: nilai}`: MySQL mengurutkan ulang kunci objek JSON menurut panjangnya,
 * sehingga "Lingkar dada" dan "Panjang" bisa bertukar tempat sendiri dan tabel
 * yang dibaca pelanggan jadi berbeda dari yang diketik pemilik toko.
 */
class ProductSizeChart extends Model
{
    protected $fillable = ['product_id', 'kategori', 'ukuran', 'ukuran_cm', 'urutan'];

    protected function casts(): array
    {
        return [
            'kategori' => KategoriUkuran::class,
            'ukuran_cm' => 'array',
            'urutan' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Nama kolom sesuai urutan yang diketik pemilik toko.
     *
     * @return list<string>
     */
    public function kolom(): array
    {
        return collect($this->ukuran_cm ?? [])->pluck('nama')->all();
    }

    /** Nilai satu kolom, dalam sentimeter. */
    public function nilai(string $nama): ?int
    {
        $baris = collect($this->ukuran_cm ?? [])->firstWhere('nama', $nama);

        return $baris ? (int) $baris['nilai'] : null;
    }
}
