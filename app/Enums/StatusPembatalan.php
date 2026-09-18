<?php

namespace App\Enums;

enum StatusPembatalan: string
{
    case DIAJUKAN = 'DIAJUKAN';
    case DISETUJUI = 'DISETUJUI';
    case DITOLAK = 'DITOLAK';

    public function label(): string
    {
        return match ($this) {
            self::DIAJUKAN => 'Menunggu Konfirmasi Toko',
            self::DISETUJUI => 'Disetujui',
            self::DITOLAK => 'Ditolak',
        };
    }
}
