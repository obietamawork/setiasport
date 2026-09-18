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
        'ukuran', 'jumlah', 'harga_satuan', 'catatan',
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

    /** Label yang dibaca manusia, misalnya "SMP M". */
    public function labelUkuran(): string
    {
        return $this->kategori?->labelUkuran($this->ukuran) ?? $this->ukuran;
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
