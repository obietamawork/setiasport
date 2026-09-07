<?php

namespace App\Enums;

/**
 * Basis pengenaan biaya opsi (KEP-009).
 *
 * Disebut PER_ITEM dan bukan PER_ORDER dengan sengaja: satu order boleh memuat
 * banyak item (KEP-001), dan biaya setup desain memang dikenakan sekali untuk
 * setiap baris pesanan, bukan sekali untuk seluruh order.
 */
enum PriceBasis: string
{
    case PER_PCS = 'PER_PCS';
    case PER_ITEM = 'PER_ITEM';

    public function label(): string
    {
        return match ($this) {
            self::PER_PCS => 'per pcs',
            self::PER_ITEM => 'per pesanan',
        };
    }
}
