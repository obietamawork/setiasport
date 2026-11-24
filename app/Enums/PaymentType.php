<?php

namespace App\Enums;

enum PaymentType: string
{
    case DP = 'DP';
    case PELUNASAN = 'PELUNASAN';
    case FULL = 'FULL';

    /**
     * Uang yang dikembalikan toko karena kelebihan bayar (KEP-158). Nominalnya
     * tetap positif; Order::sudah_dibayar yang menguranginya.
     */
    case PENGEMBALIAN = 'PENGEMBALIAN';

    public function label(): string
    {
        return match ($this) {
            self::DP => 'Uang Muka (DP)',
            self::PELUNASAN => 'Pelunasan',
            // Bukan "Pembayaran Lunas": di panel ia bersebelahan dengan chip
            // status, dan "Pembayaran Lunas · Kedaluwarsa" membingungkan (KEP-099).
            self::FULL => 'Bayar penuh',
            self::PENGEMBALIAN => 'Pengembalian',
        };
    }
}
