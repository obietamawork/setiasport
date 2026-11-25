<?php

namespace App\Models;

use App\Enums\TopikCs;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Satu percakapan CS (KEP-030). Log-nya adalah catatan resmi toko: klien
 * hanya melayani komplain lewat chat ini.
 *
 * @property TopikCs $topik
 * @property string $ditangani BOT selama asisten otomatis menjawab (KEP-151)
 * @property ?string $alasan_serah
 * @property int $gagal_bot
 * @property ?string $catatan_internal
 * @property bool $order_terverifikasi
 * @property ?Carbon $ditutup_pada
 * @property-read ?Order $order
 * @property-read ?Customer $customer
 */
class PercakapanCs extends Model
{
    protected $table = 'percakapan_cs';

    protected $fillable = [
        'token_hash', 'customer_id', 'order_id', 'order_terverifikasi', 'nama', 'kontak',
        'topik', 'catatan_internal', 'status', 'ditangani', 'alasan_serah', 'gagal_bot',
        'belum_dibaca_staf', 'belum_dibaca_pelanggan',
        'pesan_terakhir_pada', 'menunggu_sejak', 'diperingatkan_pada', 'ditutup_pada', 'ip',
    ];

    /**
     * Percakapan yang ditutup lebih lama dari ini tidak dimuat lagi di widget
     * pelanggan; pertanyaan berikutnya dimulai sebagai percakapan baru (KEP-163).
     */
    public const HARI_MULAI_BARU = 7;

    // Catatan internal hanya untuk staf (KEP-150): tidak ikut serialisasi bawaan.
    protected $hidden = ['token_hash', 'catatan_internal'];

    protected function casts(): array
    {
        return [
            'topik' => TopikCs::class,
            'order_terverifikasi' => 'boolean',
            'pesan_terakhir_pada' => 'datetime',
            'menunggu_sejak' => 'datetime',
            'diperingatkan_pada' => 'datetime',
            'ditutup_pada' => 'datetime',
        ];
    }

    /**
     * Sengaja TANPA urutan bawaan. Urutan bawaan di relasi membuat latest()
     * diabaikan diam-diam (ORDER BY kedua kalah dari yang pertama), dan bug itu
     * sempat membalik urutan chat di layar. Setiap kueri menyatakan urutannya.
     */
    /** @return HasMany<PesanCs, $this> */
    public function pesan(): HasMany
    {
        return $this->hasMany(PesanCs::class, 'percakapan_id');
    }

