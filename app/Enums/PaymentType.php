<?php

namespace App\Enums;

enum PaymentType: string
{
    case DP = 'DP';
    case PELUNASAN = 'PELUNASAN';
    case FULL = 'FULL';

    public function label(): string
    {
        return match ($this) {
            self::DP => 'Uang Muka (DP)',
            self::PELUNASAN => 'Pelunasan',
            // Bukan "Pembayaran Lunas": di panel ia bersebelahan dengan chip
            // status, dan "Pembayaran Lunas · Kedaluwarsa" membingungkan (KEP-099).
            self::FULL => 'Bayar penuh',
        };
    }
}
