<?php

namespace App\Models;

use App\Enums\ProductionStage;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Pengguna INTERNAL saja. Pelanggan tidak punya akun, mereka mengakses
 * pesanannya lewat magic link bertoken (KEP-008).
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Kata sandi akun contoh dari UserSeeder. Tertulis di dokumentasi, jadi
     * akun yang masih memakainya sama dengan pintu panel tanpa kunci.
     */
    public const SANDI_BAWAAN = 'password';

    protected $fillable = ['name', 'email', 'password', 'no_hp', 'aktif', 'wajib_ganti_sandi'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'aktif' => 'boolean',
            'wajib_ganti_sandi' => 'boolean',
            'login_terakhir_pada' => 'datetime',
        ];
    }

    public function masihSandiBawaan(): bool
    {
        return Hash::check(self::SANDI_BAWAAN, $this->password);
    }

    /**
     * Mengeluarkan akun ini dari semua perangkat lain. Sesi disimpan di
     * database (SESSION_DRIVER=database), jadi cukup menghapus barisnya.
     *
     * Token "Ingat saya" ikut diganti supaya cookie pengingat di perangkat
     * lain tidak bisa membuat sesi baru. Laravel memang sudah menolak cookie
     * itu begitu hash sandi berubah, tapi jaminan ini tidak boleh bergantung
     * pada detail tersebut, dan menonaktifkan akun tidak mengubah sandi.
     */
    public function keluarkanSesiLain(?string $kecualiSesi = null): void
    {
        DB::table('sessions')
            ->where('user_id', $this->id)
            ->when($kecualiSesi, fn ($q) => $q->where('id', '!=', $kecualiSesi))
            ->delete();

        $this->setRememberToken(Str::random(60));
        $this->save();
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