    /**
     * Untuk cuplikan di daftar percakapan.
     *
     * @return HasOne<PesanCs, $this>
     */
    public function pesanTerakhir(): HasOne
    {
        return $this->hasOne(PesanCs::class, 'percakapan_id')->latestOfMany('id');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Order, $this> */
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

    /** Sudah ditutup lebih dari HARI_MULAI_BARU hari: widget memulai yang baru (KEP-163). */
    public function sudahLamaDitutup(): bool
    {
        return $this->status === 'SELESAI'
            && $this->ditutup_pada !== null
            && $this->ditutup_pada->lt(now()->subDays(self::HARI_MULAI_BARU));
    }

    /**
     * Percakapan lain dari pelanggan yang sama, untuk konteks CS (KEP-163):
     * dari akun yang sama, atau dari kontak yang sama persis.
     *
     * @return list<array{id: int, topik: string, status: string, tanggal: ?string}>
     */
    public function percakapanSebelumnya(): array
    {
        $kontak = mb_strtolower(trim((string) $this->kontak));

        if ($this->customer_id === null && $kontak === '') {
            return [];
        }

        return static::query()
            ->whereKeyNot($this->id)
            ->where(fn ($q) => $q
                ->when($this->customer_id !== null, fn ($q) => $q->orWhere('customer_id', $this->customer_id))
                ->when($kontak !== '', fn ($q) => $q->orWhereRaw('LOWER(TRIM(kontak)) = ?', [$kontak])))
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (self $p) => [
                'id' => $p->id,
                'topik' => $p->topik->label(),
                'status' => $p->status,
                'tanggal' => $p->created_at?->translatedFormat('j M Y'),
            ])
            ->all();
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
            // BOT selama asisten otomatis menjawab (KEP-151).
            'ditangani' => $this->ditangani,
            'alasan_serah' => $this->alasan_serah,
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
        $this->loadMissing(['order.items.options', 'order.items.rosters', 'order.payments', 'order.adjustments', 'order.customer.orders', 'customer.orders']);

        $kartu = fn (Order $o) => [
            'kode' => $o->kode,
            'url' => route('panel.pesanan.show', $o),
            'status' => $o->status->label(),
            'status_warna' => $o->status->warna(),
            'total' => $o->total,
            'sisa' => $o->sisa_tagihan,
            // Kartu Detail Pesanan seperti gambar Ruang CS klien (KEP-150).
            'jenis' => match (true) {
                ! $o->punyaKustom() => 'Ready Stock',
                $o->items->contains(fn ($i) => ! $i->kustom()) => 'Campuran',
                default => 'Custom',
            },
            'barang' => $o->items->first()?->nama_produk,
            'jumlah' => $o->items->sum('qty').' pcs',
            'tanggal' => $o->created_at?->translatedFormat('j M Y'),
            'target' => $o->target_siap_pada?->translatedFormat('j M Y'),
        ];

        return [
            ...$this->untukDaftar(),
            'kontak' => $this->kontak,
            'punya_akun' => $this->customer_id !== null,
            'dibuat' => $this->created_at?->toIso8601String(),
            'catatan_internal' => $this->catatan_internal,
            'pelanggan' => $this->kartuPelanggan(),
            'order' => $this->order ? [...$kartu($this->order), 'terverifikasi' => $this->order_terverifikasi] : null,
            'percakapan_sebelumnya' => $this->percakapanSebelumnya(),
            'pesanan_lain' => $this->customer
                ? $this->customer->orders
                    ->reject(fn (Order $o) => $o->id === $this->order_id)
                    ->sortByDesc('id')->take(5)
                    ->map(fn (Order $o) => ['kode' => $o->kode, 'url' => route('panel.pesanan.show', $o), 'status' => $o->status->label()])
                    ->values()->all()
                : [],
        ];
    }

    /**
     * Kartu Detail Pelanggan (KEP-150). Data akun hanya diambil dari akun
     * pelanggan sendiri, atau dari pesanan yang tertaut DAN terverifikasi:
     * kode pesanan yang sekadar diketik tidak membuka nomor HP pemesannya
     * kepada penanya (KEP-030).
     *
     * @return array{nama: string, no_hp: ?string, email: ?string, sejak: ?string, total_pesanan: int, wa: ?string, riwayat_url: ?string}
     */
    private function kartuPelanggan(): array
    {
        $pelanggan = $this->customer ?? ($this->order_terverifikasi ? $this->order?->customer : null);
        $kontak = (string) $this->kontak;

        $noHp = $pelanggan ? $pelanggan->no_hp : (preg_match('/^\+?[\d\s-]{8,}$/', $kontak) ? $kontak : null);
        $email = $pelanggan ? $pelanggan->email : (filter_var($kontak, FILTER_VALIDATE_EMAIL) ? $kontak : null);

        return [
            'nama' => $pelanggan ? $pelanggan->nama : $this->nama,
            'no_hp' => $noHp,
            'email' => $email,
            'sejak' => $pelanggan?->created_at?->translatedFormat('j M Y'),
            'total_pesanan' => $pelanggan ? $pelanggan->orders->count() : 0,
            'wa' => $noHp ? 'https://wa.me/'.nomor_wa($noHp) : null,
            'riwayat_url' => $pelanggan ? route('panel.pesanan.index', ['q' => $pelanggan->no_hp ?: $pelanggan->email]) : null,
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
