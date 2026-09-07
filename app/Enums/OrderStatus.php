<?php

namespace App\Enums;

/**
 * Status order — sengaja ringkas. Detail progres ada di OrderItemStatus.
 *
 * Status ini adalah AGREGAT dari status seluruh item (KEP-001) dan tidak pernah
 * disetel langsung oleh operator; lihat App\Services\OrderStatusResolver.
 */
enum OrderStatus: string
{
    case MENUNGGU_PEMBAYARAN = 'MENUNGGU_PEMBAYARAN';
    case DIPROSES = 'DIPROSES';
    case SIAP_DIAMBIL = 'SIAP_DIAMBIL';
    case SELESAI = 'SELESAI';
    case DIBATALKAN = 'DIBATALKAN';
    case KADALUARSA = 'KADALUARSA';

    public function label(): string
    {
        return match ($this) {
            self::MENUNGGU_PEMBAYARAN => 'Menunggu Pembayaran',
            self::DIPROSES => 'Sedang Diproses',
            self::SIAP_DIAMBIL => 'Siap Diambil',
            self::SELESAI => 'Selesai',
            self::DIBATALKAN => 'Dibatalkan',
            self::KADALUARSA => 'Kadaluarsa',
        };
    }

    public function final(): bool
    {
        return in_array($this, [self::SELESAI, self::DIBATALKAN, self::KADALUARSA], true);
    }
}
