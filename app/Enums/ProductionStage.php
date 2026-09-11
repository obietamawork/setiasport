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

    // Dipisah sejak KEP-028: potong kain dan press adalah dua meja berbeda
    // dengan orang berbeda, dan menggabungkannya membuat antrian jahit
    // menampung pekerjaan yang belum bisa dijahit.
    case POTONG = 'POTONG';
    case PRESS = 'PRESS';

    case JAHIT = 'JAHIT';
    case QC = 'QC';
    case PACKING = 'PACKING';

    public function label(): string
    {
        return match ($this) {
            self::DESAIN => 'Desain',
            self::PRINT => 'Printing Jersey',
            self::POTONG => 'Potong Kain',
            self::PRESS => 'Press / Sublime',
            self::JAHIT => 'Jahit',
            self::QC => 'Finishing / QC',
            self::PACKING => 'Packing',
        };
    }

    /**
     * Status item yang sedang dipegang divisi ini (KEP-014). Satu-satunya
     * sumber: layar antrian dan badge menu dulu menyimpan salinannya sendiri,
     * dan salinan di menu tertinggal saat potong dan press ditambahkan
     * (KEP-028), sehingga pekerjaan di dua meja itu tidak terhitung.
     *
     * @return list<OrderItemStatus>
     */
    public function statusDipegang(): array
    {
        return match ($this) {
            self::DESAIN => [OrderItemStatus::ANTRIAN_DESAIN, OrderItemStatus::PROSES_DESAIN],
            self::PRINT => [OrderItemStatus::ANTRIAN_PRODUKSI, OrderItemStatus::PRINTING],
            self::POTONG => [OrderItemStatus::POTONG],
            self::PRESS => [OrderItemStatus::PRESS],
            self::JAHIT => [OrderItemStatus::JAHIT],
            self::QC => [OrderItemStatus::QC],
            self::PACKING => [OrderItemStatus::PACKING],
        };
    }

    /** Permission yang dibutuhkan untuk mengerjakan antrian tahap ini. */
    public function permission(): string
    {
        return 'produksi.'.strtolower($this->value);
    }
}
