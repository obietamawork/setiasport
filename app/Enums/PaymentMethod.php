<?php

namespace App\Enums;

/**
 * Pembayaran offline dan online diperlakukan setara (KEP-003); yang membedakan
 * hanya nilai enum ini beserta kolom provider dan dicatat_oleh.
 */
enum PaymentMethod: string
{
    case TUNAI = 'TUNAI';
    case TRANSFER = 'TRANSFER';
    case QRIS_EDC = 'QRIS_EDC';
    case GATEWAY = 'GATEWAY';

    public function label(): string
    {
        return match ($this) {
            self::TUNAI => 'Tunai',
            self::TRANSFER => 'Transfer Bank',
            self::QRIS_EDC => 'QRIS / EDC di Toko',
            self::GATEWAY => 'Pembayaran Online',
        };
    }

    /** Dicatat manual oleh kasir, sehingga wajib mengisi dicatat_oleh. */
    public function offline(): bool
    {
        return $this !== self::GATEWAY;
    }
}
