<?php

namespace App\Models;

use App\Enums\EmailStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Status dari webhook Resend. Karena tidak ada WhatsApp API (KEP-004), inilah
 * yang membuat sistem tahu kapan harus menyerah dan menyerahkan urusan ke
 * manusia, admin menindaklanjuti manual lewat WhatsApp.
 */
class EmailLog extends Model
{
    protected $fillable = [
        'order_id', 'ke', 'template', 'subjek', 'resend_id', 'status', 'error', 'dikirim_pada',
    ];

    protected function casts(): array
    {
        return ['status' => EmailStatus::class, 'dikirim_pada' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function scopePerluTindakLanjut(Builder $q): Builder
    {
        return $q->whereIn('status', [EmailStatus::BOUNCED->value, EmailStatus::GAGAL->value]);
    }
}
