<?php

namespace App\Models;

use App\Enums\FulfillmentType;
use App\Enums\OrderItemStatus;
use App\Enums\PriceBasis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Nilai fulfillment_type, nama_produk, dan harga_satuan di-SNAPSHOT saat
 * checkout. Order adalah dokumen historis: ia harus tetap terbaca benar
 * setelah produk, harga, atau opsi di data master diubah.
 */
class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_id', 'product_variant_id', 'nama_produk',
        'fulfillment_type', 'qty', 'harga_satuan', 'status_item',
        'approved_design_asset_id', 'approved_at', 'approved_by_name', 'approved_ip',
        'jumlah_revisi', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'fulfillment_type' => FulfillmentType::class,
            'status_item' => OrderItemStatus::class,
            'qty' => 'integer',
            'harga_satuan' => 'integer',
            'jumlah_revisi' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(OrderItemOption::class);
    }

    public function rosters(): HasMany
    {
        return $this->hasMany(OrderItemRoster::class)->orderBy('urutan');
    }

    public function designAssets(): HasMany
    {
        return $this->hasMany(DesignAsset::class)->orderBy('versi');
    }

    public function approvedDesign(): BelongsTo
    {
        return $this->belongsTo(DesignAsset::class, 'approved_design_asset_id');
    }

    public function productionJobs(): HasMany
    {
        return $this->hasMany(ProductionJob::class)->orderBy('urutan');
    }

    /**
     * subtotal_item = harga_satuan * qty + SUM(opsi PER_ITEM)   -- KEP-009
     *
     * harga_satuan sudah memuat tier dan seluruh opsi PER_PCS; yang ditambahkan
     * di sini hanyalah biaya sekali-per-baris seperti setup desain. Kalau biaya
     * itu ikut dikali qty, tagihan 18 jersey melar lebih dari satu juta rupiah.
     */
    public function getSubtotalAttribute(): int
    {
        $perItem = (int) $this->options
            ->where('basis', PriceBasis::PER_ITEM)
            ->sum('tambahan_harga');

        return $this->harga_satuan * $this->qty + $perItem;
    }

    public function kustom(): bool
    {
        return $this->fulfillment_type === FulfillmentType::PREORDER;
    }

    public function sudahDisetujui(): bool
    {
        return $this->approved_at !== null;
    }

    /** Roster terkunci begitu desain disetujui (docs/03). */
    public function rosterTerkunci(): bool
    {
        return $this->sudahDisetujui();
    }

    /** Ringkasan "S:3, M:8, L:5" untuk pelanggan sekaligus untuk gudang. */
    public function ringkasanUkuran(): string
    {
        return $this->rosters
            ->groupBy('ukuran')
            ->map(fn ($grup, $ukuran) => $ukuran.':'.$grup->count())
            ->implode(', ');
    }
}
