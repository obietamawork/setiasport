<?php

namespace App\Enums;

/**
 * Progres satu baris pesanan. Inilah sumber kebenaran yang dilihat pelanggan
 * di halaman tracking, dan ia bergerak sebagai efek samping dari pekerjaan
 * yang diselesaikan operator, bukan diketik terpisah (docs/04).
 *
 * Status di sini SENGAJA lebih rinci daripada yang dibaca pelanggan. Operator
 * butuh tahu satu jersey sedang di meja potong atau di mesin press; pelanggan
 * cuma perlu tahu "sedang produksi". Pengelompokannya diurus FasePesanan
 * (KEP-028), jadi kerincian internal tidak bocor jadi kecemasan pelanggan.
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
    case PRESS = 'PRESS';
    case POTONG = 'POTONG';
    case JAHIT = 'JAHIT';
    case QC = 'QC';

    /*
     * Barang sudah jadi, tinggal menunggu sisa tagihan (KEP-028). Pesanan
     * kustom dikerjakan dengan modal DP, jadi inilah titik ketika toko berhak
     * menahan barang. Item melewatinya sendiri tanpa dilewati operator: begitu
     * pelunasan terverifikasi, status naik dengan sendirinya.
     */
    case MENUNGGU_PELUNASAN = 'MENUNGGU_PELUNASAN';

    // Bersama
    case PACKING = 'PACKING';
    case SIAP = 'SIAP';
    case SELESAI = 'SELESAI';
    case DIBATALKAN = 'DIBATALKAN';

    public function label(): string
    {
        return match ($this) {
            self::MENUNGGU_PEMBAYARAN => 'Menunggu Pembayaran',
            self::DIPROSES => 'Disiapkan',
            self::ANTRIAN_DESAIN => 'Antrian Desain',
            self::PROSES_DESAIN => 'Proses Desain',
            self::MENUNGGU_PERSETUJUAN => 'Menunggu Persetujuan Anda',
            self::ANTRIAN_PRODUKSI => 'Antrian Produksi',
            self::PRINTING => 'Printing Jersey',
            self::PRESS => 'Press / Sublime',
            self::POTONG => 'Potong Kain',
            self::JAHIT => 'Jahit',
            self::QC => 'Finishing & Packing',
            self::MENUNGGU_PELUNASAN => 'Menunggu Pelunasan',
            self::PACKING => 'Packing',
            self::SIAP => 'Siap',
            self::SELESAI => 'Selesai',
            self::DIBATALKAN => 'Dibatalkan',
        };
    }

    /**
     * Label untuk layar orang dalam: panel, lembar kerja, dan halaman pekerja.
     *
     * label() ditulis untuk pelanggan, dan "Menunggu Persetujuan Anda" terbaca
     * salah orang begitu muncul di layar admin atau di HP tukang jahit: yang
     * ditunggu pelanggan, bukan mereka.
     */
    public function labelStaf(): string
    {
        return match ($this) {
            self::MENUNGGU_PERSETUJUAN => 'Menunggu Persetujuan Pelanggan',
            self::DIPROSES => 'Disiapkan',
            default => $this->label(),
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
                self::PRESS,
                self::POTONG,
                self::JAHIT,
                self::QC,
                self::MENUNGGU_PELUNASAN,
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
            self::PRESS => ProductionStage::PRESS,
            self::POTONG => ProductionStage::POTONG,
            self::JAHIT => ProductionStage::JAHIT,
            self::QC => ProductionStage::QC,
            self::PACKING => ProductionStage::PACKING,
            default => null,
        };
    }
}
