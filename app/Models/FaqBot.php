<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Jawaban siap pakai untuk chatbot CS (KEP-151), dikelola di panel.
 *
 * Jawaban boleh memuat penanda seperti {toko_alamat}; isinya diambil dari
 * Pengaturan saat dijawab, jadi jawaban tetap benar ketika alamat, jam buka,
 * atau persen DP diubah pemilik toko.
 */
class FaqBot extends Model
{
    protected $table = 'faq_bot';

    protected $fillable = ['pertanyaan', 'jawaban', 'kata_kunci', 'tampil_di_menu', 'aktif', 'urutan', 'dipakai'];

    protected function casts(): array
    {
        return [
            'tampil_di_menu' => 'boolean',
            'aktif' => 'boolean',
            'urutan' => 'integer',
            'dipakai' => 'integer',
        ];
    }

    /** @param Builder<FaqBot> $q */
    public function scopeAktif(Builder $q): Builder
    {
        return $q->where('aktif', true);
    }

    /** @return list<string> frasa pemicu yang sudah dirapikan */
    public function frasaKunci(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->kata_kunci))));
    }

    /** Jawaban dengan penanda yang sudah diganti data toko. */
    public function jawabanTampil(): string
    {
        return strtr($this->jawaban, self::penanda());
    }

    /** @return array<string, string> */
    public static function penanda(): array
    {
        $moqTerkecil = (int) Product::query()
            ->where('aktif', true)
            ->whereIn('fulfillment_type', ['PREORDER', 'BOTH'])
            ->min('moq');

        return [
            '{toko_nama}' => (string) setting('toko_nama', 'Setia Sport'),
            '{toko_alamat}' => (string) setting('toko_alamat', ''),
            '{toko_jam_buka}' => str_replace(["\r\n", "\n"], ', ', (string) setting('toko_jam_buka', '')),
            '{cs_jam_layanan}' => lcfirst((string) setting('cs_jam_layanan', '')),
            '{dp_persen}' => (string) (int) setting('dp_minimum_persen', 50),
            '{batas_bayar_jam}' => (string) (int) setting('batas_bayar_jam', 24),
            '{moq_terkecil}' => (string) ($moqTerkecil ?: 6),
        ];
    }
}
