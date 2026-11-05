<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Nilai bisnis yang boleh diubah pemilik toko tanpa deploy ulang:
 * persentase DP, batas waktu bayar, jumlah revisi gratis, jam buka.
 */
class Setting extends Model
{
    /** Kunci cache dan nama binding untuk seluruh pengaturan sekaligus (KEP-108). */
    public const CACHE = 'setting.semua';

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value', 'tipe', 'grup', 'label', 'deskripsi'];

    /**
     * Semua pengaturan, dibaca dari cache satu kali per permintaan (KEP-108).
     *
     * Dulu tiap kunci punya entri cache sendiri, dan dengan cache database di
     * hosting itu berarti satu kueri per panggilan setting(): 59 kueri hanya
     * untuk membuka halaman depan. Kunci yang tidak ada malah dua kueri, karena
     * null tidak pernah tersimpan di cache. Tabelnya kecil, jadi semuanya dimuat
     * sekaligus; binding "scoped" disegarkan antarpermintaan, antarjob antrian,
     * dan antartes, sehingga perubahan dari proses lain tetap terbaca.
     *
     * @return array<string, array{0: ?string, 1: string}> kunci => [nilai, tipe]
     */
    public static function semua(): array
    {
        return app(self::CACHE);
    }

    /** @return array<string, array{0: ?string, 1: string}> */
    public static function muatSemua(): array
    {
        return Cache::rememberForever(self::CACHE, fn () => static::query()
            ->get(['key', 'value', 'tipe'])
            ->mapWithKeys(fn (self $s) => [$s->key => [$s->value, $s->tipe]])
            ->all());
    }

    public static function ambil(string $key, mixed $default = null): mixed
    {
        $semua = static::semua();

        if (! array_key_exists($key, $semua)) {
            return $default;
        }

        [$value, $tipe] = $semua[$key];

        return match ($tipe) {
            'int' => (int) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode((string) $value, true),
            default => $value,
        };
    }

    public static function simpan(string $key, mixed $value): void
    {
        $baris = static::findOrFail($key);
        $baris->value = $baris->tipe === 'json' ? json_encode($value) : (string) $value;
        $baris->save();
    }

    /**
     * Dipanggil setiap kali satu pengaturan berubah. Penulisan yang melewati
     * model (DB::table di migrasi) tidak memicunya; deploy membersihkan kunci
     * ini setelah migrasi untuk berjaga-jaga.
     */
    public static function lupakanCache(): void
    {
        Cache::forget(self::CACHE);
        app()->forgetInstance(self::CACHE);
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::lupakanCache());
        static::deleted(fn () => static::lupakanCache());
    }
}
