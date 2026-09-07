<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Perubahan pesanan setelah DP masuk (KEP-007): tambah pemain, tambah patch,
 * diskon nego. Dicatat sebagai baris tersendiri, bukan dengan mengedit item
 * lama, supaya pelanggan bisa melihat apa yang berubah di halaman tracking
 * SEBELUM datang ke toko. Tidak ada kejutan angka di kasir.
 */
class OrderAdjustment extends Model
{
    protected $fillable = ['order_id', 'order_item_id', 'deskripsi', 'jumlah', 'dibuat_oleh'];

    protected function casts(): array
    {
        return ['jumlah' => 'integer'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }
}
