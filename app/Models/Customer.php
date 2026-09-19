<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Pelanggan boleh punya akun, boleh juga tidak (KEP-020).
 *
 * Yang tanpa akun tetap membuka pesanannya lewat tautan bertoken di email,
 * persis seperti sebelumnya. Akun hanya menambah kemudahan bagi pelanggan
 * berulang, tidak pernah menjadi syarat.
 */
class Customer extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'nama', 'email', 'no_hp', 'catatan', 'user_id', 'password',
        'reseller', 'catatan_reseller',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'reseller' => 'boolean',
            'akun_dibuat_pada' => 'datetime',
            'login_terakhir_pada' => 'datetime',
            'email_terverifikasi_pada' => 'datetime',
            'token_dibuat_pada' => 'datetime',
        ];
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
        $token = \Illuminate\Support\Str::random(64);

        $this->forceFill([
            'password' => \Illuminate\Support\Facades\Hash::make($password),
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

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
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
        [$lokal, $domain] = array_pad(explode('@', $this->email, 2), 2, '');
        $samar = strlen($lokal) <= 2 ? $lokal : substr($lokal, 0, 2).str_repeat('*', strlen($lokal) - 2);

        return $domain ? $samar.'@'.$domain : $samar;
    }
}
