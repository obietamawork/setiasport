<?php

namespace App\Models;

use App\Enums\StatusPembatalan;
use App\Enums\TahapPembatalan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pengajuan pembatalan pesanan berikut keputusan toko atasnya (KEP-041). */
class Pembatalan extends Model
{
    protected $table = 'pembatalans';

    protected $fillable = [
        'order_id', 'status', 'tahap', 'alasan', 'sudah_dibayar', 'refund_usulan',
        'refund_jumlah', 'refund_metode', 'catatan_admin', 'diputuskan_oleh', 'diputuskan_pada',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusPembatalan::class,
            'tahap' => TahapPembatalan::class,
            'sudah_dibayar' => 'integer',
            'refund_usulan' => 'integer',
            'refund_jumlah' => 'integer',
            'diputuskan_pada' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function diputuskanOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diputuskan_oleh');
    }

    public function menunggu(): bool
    {
        return $this->status === StatusPembatalan::DIAJUKAN;
    }
}
