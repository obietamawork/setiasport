<?php

namespace App\Models;

use App\Enums\ProductionStage;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Pengguna INTERNAL saja. Pelanggan tidak punya akun, mereka mengakses
 * pesanannya lewat magic link bertoken (KEP-008).
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'no_hp', 'aktif'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'aktif' => 'boolean',
            'login_terakhir_pada' => 'datetime',
        ];
    }

    /**
     * Many-to-many dengan sengaja: di toko kecil satu orang lazim memegang
     * beberapa peran sekaligus (docs/04).
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function punyaPeran(string $slug): bool
    {
        return $this->roles->contains('slug', $slug);
    }

    public function punyaIzin(string $slug): bool
    {
        // Owner melewati seluruh pemeriksaan agar toko tidak pernah terkunci
        // dari sistemnya sendiri karena salah konfigurasi permission.
        if ($this->punyaPeran('owner')) {
            return true;
        }

        return $this->roles
            ->loadMissing('permissions')
            ->pluck('permissions')
            ->flatten()
            ->contains('slug', $slug);
    }

    /** Tahap produksi yang antriannya boleh dikerjakan pengguna ini. */
    public function tahapProduksi(): array
    {
        return array_values(array_filter(
            ProductionStage::cases(),
            fn (ProductionStage $t) => $this->punyaIzin($t->permission())
        ));
    }
}
