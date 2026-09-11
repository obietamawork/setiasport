<?php

namespace App\Models;

use App\Enums\TopikCs;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * Satu percakapan CS (KEP-030). Log-nya adalah catatan resmi toko: klien
 * hanya melayani komplain lewat chat ini.
 */
class PercakapanCs extends Model
{
    protected $table = 'percakapan_cs';

    protected $fillable = [
        'token_hash', 'customer_id', 'order_id', 'order_terverifikasi', 'nama', 'kontak',
        'topik', 'status', 'belum_dibaca_staf', 'belum_dibaca_pelanggan',
        'pesan_terakhir_pada', 'menunggu_sejak', 'diperingatkan_pada', 'ip',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'topik' => TopikCs::class,
            'order_terverifikasi' => 'boolean',
            'pesan_terakhir_pada' => 'datetime',
            'menunggu_sejak' => 'datetime',
            'diperingatkan_pada' => 'datetime',
        ];
    }

    /**
     * Sengaja TANPA urutan bawaan. Urutan bawaan di relasi membuat latest()
     * diabaikan diam-diam (ORDER BY kedua kalah dari yang pertama), dan bug itu
     * sempat membalik urutan chat di layar. Setiap kueri menyatakan urutannya.
     */
    public function pesan(): HasMany
    {
        return $this->hasMany(PesanCs::class, 'percakapan_id');
    }

    /** Untuk cuplikan di daftar percakapan. */
    public function pesanTerakhir(): HasOne
    {
        return $this->hasOne(PesanCs::class, 'percakapan_id')->latestOfMany('id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** Token mentah untuk cookie; yang disimpan hanya hash-nya. */
    public static function tokenBaru(): string
    {
        return Str::random(48);
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function dariToken(?string $token): ?self
    {
        return $token ? static::where('token_hash', static::hashToken($token))->first() : null;
    }

    public function terbuka(): bool
    {
        return $this->status === 'TERBUKA';
    }

    /** Nama channel realtime-nya. Aksesnya diperiksa di endpoint otorisasi. */
    public function channel(): string
    {
        return 'private-cs.percakapan.'.$this->id;
    }

    /**
     * Satu baris di daftar ruang CS. Dipakai dua tempat yang harus selalu
     * sama: jawaban awal halaman dan kabar realtime, supaya baris yang
     * diperbarui lewat Pusher tidak berbeda bentuk dari yang dimuat server.
     *
     * @param  PesanCs|null  $terakhir  pesan yang baru dibuat, bila sudah di tangan
     */
    public function untukDaftar(?PesanCs $terakhir = null): array
    {
        $terakhir ??= $this->pesanTerakhir;

        return [
            'id' => $this->id,
            'nama' => $this->nama,
            'topik' => $this->topik->label(),
            'topik_kode' => $this->topik->value,
            'status' => $this->status,
            'belum_dibaca' => $this->belum_dibaca_staf,
            'menunggu_sejak' => $this->menunggu_sejak?->toIso8601String(),
            'pesan_terakhir_pada' => ($this->pesan_terakhir_pada ?? $this->created_at)?->toIso8601String(),
            'cuplikan' => match (true) {
                ! $terakhir => '',
                $terakhir->isi !== null => mb_strimwidth($terakhir->isi, 0, 90, '…'),
                (bool) $terakhir->lampiran_path => 'Mengirim foto',
                default => '',
            },
            'cuplikan_dari' => $terakhir?->pengirim,
        ];
    }

    /** Konteks lengkap untuk panel kanan ruang CS. */
    public function untukDetail(): array
    {
        $this->loadMissing(['order.items.options', 'order.payments', 'order.adjustments', 'customer.orders']);

        $kartu = fn (Order $o) => [
            'kode' => $o->kode,
            'url' => route('panel.pesanan.show', $o),
            'status' => $o->status->label(),
            'total' => $o->total,
            'sisa' => $o->sisa_tagihan,
        ];

        return [
            ...$this->untukDaftar(),
            'kontak' => $this->kontak,
            'punya_akun' => $this->customer_id !== null,
            'dibuat' => $this->created_at?->toIso8601String(),
            'order' => $this->order ? [...$kartu($this->order), 'terverifikasi' => $this->order_terverifikasi] : null,
            'pesanan_lain' => $this->customer
                ? $this->customer->orders
                    ->reject(fn (Order $o) => $o->id === $this->order_id)
                    ->sortByDesc('id')->take(5)
                    ->map(fn (Order $o) => ['kode' => $o->kode, 'url' => route('panel.pesanan.show', $o), 'status' => $o->status->label()])
                    ->values()->all()
                : [],
        ];
    }

    public function scopeUntukKotakMasuk(Builder $q): Builder
    {
        // Yang menunggu jawaban paling atas, lalu yang paling baru bergerak.
        return $q->orderByRaw('menunggu_sejak IS NULL')
            ->orderBy('menunggu_sejak')
            ->orderByDesc('pesan_terakhir_pada');
    }
}
