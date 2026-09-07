<?php

namespace App\Enums;

enum DesignAssetType: string
{
    case UPLOAD_PELANGGAN = 'UPLOAD_PELANGGAN';
    case MOCKUP = 'MOCKUP';

    public function label(): string
    {
        return match ($this) {
            self::UPLOAD_PELANGGAN => 'Kiriman Pelanggan',
            self::MOCKUP => 'Mockup Desainer',
        };
    }
}
