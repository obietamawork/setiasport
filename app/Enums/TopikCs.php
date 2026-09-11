<?php

namespace App\Enums;

/**
 * Alasan pelanggan membuka chat (KEP-030). Dipilih di awal supaya staf tahu
 * harus membuka apa sebelum membaca pesannya, dan supaya komplain, yang
 * menurut klien hanya dilayani lewat chat ini, tidak tenggelam di antara
 * pertanyaan stok.
 */
enum TopikCs: string
{
    case STOK = 'STOK';
    case KUSTOM = 'KUSTOM';
    case KOMPLAIN = 'KOMPLAIN';
    case LAIN = 'LAIN';

    public function label(): string
    {
        return match ($this) {
            self::STOK => 'Tanya stok',
            self::KUSTOM => 'Pesanan custom',
            self::KOMPLAIN => 'Komplain pesanan',
            self::LAIN => 'Lainnya',
        };
    }
}
