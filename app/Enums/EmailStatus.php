<?php

namespace App\Enums;

/**
 * Status dari webhook Resend. Inilah yang membuat admin tahu kapan email gagal
 * sampai dan urusannya harus diambil alih manusia lewat WhatsApp (docs/03).
 */
enum EmailStatus: string
{
    case TERKIRIM = 'TERKIRIM';
    case DELIVERED = 'DELIVERED';
    case OPENED = 'OPENED';
    case BOUNCED = 'BOUNCED';
    case GAGAL = 'GAGAL';

    public function label(): string
    {
        return match ($this) {
            self::TERKIRIM => 'Terkirim',
            self::DELIVERED => 'Sampai',
            self::OPENED => 'Dibuka',
            self::BOUNCED => 'Bounce',
            self::GAGAL => 'Gagal Kirim',
        };
    }

    /** Perlu ditindaklanjuti manual lewat WhatsApp. */
    public function perluTindakLanjut(): bool
    {
        return in_array($this, [self::BOUNCED, self::GAGAL], true);
    }
}
