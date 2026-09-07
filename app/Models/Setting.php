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
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value', 'tipe', 'grup', 'label', 'deskripsi'];

    public static function ambil(string $key, mixed $default = null): mixed
    {
        $baris = Cache::rememberForever('setting.'.$key, fn () => static::find($key));

        if (! $baris) {
            return $default;
        }

        return match ($baris->tipe) {
            'int' => (int) $baris->value,
            'bool' => filter_var($baris->value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($baris->value, true),
            default => $baris->value,
        };
    }

    public static function simpan(string $key, mixed $value): void
    {
        $baris = static::findOrFail($key);
        $baris->value = $baris->tipe === 'json' ? json_encode($value) : (string) $value;
        $baris->save();

        Cache::forget('setting.'.$key);
    }

    protected static function booted(): void
    {
        static::saved(fn (self $s) => Cache::forget('setting.'.$s->key));
        static::deleted(fn (self $s) => Cache::forget('setting.'.$s->key));
    }
}
