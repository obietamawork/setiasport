<?php

namespace App\Enums;

/**
 * Cara barang sampai ke pelanggan (KEP-026).
 *
 * Bawaannya selalu AMBIL_TOKO, karena sebagian besar pembeli memang datang ke
 * toko. Sejak KEP-055 barang kustom pun boleh dikirim: pemesan dari luar kota
 * terlalu banyak untuk ditolak.
 */
enum MetodeAmbil: string
{
    case AMBIL_TOKO = 'AMBIL_TOKO';
    case KIRIM = 'KIRIM';

    public function label(): string
    {
        return match ($this) {
            self::AMBIL_TOKO => 'Diambil di toko',
            self::KIRIM => 'Dikirim ke alamat pelanggan',
        };
    }
}
