<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/** Satu roll kertas printing yang memuat beberapa pesanan sekaligus (KEP-051). */
class RollCetak extends Model
{
    protected $fillable = ['kode', 'token', 'catatan', 'dibuat_oleh', 'pekerja_id', 'selesai_pada'];

    protected function casts(): array
    {
        return ['selesai_pada' => 'datetime'];
    }

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(OrderItem::class, 'roll_cetak_item')->withTimestamps();
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function pekerja(): BelongsTo
    {
        return $this->belongsTo(Pekerja::class);
    }

    public function selesai(): bool
    {
        return $this->selesai_pada !== null;
    }

    /** Nomor roll yang mudah disebut lisan di konveksi: ROLL-2609-003. */
    public static function kodeBaru(): string
    {
        $awalan = 'ROLL-'.now()->format('ym').'-';

        $terakhir = static::where('kode', 'like', $awalan.'%')
            ->orderByDesc('kode')
            ->value('kode');

        $urut = $terakhir ? ((int) Str::afterLast($terakhir, '-')) + 1 : 1;

        return $awalan.str_pad((string) $urut, 3, '0', STR_PAD_LEFT);
    }
}
