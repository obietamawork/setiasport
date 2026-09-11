<?php

namespace App\Enums;

/**
 * Cara barang sampai ke pelanggan (KEP-026).
 *
 * Bawaannya selalu AMBIL_TOKO. KIRIM adalah pengecualian yang disepakati lewat
 * WhatsApp untuk pelanggan yang jauh, dan hanya sah untuk pesanan yang isinya
 * murni stok toko: barang kustom dicoba dulu di toko sebelum diserahkan.
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
