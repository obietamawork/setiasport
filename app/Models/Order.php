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
        'kode', 'customer_id', 'status', 'access_token', 'tipe_pembayaran', 'diskon', 'biaya_layanan',
        'ongkir', 'area_tujuan', 'layanan_kirim',
        'target_siap_pada', 'kadaluarsa_pada', 'siap_pada', 'diambil_pada',
        'kode_ambil', 'diambil_oleh', 'diserahkan_oleh', 'catatan',
        'metode_ambil', 'alamat_kirim', 'jasa_kirim', 'no_resi',
        'lacak_status', 'lacak_catatan', 'lacak_riwayat', 'lacak_pada',
        'dikirim_pada', 'diterima_pada',
    ];

    protected $hidden = ['access_token'];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'metode_ambil' => MetodeAmbil::class,
            'lacak_riwayat' => 'array',
            'lacak_pada' => 'datetime',
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

    public function pembatalans(): HasMany
    {
        return $this->hasMany(Pembatalan::class)->latest('id');
    }

    public function getSubtotalAttribute(): int
    {
        return (int) $this->items->sum(fn (OrderItem $i) => $i->subtotal);
    }

    public function getTotalAttribute(): int
    {
        return $this->subtotal
            + (int) $this->adjustments->sum('jumlah')
            + (int) $this->biaya_layanan
            + (int) $this->ongkir
            - $this->diskon;
    }

    public function getSudahDibayarAttribute(): int
    {
        return (int) $this->payments->where('status', PaymentStatus::LUNAS)->sum('jumlah');
    }

    public function getSisaTagihanAttribute(): int
    {
        return max(0, $this->total - $this->sudah_dibayar);
    }

    /**
     * Nominal DP terkecil yang sah untuk order ini (KEP-005).
     *
     * Dihitung dari harga SEBELUM potongan borongan (KEP-050): toko memberi
     * potongannya saat pelunasan, bukan saat uang muka.
     *
     * Ongkir juga tidak ikut dihitung (KEP-058). Uang muka adalah tanda jadi
     * atas barangnya; ongkos kirim baru dibayar bersama pelunasan, dan paket
     * memang hanya berangkat setelah lunas.
     */
    public function dpMinimum(): int
    {
        $persen = (int) Setting::ambil('dp_minimum_persen', 50);

        return (int) ceil(($this->total + $this->diskon - (int) $this->ongkir) * $persen / 100);
    }

    /** Sudah melewati batas hari pengambilan (KEP-043). */
    public function bolehDihanguskan(): bool
    {
        $hari = (int) Setting::ambil('hangus_hari', 30);

        return $this->status === OrderStatus::SIAP_DIAMBIL
            && $this->siap_pada !== null
            && $this->siap_pada->lt(now()->subDays($hari));
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
     * Pesanan ini memuat barang yang dikerjakan di workshop. Dipakai untuk
     * memisahkan tab di panel (KEP-027).
     */
    public function punyaKustom(): bool
    {
        return $this->items->contains(fn (OrderItem $i) => $i->kustom());
    }

    /**
     * Kurir menyatakan paketnya sudah diterima (KEP-061).
     *
     * Ini BUKAN penutup pesanan: yang menandai selesai tetap orang toko, karena
     * diterima satpam sekolah dan diterima pemesannya adalah dua hal berbeda.
     */
    public function paketSampai(): bool
    {
        return in_array(mb_strtolower((string) $this->lacak_status), ['delivered', 'received'], true);
    }

    public function dikirim(): bool
    {
        return $this->metode_ambil === MetodeAmbil::KIRIM;
    }

    /**
     * Paket hanya berangkat setelah lunas: tidak ada kasir di depan pintu
     * pelanggan yang bisa menagih sisanya.
     *
     * Larangan mengirim barang kustom (KEP-026) sudah dicabut klien (KEP-055),
     * dan sejak pelanggan boleh memilih kirim sendiri di checkout (KEP-058)
     * larangan itu hanya akan membuat pesanan tersangkut tanpa jalan keluar.
     */
    public function bolehDikirim(): bool
    {
        return $this->dikirim()
            && $this->status === OrderStatus::SIAP_DIAMBIL
            && $this->lunas();
    }

    public function alasanBelumBisaDikirim(): ?string
    {
        return match (true) {
            ! $this->dikirim() => 'Pesanan ini disetel untuk diambil di toko.',
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

    /**
     * Pesanan yang menunggu tindakan ORANG TOKO, bukan menunggu pelanggan.
     *
     * Dipakai sebagai penyaring di daftar pesanan, dan sengaja disempitkan pada
     * tiga hal yang kalau didiamkan benar-benar merugikan: bukti transfer yang
     * belum diperiksa (pekerjaan tertahan), pengajuan pembatalan yang belum
     * diputuskan (kain terus terpakai), dan barang yang sudah siap tetapi belum
     * diserahkan (rak toko terpakai, uang belum masuk).
     *
     * Pesanan yang menunggu pelanggan membayar TIDAK termasuk: tidak ada yang
     * bisa dikerjakan toko di sana, dan daftar yang memuat hal-hal yang tidak
     * bisa ditindaklanjuti akan cepat diabaikan.
     */
    public function scopePerluTindakan(Builder $q): Builder
    {
        return $q->where(fn (Builder $w) => $w
            ->whereHas('payments', fn ($p) => $p
                ->where('status', PaymentStatus::MENUNGGU->value)
                ->whereNotNull('bukti_path'))
            ->orWhereHas('pembatalans', fn ($p) => $p->where('status', 'DIAJUKAN'))
            ->orWhereIn('status', [
                OrderStatus::MENUNGGU_PELUNASAN->value,
                OrderStatus::SIAP_DIAMBIL->value,
            ]));
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
