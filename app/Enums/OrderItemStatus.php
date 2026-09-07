<?php

namespace App\Enums;

/**
 * Progres satu baris pesanan. Inilah sumber kebenaran yang dilihat pelanggan
 * di halaman tracking, dan ia bergerak sebagai efek samping dari pekerjaan
 * yang diselesaikan operator — bukan diketik terpisah (docs/04).
 */
enum OrderItemStatus: string
{
    case MENUNGGU_PEMBAYARAN = 'MENUNGGU_PEMBAYARAN';

    // Alur biasa
    case DIPROSES = 'DIPROSES';

    // Alur PreOrder
    case ANTRIAN_DESAIN = 'ANTRIAN_DESAIN';
    case PROSES_DESAIN = 'PROSES_DESAIN';
    case MENUNGGU_PERSETUJUAN = 'MENUNGGU_PERSETUJUAN';
    case ANTRIAN_PRODUKSI = 'ANTRIAN_PRODUKSI';
    case PRINTING = 'PRINTING';
    case JAHIT = 'JAHIT';
    case QC = 'QC';

    // Bersama
    case PACKING = 'PACKING';
    case SIAP = 'SIAP';
    case SELESAI = 'SELESAI';
    case DIBATALKAN = 'DIBATALKAN';

    public function label(): string
    {
        return match ($this) {
            self::MENUNGGU_PEMBAYARAN => 'Menunggu Pembayaran',
            self::DIPROSES => 'Disiapkan dari Gudang',
            self::ANTRIAN_DESAIN => 'Antrian Desain',
            self::PROSES_DESAIN => 'Proses Desain',
            self::MENUNGGU_PERSETUJUAN => 'Menunggu Persetujuan Anda',
            self::ANTRIAN_PRODUKSI => 'Antrian Produksi',
            self::PRINTING => 'Printing',
            self::JAHIT => 'Jahit',
            self::QC => 'Pemeriksaan Kualitas',
            self::PACKING => 'Packing',
            self::SIAP => 'Siap Diambil',
            self::SELESAI => 'Selesai',
            self::DIBATALKAN => 'Dibatalkan',
        };
    }

    /**
     * Urutan tahap untuk satu jalur pemenuhan. Dipakai menggambar timeline
     * pelanggan dan menentukan tahap berikutnya.
     *
     * @return array<int, self>
     */
    public static function alur(FulfillmentType $tipe): array
    {
        return $tipe === FulfillmentType::READY_STOCK
            ? [self::MENUNGGU_PEMBAYARAN, self::DIPROSES, self::PACKING, self::SIAP, self::SELESAI]
            : [
                self::MENUNGGU_PEMBAYARAN,
                self::ANTRIAN_DESAIN,
                self::PROSES_DESAIN,
                self::MENUNGGU_PERSETUJUAN,
                self::ANTRIAN_PRODUKSI,
                self::PRINTING,
                self::JAHIT,
                self::QC,
                self::PACKING,
                self::SIAP,
                self::SELESAI,
            ];
    }

    public function berikutnya(FulfillmentType $tipe): ?self
    {
        $alur = self::alur($tipe);
        $i = array_search($this, $alur, true);

        return $i === false ? null : ($alur[$i + 1] ?? null);
    }

    /** Tahap produksi yang menghasilkan status ini, bila ada. */
    public function tahapProduksi(): ?ProductionStage
    {
        return match ($this) {
            self::PROSES_DESAIN => ProductionStage::DESAIN,
            self::PRINTING => ProductionStage::PRINT,
            self::JAHIT => ProductionStage::JAHIT,
            self::QC => ProductionStage::QC,
            self::PACKING => ProductionStage::PACKING,
            default => null,
        };
    }
}
