<?php

namespace App\Enums;

/**
 * Jalur pemenuhan pesanan (KEP-011).
 *
 * Nilai BOTH hanya sah pada Product. Pada OrderItem nilainya selalu konkret,
 * karena jalur sudah dipilih pelanggan saat memasukkan ke keranjang.
 */
enum FulfillmentType: string
{
    case READY_STOCK = 'READY_STOCK';
    case PREORDER = 'PREORDER';
    case BOTH = 'BOTH';

    public function label(): string
    {
        return match ($this) {
            self::READY_STOCK => 'Ready Stock',
            self::PREORDER => 'PreOrder / Kustom',
            self::BOTH => 'Ready Stock & Kustom',
        };
    }
}
