<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Pelanggan boleh punya akun, boleh juga tidak (KEP-020).
 *
 * Yang tanpa akun tetap membuka pesanannya lewat tautan bertoken di email,
 * persis seperti sebelumnya. Akun hanya menambah kemudahan bagi pelanggan
 * berulang, tidak pernah menjadi syarat.
 *
 * @property Carbon|null $token_dibuat_pada
 * @property Carbon|null $token_atur_sandi_dibuat_pada
 */
class Customer extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'nama', 'email', 'no_hp', 'catatan', 'user_id', 'password',
        'reseller', 'catatan_reseller',
    ];

    protected $hidden = ['password', 'remember_token', 'token_verifikasi', 'token_atur_sandi'];

    /** Masa berlaku tautan atur ulang kata sandi (KEP-157). */
    public const MENIT_ATUR_SANDI = 60;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'reseller' => 'boolean',
            'aktif' => 'boolean',
            'akun_dibuat_pada' => 'datetime',
            'login_terakhir_pada' => 'datetime',
            'email_terverifikasi_pada' => 'datetime',
            'token_dibuat_pada' => 'datetime',
            'token_atur_sandi_dibuat_pada' => 'datetime',
        ];
    }

    /**
     * Token atur ulang kata sandi (KEP-157). Yang disimpan hanya hash-nya;
     * tokennya sendiri hanya ada di tautan email.
     */
    public function siapkanAturUlangSandi(): string
    {
        $token = Str::random(64);

        $this->forceFill([
            'token_atur_sandi' => hash('sha256', $token),
            'token_atur_sandi_dibuat_pada' => now(),
        ])->save();

        return $token;
    }

    public function aturSandiMasihBerlaku(): bool
    {
        return filled($this->token_atur_sandi)
            && $this->token_atur_sandi_dibuat_pada?->gt(now()->subMinutes(self::MENIT_ATUR_SANDI));
    }

    /**
     * Membuka tautan di email membuktikan penguasaan email, jadi pemesan tamu
     * dan pendaftaran yang belum dikonfirmasi ikut menjadi akun aktif. Token
     * "ingat saya" diganti, sehingga perangkat lain yang masih tersimpan ikut
     * keluar.
     */
    public function aturUlangSandi(string $password): void
    {
        $this->forceFill([
            'password' => $password,
            'email_terverifikasi_pada' => $this->email_terverifikasi_pada ?? now(),
            'akun_dibuat_pada' => $this->akun_dibuat_pada ?? now(),
            'token_atur_sandi' => null,
            'token_atur_sandi_dibuat_pada' => null,
            'token_verifikasi' => null,
            'token_dibuat_pada' => null,
            'remember_token' => Str::random(60),
        ])->save();
    }

    /** @return HasMany<AlamatPelanggan, $this> */
    public function alamat(): HasMany
    {
        return $this->hasMany(AlamatPelanggan::class)->orderByDesc('utama')->orderBy('id');
    }

    /**
     * Menambah alamat ke buku alamat; alamat pertama otomatis jadi utama.
     * Null bila buku alamat sudah penuh (AlamatPelanggan::BATAS).
     *
     * @param  array<string, mixed>  $data
     */
    public function tambahAlamat(array $data): ?AlamatPelanggan
    {
        $jumlah = $this->alamat()->count();

        if ($jumlah >= AlamatPelanggan::BATAS) {
            return null;
        }

        return $this->alamat()->create([...$data, 'utama' => $jumlah === 0]);
    }

    /**
     * Kata sandi sudah dipasang, tapi belum tentu boleh dipakai. Pendaftaran
     * yang belum dikonfirmasi lewat email tetap berhenti di sini (KEP-021).
     */
    public function punyaKataSandi(): bool
    {
        return filled($this->password);
    }

    /** Satu-satunya keadaan yang boleh dipakai untuk masuk. */
    public function akunAktif(): bool
    {
        return filled($this->password) && $this->email_terverifikasi_pada !== null;
    }

    public function tokenMasihBerlaku(): bool
    {
        return filled($this->token_verifikasi)
            && $this->token_dibuat_pada?->gt(now()->subDay());
    }

    /**
     * Membuat pendaftaran tertunda. Kata sandi disimpan tapi belum berlaku
     * sampai tautan konfirmasi di email diklik.
     */
    public function siapkanKonfirmasi(string $password): string
    {
        $token = Str::random(64);

        $this->forceFill([
            'password' => Hash::make($password),
            'token_verifikasi' => hash('sha256', $token),
            'token_dibuat_pada' => now(),
        ])->save();

        return $token;
    }

    public function selesaikanKonfirmasi(): void
    {
        $this->forceFill([
            'email_terverifikasi_pada' => now(),
            'akun_dibuat_pada' => $this->akun_dibuat_pada ?? now(),
            'token_verifikasi' => null,
            'token_dibuat_pada' => null,
        ])->save();
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Cari pelanggan dari nama, email, atau nomor HP (KEP-099, KEP-111).
     *
     * Nomor HP disalin dari WhatsApp sebagai "+62 812-3456-7890", padahal
     * pelanggan mengetiknya dengan cara masing-masing saat memesan. Yang
     * dicocokkan hanya angkanya, tanpa 0 atau 62 di depan. "%" dan "_"
     * diketik sebagai karakter biasa, bukan pola.
     */
    public function scopeCari(Builder $q, string $kata): Builder
    {
        $kata = trim($kata);
        $pola = '%'.addcslashes($kata, '%_\\').'%';

        $angka = preg_replace('/\D/', '', $kata);
        $hp = preg_match('/^[\d\s()+.\-]+$/', $kata) && strlen($angka) >= 6
            ? preg_replace('/^(62|0)/', '', $angka)
            : null;

        return $q->where(fn (Builder $w) => $w
            ->where('nama', 'like', $pola)
            ->orWhere('email', 'like', $pola)
            ->orWhere('no_hp', 'like', $pola)
            ->when($hp, fn (Builder $c) => $c->orWhereRaw(
                "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(no_hp, ' ', ''), '-', ''), '+', ''), '.', ''), '(', ''), ')', '') like ?",
                ['%'.$hp.'%']
            )));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Halaman pesanan bersifat publik-bertoken dan tautannya memang dibagikan
     * ke grup tim (KEP-008), jadi data kontak tidak boleh tampil utuh di sana.
     */
    public function getNoHpSamarAttribute(): string
    {
        $n = $this->no_hp;

        return strlen($n) <= 6 ? $n : substr($n, 0, 4).str_repeat('*', strlen($n) - 6).substr($n, -2);
    }

    public function getEmailSamarAttribute(): string
    {
        if (blank($this->email)) {
            return 'tanpa email';
        }

        [$lokal, $domain] = array_pad(explode('@', $this->email, 2), 2, '');
        $samar = strlen($lokal) <= 2 ? $lokal : substr($lokal, 0, 2).str_repeat('*', strlen($lokal) - 2);

        return $domain ? $samar.'@'.$domain : $samar;
    }
}
