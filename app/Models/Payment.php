<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Seluruh pembayaran diverifikasi manual (KEP-023).
 *
 * Pelanggan membayar lewat QRIS statis atau transfer bank, mengirim buktinya
 * lewat WhatsApp, lalu admin mencatat dan memverifikasinya di panel. Kolom
 * dicatat_oleh dan diverifikasi_oleh wajib terisi: ini titik rawan uang kas,
 * dan setiap pencatatan masuk audit log.
 */
class Payment extends Model
{
    protected $fillable = [
        'order_id', 'jenis', 'metode', 'status', 'jumlah', 'bukti_path',
        'dicatat_oleh', 'diverifikasi_oleh', 'dibayar_pada', 'kadaluarsa_pada',
    ];

    protected function casts(): array
    {
        return [
            'jenis' => PaymentType::class,
            'metode' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'jumlah' => 'integer',
            'dibayar_pada' => 'datetime',
            'kadaluarsa_pada' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_oleh');
    }

    public function verifikator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }
}
