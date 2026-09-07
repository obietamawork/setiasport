<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case MENUNGGU = 'MENUNGGU';
    case LUNAS = 'LUNAS';
    case GAGAL = 'GAGAL';
    case KADALUARSA = 'KADALUARSA';

    public function label(): string
    {
        return match ($this) {
            self::MENUNGGU => 'Menunggu Pembayaran',
            self::LUNAS => 'Lunas',
            self::GAGAL => 'Gagal',
            self::KADALUARSA => 'Kadaluarsa',
        };
    }
}
