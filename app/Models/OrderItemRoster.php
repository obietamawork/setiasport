<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nama punggung, nomor, dan ukuran per pemain, inti pekerjaan toko jersey.
 * Kalau data ini dibiarkan mengalir lewat WhatsApp, admin mengetik ulang,
 * salah ketik, dan jersey dicetak dua kali (docs/03).
 */
class OrderItemRoster extends Model
{
    protected $fillable = ['order_item_id', 'urutan', 'nama_punggung', 'nomor', 'ukuran', 'catatan'];

    protected function casts(): array
    {
        return ['urutan' => 'integer'];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
