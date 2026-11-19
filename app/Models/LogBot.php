<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu pertanyaan pelanggan yang ditangani chatbot (KEP-151): lapis mana yang
 * menjawabnya (menu, faq, sistem, ai, atau admin), dan untuk lapis AI, model,
 * token, dan biayanya. Yang lolos ke AI atau ke admin menjadi bahan kurasi FAQ.
 */
class LogBot extends Model
{
    protected $table = 'log_bot';

    protected $fillable = [
        'percakapan_id', 'pesan_id', 'pertanyaan', 'lapis', 'faq_id', 'alasan', 'jawaban',
        'model', 'token_masuk', 'token_keluar', 'biaya', 'durasi_ms', 'dikurasi_pada',
    ];

    protected function casts(): array
    {
        return [
            'biaya' => 'float',
            'token_masuk' => 'integer',
            'token_keluar' => 'integer',
            'durasi_ms' => 'integer',
            'dikurasi_pada' => 'datetime',
        ];
    }

    /** @return BelongsTo<PercakapanCs, $this> */
    public function percakapan(): BelongsTo
    {
        return $this->belongsTo(PercakapanCs::class, 'percakapan_id');
    }

    /** @return BelongsTo<FaqBot, $this> */
    public function faq(): BelongsTo
    {
        return $this->belongsTo(FaqBot::class, 'faq_id');
    }

    /**
     * Bahan kurasi: dijawab AI (mungkin perlu jadi FAQ) atau diteruskan ke
     * admin karena bot tidak tahu jawabannya. Serah karena komplain atau
     * permintaan admin bukan kekurangan FAQ, jadi tidak ikut.
     *
     * @param  Builder<LogBot>  $q
     */
    public function scopeBelumTerjawab(Builder $q): Builder
    {
        return $q->whereNull('dikurasi_pada')
            ->where(fn ($w) => $w->where('lapis', 'ai')
                ->orWhere(fn ($a) => $a->where('lapis', 'admin')->whereIn('alasan', [
                    'Tidak ada jawaban', 'Jawaban bot tidak membantu', 'AI ragu', 'AI tidak tersedia',
                ])));
    }
}
