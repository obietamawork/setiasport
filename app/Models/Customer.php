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

    protected $fillable = ['nama', 'email', 'no_hp', 'catatan', 'user_id', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'akun_dibuat_pada' => 'datetime',
            'login_terakhir_pada' => 'datetime',
        ];
    }

    public function punyaAkun(): bool
    {
        return filled($this->password);
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
