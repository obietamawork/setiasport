<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = ['nama', 'email', 'no_hp', 'catatan', 'user_id'];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Halaman tracking bersifat publik-bertoken dan tautannya memang dibagikan
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
