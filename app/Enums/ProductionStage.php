<?php

namespace App\Enums;

/**
 * Tahap produksi yang punya antrian kerja sendiri di panel internal.
 * Setiap tahap dipegang peran berbeda (docs/04).
 */
enum ProductionStage: string
{
    case DESAIN = 'DESAIN';
    case PRINT = 'PRINT';
    case JAHIT = 'JAHIT';
    case QC = 'QC';
    case PACKING = 'PACKING';

    public function label(): string
    {
        return match ($this) {
            self::DESAIN => 'Desain',
            self::PRINT => 'Printing',
            self::JAHIT => 'Jahit',
            self::QC => 'Pemeriksaan Kualitas',
            self::PACKING => 'Packing',
        };
    }

    /** Permission yang dibutuhkan untuk mengerjakan antrian tahap ini. */
    public function permission(): string
    {
        return 'produksi.'.strtolower($this->value);
    }
}
