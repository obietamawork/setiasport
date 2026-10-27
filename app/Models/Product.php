<?php

namespace App\Models;

use App\Enums\FulfillmentType;
use App\Enums\KategoriUkuran;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'category_id', 'sku', 'nama', 'slug', 'deskripsi', 'fulfillment_type',
        'harga_dasar', 'berat_gram', 'moq', 'diskon_min_qty', 'diskon_per_pcs',
        'lead_time_hari', 'roster_wajib', 'aktif',
    ];

    protected function casts(): array
    {
        return [
            'fulfillment_type' => FulfillmentType::class,
            'harga_dasar' => 'integer',
            'berat_gram' => 'integer',
            'moq' => 'integer',
            'diskon_min_qty' => 'integer',
            'diskon_per_pcs' => 'integer',
            'lead_time_hari' => 'integer',
            'roster_wajib' => 'boolean',
            'aktif' => 'boolean',
        ];
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        // chaperone(): varian yang dimuat lewat produknya langsung tahu
        // produknya, tanpa satu kueri lagi per varian saat harga dihitung.
        return $this->hasMany(ProductVariant::class)->chaperone('product');
    }

    /** @return HasMany<ProductImage, $this> */
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

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    public function sizeSurcharges(): HasMany
    {
        return $this->hasMany(ProductSizeSurcharge::class);
    }

    public function sizeCharts(): HasMany
    {
        return $this->hasMany(ProductSizeChart::class)->orderBy('urutan');
    }

    /**
     * Apakah harga produk ini dipisah menurut kategori umur (KEP-039).
     * Celana pendek tidak: satu harga dari ukuran terkecil sampai terbesar.
     */
    public function pakaiKategoriUkuran(): bool
    {
        return $this->prices->contains(fn (ProductPrice $h) => $h->kategori !== null);
    }

    /**
     * Ukuran yang boleh dipesan. Panduan ukuran yang dikirim klien jadi sumber
     * utamanya (KEP-049); daftar bawaan di enum hanya dipakai kalau panduannya
     * belum diisi.
     *
     * @return list<string>
     */
    public function ukuranTersedia(?KategoriUkuran $kategori): array
    {
        $panduan = $this->sizeCharts
            ->filter(fn (ProductSizeChart $b) => $b->kategori === $kategori)
            ->pluck('ukuran')
            ->all();

        if ($panduan !== []) {
            return array_values($panduan);
        }

        return $kategori?->ukuran() ?? KategoriUkuran::DEWASA->ukuran();
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
     * Harga dasar satu potong menurut kategori ukuran dan bahannya (KEP-039).
     *
     * Dicari dari yang paling khusus ke yang paling umum: sel yang menyebut
     * kategori DAN bahan menang atas sel yang hanya menyebut salah satunya.
     * Dengan begitu produk yang harganya sama untuk semua umur, seperti celana
     * pendek fullprint, cukup diisi satu baris tanpa kategori.
     */
    public function hargaPerPotong(?KategoriUkuran $kategori, ?int $optionValueId): ?int
    {
        $cocok = fn (?KategoriUkuran $k, ?int $v) => $this->prices
            ->first(fn (ProductPrice $h) => $h->kategori === $k && $h->option_value_id === $v);

        $harga = $cocok($kategori, $optionValueId)
            ?? $cocok(null, $optionValueId)
            ?? $cocok($kategori, null)
            ?? $cocok(null, null);

        return $harga?->harga_satuan;
    }

    /**
     * Potongan borongan (KEP-050): di atas jumlah tertentu, tiap potong dipotong
     * sekian rupiah. Tidak berlaku kelipatan, jadi 24 pcs dipotong dengan tarif
     * yang sama dengan 12 pcs.
     */
    public function potonganBorongan(int $qty): int
    {
        if ($this->diskon_min_qty < 1 || $this->diskon_per_pcs < 1 || $qty < $this->diskon_min_qty) {
            return 0;
        }

        return $qty * $this->diskon_per_pcs;
    }

    /** Tambahan harga untuk ukuran besar seperti 3XL (KEP-039). */
    public function tambahanUkuran(string $ukuran): int
    {
        return $this->sizeSurcharges
            ->first(fn (ProductSizeSurcharge $t) => $t->ukuran === $ukuran)
            ?->tambahan_harga ?? 0;
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

    /**
     * Waktu pengerjaan produk kustom yang tayang, untuk beranda dan katalog
     * (KEP-091). Rentang, bukan angka tercepat saja: kalau jersey 7 hari dan
     * jaket 14 hari, "7 hari kerja" menjanjikan sesuatu yang tidak berlaku.
     */
    public static function rentangWaktuKustom(): ?string
    {
        $waktu = static::query()->aktif()
            ->whereIn('fulfillment_type', [FulfillmentType::PREORDER->value, FulfillmentType::BOTH->value])
            ->toBase()
            ->selectRaw('min(lead_time_hari) as tercepat, max(lead_time_hari) as terlama')
            ->first();

        return rentang_hari_kerja(
            $waktu?->tercepat === null ? null : (int) $waktu->tercepat,
            $waktu?->terlama === null ? null : (int) $waktu->terlama,
        );
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
