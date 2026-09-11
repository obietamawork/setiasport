<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PesanCs extends Model
{
    protected $table = 'pesan_cs';

    public const UPDATED_AT = null;

    protected $fillable = ['percakapan_id', 'pengirim', 'user_id', 'isi', 'lampiran_path'];

    public function percakapan(): BelongsTo
    {
        return $this->belongsTo(PercakapanCs::class, 'percakapan_id');
    }

    public function staf(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Bentuk yang dikirim ke browser, baik lewat Pusher maupun lewat polling.
     * Isinya teks mentah: browser memasangnya dengan textContent, tidak pernah
     * sebagai HTML, jadi pesan berisi tag tidak bisa menjalankan apa pun.
     *
     * @param  'pelanggan'|'staf'  $untuk  menentukan rute lampiran mana yang dipakai
     */
    public function untukKlien(string $untuk): array
    {
        return [
            'id' => $this->id,
            'pengirim' => $this->pengirim,
            // Pelanggan melihat "CS Setia Sport", bukan nama pribadi staf.
            'nama' => match ($this->pengirim) {
                'STAF' => $untuk === 'staf' ? ($this->staf?->name ?? 'Staf') : 'CS '.setting('toko_nama', 'Setia Sport'),
                'SISTEM' => null,
                default => $this->percakapan?->nama,
            },
            'isi' => $this->isi,
            'lampiran' => $this->lampiran_path
                ? ($untuk === 'staf'
                    ? route('panel.cs.lampiran', [$this->percakapan_id, $this->id])
                    : route('cs.lampiran', $this->id))
                : null,
            'waktu' => $this->created_at?->toIso8601String(),
        ];
    }
}
