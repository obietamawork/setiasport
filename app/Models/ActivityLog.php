<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Alat penyelesaian perselisihan internal: siapa mengubah apa, kapan (docs/04). */
class ActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'aksi', 'subjek_tipe', 'subjek_id', 'data_json', 'ip', 'created_at'];

    protected function casts(): array
    {
        return ['data_json' => 'array', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function catat(string $aksi, ?Model $subjek = null, array $data = []): self
    {
        return static::create([
            'user_id' => auth()->id(),
            'aksi' => $aksi,
            'subjek_tipe' => $subjek ? class_basename($subjek) : null,
            'subjek_id' => $subjek?->getKey(),
            'data_json' => $data ?: null,
            'ip' => request()->ip(),
            'created_at' => now(),
        ]);
    }
}
