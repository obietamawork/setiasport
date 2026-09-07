<?php

namespace App\Enums;

/**
 * ANTRI dibedakan dari DIKERJAKAN dengan sengaja. Tanpa status "antri" yang
 * jujur, operator terpaksa menandai pekerjaan sebagai sedang dikerjakan sejak
 * awal, dan tracking kehilangan maknanya (docs/02).
 */
enum ProductionStatus: string
{
    case ANTRI = 'ANTRI';
    case DIKERJAKAN = 'DIKERJAKAN';
    case SELESAI = 'SELESAI';

    public function label(): string
    {
        return match ($this) {
            self::ANTRI => 'Antri',
            self::DIKERJAKAN => 'Dikerjakan',
            self::SELESAI => 'Selesai',
        };
    }
}
