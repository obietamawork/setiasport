<?php

namespace App\Enums;

/**
 * Gambar pola hitam putih yang menyertai produk kustom (KEP-106).
 *
 * Permintaan klien: pemesan awam perlu gambaran bentuk barangnya sebelum ada
 * desain, seperti gambar teknis mentah jersey di konveksi. Satu pola bisa
 * terdiri dari beberapa bagian (setelan = atasan + celana pendek).
 */
enum PolaPakaian: string
{
    case KAOS = 'kaos';
    case KAOS_KERAH = 'kaos_kerah';
    case SETELAN = 'setelan';
    case SERAGAM = 'seragam';
    case CELANA_PENDEK = 'celana_pendek';
    case CELANA_PANJANG = 'celana_panjang';
    case JAKET = 'jaket';

    public function label(): string
    {
        return match ($this) {
            self::KAOS => 'Kaos / jersey atasan',
            self::KAOS_KERAH => 'Kaos berkerah',
            self::SETELAN => 'Setelan (atasan + celana pendek)',
            self::SERAGAM => 'Seragam (kaos + celana panjang)',
            self::CELANA_PENDEK => 'Celana pendek',
            self::CELANA_PANJANG => 'Celana panjang / training',
            self::JAKET => 'Jaket',
        };
    }

    /**
     * Bagian yang digambar, berurutan dari atas.
     *
     * @return list<'atasan'|'celana_pendek'|'celana_panjang'|'jaket'>
     */
    public function bagian(): array
    {
        return match ($this) {
            self::KAOS, self::KAOS_KERAH => ['atasan'],
            self::SETELAN => ['atasan', 'celana_pendek'],
            self::SERAGAM => ['atasan', 'celana_panjang'],
            self::CELANA_PENDEK => ['celana_pendek'],
            self::CELANA_PANJANG => ['celana_panjang'],
            self::JAKET => ['jaket'],
        };
    }

    /** Leher bila pelanggan belum memilih model apa pun: 'bulat', 'v', atau 'kerah'. */
    public function leherBawaan(): string
    {
        return $this === self::KAOS_KERAH ? 'kerah' : 'bulat';
    }
}
