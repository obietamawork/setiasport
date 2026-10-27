<?php

namespace App\Models;

use App\Enums\ProductionStage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Pekerja konveksi yang menandai tahap lewat pindai QR (KEP-047).
 * Bukan pengguna panel; lihat migrasinya untuk alasannya.
 */
class Pekerja extends Model
{
    /** Salah PIN yang diizinkan per lembar kerja dalam 15 menit (KEP-088). */
    public const BATAS_SALAH_PIN = 5;

    protected $fillable = ['nama', 'pin_hash', 'tahap', 'aktif', 'catatan'];

    protected $hidden = ['pin_hash'];

    protected function casts(): array
    {
        return [
            'tahap' => 'array',
            'aktif' => 'boolean',
        ];
    }

    public function scopeAktif(Builder $q): Builder
    {
        return $q->where('aktif', true);
    }

    public function productionJobs(): HasMany
    {
        return $this->hasMany(ProductionJob::class);
    }

    /**
     * PIN diubah jadi hash yang bisa dicari, bukan dicocokkan satu per satu.
     * Kunci aplikasi ikut masuk hitungan, jadi isi tabel yang bocor tidak bisa
     * dipakai menebak PIN lewat tabel pelangi.
     */
    public static function hashPin(string $pin): string
    {
        return hash_hmac('sha256', $pin, (string) config('app.key'));
    }

    public static function cariLewatPin(string $pin): ?self
    {
        return static::aktif()->where('pin_hash', static::hashPin($pin))->first();
    }

    /**
     * Pemilik PIN untuk menandai satu lembar (lembar kerja atau roll), dengan
     * kunci per lembar. Batas per IP di rutenya saja tidak cukup: PIN hanya
     * empat angka, dan di jaringan seluler satu IP dipakai banyak orang
     * sekaligus sementara penebak tinggal berganti IP.
     *
     * @throws ValidationException
     */
    public static function periksaPin(string $pin, string $lembar): self
    {
        $kunci = 'pin-lembar:'.$lembar;

        if (RateLimiter::tooManyAttempts($kunci, self::BATAS_SALAH_PIN)) {
            throw ValidationException::withMessages([
                'pin' => 'Terlalu banyak PIN salah di lembar ini. Coba lagi dalam '
                    .ceil(RateLimiter::availableIn($kunci) / 60).' menit, atau minta admin menandainya dari panel.',
            ]);
        }

        $pekerja = static::cariLewatPin($pin);

        if (! $pekerja) {
            RateLimiter::hit($kunci, 900);

            throw ValidationException::withMessages([
                'pin' => 'PIN tidak dikenali. Pastikan Anda memakai PIN Anda sendiri.',
            ]);
        }

        RateLimiter::clear($kunci);

        return $pekerja;
    }

    /** Divisi yang boleh ditandai orang ini. */
    public function bolehMenandai(ProductionStage $tahap): bool
    {
        return in_array($tahap->value, $this->tahap ?? [], true);
    }

    /** @return list<ProductionStage> */
    public function tahapEnum(): array
    {
        return collect($this->tahap ?? [])
            ->map(fn (string $t) => ProductionStage::tryFrom($t))
            ->filter()
            ->values()
            ->all();
    }

    public function labelTahap(): string
    {
        return collect($this->tahapEnum())->map(fn (ProductionStage $t) => $t->label())->implode(', ');
    }
}
