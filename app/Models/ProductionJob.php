<?php

namespace App\Models;

use App\Enums\ProductionStage;
use App\Enums\ProductionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu pekerjaan nyata di satu divisi. Inilah yang mengisi layar "antrian saya"
 * per peran, dan penyelesaiannya adalah SATU-SATUNYA cara status pelanggan naik.
 * Status tracking tidak pernah diketik terpisah — kalau begitu, dalam dua minggu
 * ia jadi basi dan sistemnya ditinggalkan (docs/04).
 */
class ProductionJob extends Model
{
    protected $fillable = [
        'order_item_id', 'tahap', 'urutan', 'status',
        'ditugaskan_ke', 'diselesaikan_oleh', 'dimulai_pada', 'selesai_pada',
        'catatan', 'foto_path',
    ];

    protected function casts(): array
    {
        return [
            'tahap' => ProductionStage::class,
            'status' => ProductionStatus::class,
            'urutan' => 'integer',
            'dimulai_pada' => 'datetime',
            'selesai_pada' => 'datetime',
        ];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function petugas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditugaskan_ke');
    }

    public function penyelesai(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diselesaikan_oleh');
    }

    /** Antrian satu divisi: dasar layar kerja operator. */
    public function scopeAntrian(Builder $q, ProductionStage $tahap): Builder
    {
        return $q->where('tahap', $tahap)
            ->whereIn('status', [ProductionStatus::ANTRI, ProductionStatus::DIKERJAKAN])
            ->orderBy('created_at');
    }
}
