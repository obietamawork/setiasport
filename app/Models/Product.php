<?php

namespace App\Models;

use App\Enums\FulfillmentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'category_id', 'sku', 'nama', 'slug', 'deskripsi', 'fulfillment_type',
        'harga_dasar', 'moq', 'lead_time_hari', 'roster_wajib', 'aktif',
    ];

    protected function casts(): array
    {
        return [
            'fulfillment_type' => FulfillmentType::class,
            'harga_dasar' => 'integer',
            'moq' => 'integer',
            'lead_time_hari' => 'integer',
            'roster_wajib' => 'boolean',
            'aktif' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('urutan');
    }

    public function options(): HasMany
    {
        return $this->hasMany(ProductOption::class)->orderBy('urutan');
    }

    public function priceTiers(): HasMany
    {
        return $this->hasMany(ProductPriceTier::class)->orderBy('min_qty');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** Apakah produk ini bisa dibeli lewat jalur ready stock (KEP-011). */
    public function punyaJalurReadyStock(): bool
    {
        return in_array($this->fulfillment_type, [FulfillmentType::READY_STOCK, FulfillmentType::BOTH], true);
    }

    /** Apakah produk ini bisa dipesan lewat jalur kustom (KEP-011). */
    public function punyaJalurKustom(): bool
    {
        return in_array($this->fulfillment_type, [FulfillmentType::PREORDER, FulfillmentType::BOTH], true);
    }

    /**
     * Harga per pcs untuk jalur kustom pada jumlah tertentu (KEP-009).
     * Tier ditentukan qty SATU baris item, bukan gabungan keranjang.
     */
    public function hargaTier(int $qty): ?int
    {
        $tier = $this->priceTiers->first(
            fn (ProductPriceTier $t) => $qty >= $t->min_qty
                && ($t->max_qty === null || $qty <= $t->max_qty)
        );

        return $tier?->harga_satuan;
    }

    public function scopeAktif(Builder $q): Builder
    {
        return $q->where('aktif', true);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
