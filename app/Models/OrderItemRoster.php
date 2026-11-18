<?php

namespace App\Models;

use App\Enums\KategoriUkuran;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nama punggung, nomor, dan ukuran per pemain, inti pekerjaan toko jersey.
 * Kalau data ini dibiarkan mengalir lewat WhatsApp, admin mengetik ulang,
 * salah ketik, dan jersey dicetak dua kali (docs/03).
 */
class OrderItemRoster extends Model
{
    protected $fillable = [
        'order_item_id', 'urutan', 'kategori', 'nama_punggung', 'nomor',
        'ukuran', 'jumlah', 'harga_satuan', 'option_value_id', 'model', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'urutan' => 'integer',
            'kategori' => KategoriUkuran::class,
            'jumlah' => 'integer',
            'harga_satuan' => 'integer',
        ];
    }

    /**
     * Label yang dibaca manusia, misalnya "SMP M", ditambah model baju bila
     * dipilih per pemain (KEP-123): "SMP M · Pakai Kerah". Lembar kerja,
     * invoice, dan serah terima ikut menyebutnya tanpa disentuh satu per satu.
     */
    public function labelUkuran(): string
    {
        $ukuran = $this->kategori?->labelUkuran($this->ukuran) ?? $this->ukuran;

        return $this->model ? $ukuran.' · '.$this->model : $ukuran;
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
