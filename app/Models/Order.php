<?php

namespace App\Models;

use App\Enums\MetodeAmbil;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Perhatikan tidak adanya kolom subtotal / total / sudah_dibayar / sisa_tagihan.
 *
 * Semuanya dihitung ulang dari items + adjustments + payments (KEP-003, KEP-007).
 * Menyimpannya sebagai kolom berarti mengundang angka basi di layar kasir, * persis masalah yang ingin dihindari seluruh rancangan pembayaran ini.
 */
class Order extends Model
{
    protected $fillable = [
        'kode', 'customer_id', 'status', 'access_token', 'tipe_pembayaran', 'diskon',
        'target_siap_pada', 'kadaluarsa_pada', 'siap_pada', 'diambil_pada',
        'kode_ambil', 'diambil_oleh', 'diserahkan_oleh', 'catatan',
        'metode_ambil', 'alamat_kirim', 'jasa_kirim', 'no_resi',
        'dikirim_pada', 'diterima_pada',
    ];

    protected $hidden = ['access_token'];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'metode_ambil' => MetodeAmbil::class,
            'diskon' => 'integer',
            'target_siap_pada' => 'date',
            'kadaluarsa_pada' => 'datetime',
            'siap_pada' => 'datetime',
            'diambil_pada' => 'datetime',
            'dikirim_pada' => 'datetime',
            'diterima_pada' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(OrderAdjustment::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(OrderStatusLog::class)->latest('created_at');
    }

    public function emailLogs(): HasMany
    {
        return $this->hasMany(EmailLog::class)->latest();
    }

    public function getSubtotalAttribute(): int
    {
        return (int) $this->items->sum(fn (OrderItem $i) => $i->subtotal);
    }

    public function getTotalAttribute(): int
    {
        return $this->subtotal + (int) $this->adjustments->sum('jumlah') - $this->diskon;
    }

    public function getSudahDibayarAttribute(): int
    {
        return (int) $this->payments->where('status', PaymentStatus::LUNAS)->sum('jumlah');
    }

    public function getSisaTagihanAttribute(): int
    {
        return max(0, $this->total - $this->sudah_dibayar);
    }

    /** Nominal DP terkecil yang sah untuk order ini (KEP-005). */
    public function dpMinimum(): int
    {
        $persen = (int) Setting::ambil('dp_minimum_persen', 50);

        return (int) ceil($this->total * $persen / 100);
    }

    public function lunas(): bool
    {
        return $this->sisa_tagihan === 0;
    }

    /**
     * Penjaga KEP-006: order tidak boleh Selesai selama masih ada sisa tagihan.
     * Pelunasan terjadi di kasir saat serah terima, jadi inilah satu-satunya
     * tempat aturan itu bisa ditegakkan.
     *
     * Pesanan yang disetel untuk dikirim tidak lewat meja kasir sama sekali
     * (KEP-026). Dijaga di sini, bukan sekadar disembunyikan di tampilan, supaya
     * barang tidak bisa keluar tanpa nomor resi yang tercatat.
     */
    public function bolehDiserahkan(): bool
    {
        return $this->status === OrderStatus::SIAP_DIAMBIL
            && ! $this->dikirim()
            && $this->lunas();
    }

    /**
     * Pesanan ini memuat barang yang dikerjakan di workshop. Dipakai di dua
     * tempat yang alurnya memang berbeda: pemisahan tab di panel (KEP-027) dan
     * larangan mengirim barang kustom (KEP-026).
     */
    public function punyaKustom(): bool
    {
        return $this->items->contains(fn (OrderItem $i) => $i->kustom());
    }

    public function dikirim(): bool
    {
        return $this->metode_ambil === MetodeAmbil::KIRIM;
    }

    /**
     * Penjaga KEP-026. Barang kustom TIDAK boleh dikirim: ukurannya dicoba dulu
     * di toko, dan kalau meleset kami perbaiki saat itu juga. Selain itu paket
     * hanya berangkat setelah lunas, karena tidak ada kasir di depan pintu
     * pelanggan yang bisa menagih sisanya.
     */
    public function bolehDikirim(): bool
    {
        return $this->dikirim()
            && ! $this->punyaKustom()
            && $this->status === OrderStatus::SIAP_DIAMBIL
            && $this->lunas();
    }

    public function alasanBelumBisaDikirim(): ?string
    {
        return match (true) {
            ! $this->dikirim() => 'Pesanan ini disetel untuk diambil di toko.',
            $this->punyaKustom() => 'Pesanan ini memuat barang kustom, dan barang kustom diambil di toko.',
            $this->status !== OrderStatus::SIAP_DIAMBIL => 'Barangnya belum siap.',
            ! $this->lunas() => 'Masih ada sisa tagihan '.rupiah($this->sisa_tagihan)
                .'. Paket hanya berangkat setelah lunas.',
            default => null,
        };
    }

    public static function kodeBaru(): string
    {
        $prefix = 'SS-'.now()->format('ym').'-';
        $terakhir = static::where('kode', 'like', $prefix.'%')->max('kode');
        $urut = $terakhir ? ((int) substr($terakhir, -4)) + 1 : 1;

        return $prefix.str_pad((string) $urut, 4, '0', STR_PAD_LEFT);
    }

    public static function tokenBaru(): string
    {
        return Str::random(64);
    }

    public static function kodeAmbilBaru(): string
    {
        return strtoupper(Str::random(3)).'-'.random_int(1000, 9999);
    }

    /** Pesanan yang memuat setidaknya satu barang kustom (KEP-027). */
    public function scopeAdaKustom(Builder $q): Builder
    {
        return $q->whereHas('items', fn ($i) => $i->where('fulfillment_type', 'PREORDER'));
    }

    public function scopeHanyaStok(Builder $q): Builder
    {
        return $q->whereDoesntHave('items', fn ($i) => $i->where('fulfillment_type', 'PREORDER'));
    }

    public function scopeAktif(Builder $q): Builder
    {
        return $q->whereNotIn('status', [
            OrderStatus::SELESAI->value,
            OrderStatus::DIBATALKAN->value,
            OrderStatus::KADALUARSA->value,
        ]);
    }

    public function getRouteKeyName(): string
    {
        return 'kode';
    }
}
