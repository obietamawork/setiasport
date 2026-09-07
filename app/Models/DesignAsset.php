<?php

namespace App\Models;

use App\Enums\DesignAssetType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Berversi dengan sengaja: mockup v1 -> revisi -> v2 -> v3. Versi yang disetujui
 * pelanggan direferensikan dari order_items.approved_design_asset_id. Tanpa ini,
 * sengketa "saya menyetujui yang mana" tidak bisa diselesaikan (docs/02).
 */
class DesignAsset extends Model
{
    protected $fillable = [
        'order_item_id', 'tipe', 'versi', 'path', 'external_url', 'catatan', 'diunggah_oleh',
    ];

    protected function casts(): array
    {
        return [
            'tipe' => DesignAssetType::class,
            'versi' => 'integer',
        ];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function pengunggah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diunggah_oleh');
    }
}
