<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pembayaran offline (tunai di kasir) dan online (gateway) diperlakukan setara;
 * yang membedakan hanya metode, provider, dan dicatat_oleh (KEP-003).
 *
 * Kolom provider/provider_ref/payload_json dibiarkan kosong sampai fase gateway.
 * Justru itu yang membuat pemasangan Midtrans atau Xendit nanti jadi pekerjaan
 * kecil, bukan refactor besar.
 */
class Payment extends Model
{
    protected $fillable = [
        'order_id', 'jenis', 'metode', 'status', 'jumlah', 'bukti_path',
        'provider', 'provider_ref', 'payload_json',
        'dicatat_oleh', 'diverifikasi_oleh', 'dibayar_pada', 'kadaluarsa_pada',
    ];

    protected function casts(): array
    {
        return [
            'jenis' => PaymentType::class,
            'metode' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'jumlah' => 'integer',
            'payload_json' => 'array',
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
