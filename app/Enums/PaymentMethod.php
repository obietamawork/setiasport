<?php

namespace App\Enums;

/**
 * Seluruh pembayaran diterima dan diverifikasi manual (KEP-023). Toko memakai
 * QRIS statis dan transfer bank, dan buktinya dikirim pelanggan lewat WhatsApp.
 */
enum PaymentMethod: string
{
    case TUNAI = 'TUNAI';
    case TRANSFER = 'TRANSFER';
    case QRIS = 'QRIS';

    public function label(): string
    {
        return match ($this) {
            self::TUNAI => 'Tunai',
            self::TRANSFER => 'Transfer Bank',
            self::QRIS => 'QRIS',
        };
    }

}
