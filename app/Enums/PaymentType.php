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
            self::FULL => 'Pembayaran Lunas',
        };
    }
}
