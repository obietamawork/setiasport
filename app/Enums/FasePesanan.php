<?php

namespace App\Enums;

/**
 * Tahap besar yang dibaca pelanggan (KEP-028).
 *
 * Timeline pelanggan dan antrian operator adalah dua kebutuhan berbeda yang
 * selama ini dipaksa memakai daftar yang sama. Operator perlu tahu satu jersey
 * ada di meja potong atau di mesin press; pelanggan yang melihat sebelas baris
 * hanya belajar bahwa pesanannya rumit. Fase mengelompokkan status internal jadi
 * beberapa tahap besar, dan hanya fase Produksi yang membuka subprogresnya.
 */
enum FasePesanan: string
{
    case PESANAN = 'PESANAN';
    case PEMBAYARAN = 'PEMBAYARAN';
    case DESAIN = 'DESAIN';
    case PRODUKSI = 'PRODUKSI';
    case PELUNASAN = 'PELUNASAN';
    case DISIAPKAN = 'DISIAPKAN';
    case PACKING = 'PACKING';
    case SIAP = 'SIAP';
    case SELESAI = 'SELESAI';

    /**
     * Urutan fase untuk satu jalur pemenuhan.
     *
     * @return array<int, self>
     */
    public static function alur(FulfillmentType $tipe): array
    {
        return $tipe === FulfillmentType::READY_STOCK
            ? [self::PESANAN, self::PEMBAYARAN, self::DISIAPKAN, self::PACKING, self::SIAP, self::SELESAI]
            : [self::PESANAN, self::PEMBAYARAN, self::DESAIN, self::PRODUKSI, self::PELUNASAN, self::SIAP, self::SELESAI];
    }

    /** Fase yang memuat status item ini. */
    public static function untuk(OrderItemStatus $status): self
    {
        return match ($status) {
            OrderItemStatus::MENUNGGU_PEMBAYARAN => self::PEMBAYARAN,

            OrderItemStatus::ANTRIAN_DESAIN,
            OrderItemStatus::PROSES_DESAIN,
            OrderItemStatus::MENUNGGU_PERSETUJUAN => self::DESAIN,

            OrderItemStatus::ANTRIAN_PRODUKSI,
            OrderItemStatus::PRINTING,
            OrderItemStatus::PRESS,
            OrderItemStatus::POTONG,
            OrderItemStatus::JAHIT,
            OrderItemStatus::QC => self::PRODUKSI,

            OrderItemStatus::MENUNGGU_PELUNASAN => self::PELUNASAN,
            OrderItemStatus::DIPROSES => self::DISIAPKAN,
            OrderItemStatus::PACKING => self::PACKING,
            OrderItemStatus::SIAP => self::SIAP,

            // Item yang dibatalkan tidak digambar sebagai timeline sama sekali;
            // pemetaan ke SELESAI di sini hanya supaya match tidak pernah gagal.
            OrderItemStatus::SELESAI,
            OrderItemStatus::DIBATALKAN => self::SELESAI,
        };
    }

    public function label(bool $dikirim = false): string
    {
        return match ($this) {
            self::PESANAN => 'Pesanan Masuk',
            self::PEMBAYARAN => 'Pembayaran',
            self::DESAIN => 'Desain & Data',
            self::PRODUKSI => 'Produksi',
            self::PELUNASAN => 'Pelunasan',
            self::DISIAPKAN => 'Disiapkan',
            self::PACKING => 'Packing',
            self::SIAP => $dikirim ? 'Dikirim' : 'Siap Diambil',
            self::SELESAI => 'Selesai',
        };
    }

    /**
     * Kalimat pendek yang menjelaskan apa yang sebenarnya sedang terjadi.
     * Tanpa ini, "Desain & Data" tidak memberi tahu pelanggan bahwa gilirannya
     * yang ditunggu.
     */
    public function catatan(bool $dikirim = false): string
    {
        return match ($this) {
            self::PESANAN => 'Pesanan Anda tercatat di sistem kami.',
            self::PEMBAYARAN => 'Pengerjaan dimulai setelah pembayaran kami verifikasi.',
            self::DESAIN => 'Kami siapkan mockup, Anda lengkapi data pemain, lalu Anda yang menyetujuinya.',
            self::PRODUKSI => 'Dikerjakan di workshop kami, tahap demi tahap.',
            self::PELUNASAN => 'Barang sudah jadi. Sisa tagihan dilunasi sebelum diserahkan.',
            self::DISIAPKAN => 'Barang diambil dari rak dan diperiksa.',
            self::PACKING => 'Dibungkus dan disiapkan untuk diserahkan.',
            self::SIAP => $dikirim
                ? 'Paket diserahkan ke jasa kirim, nomor resinya ada di atas.'
                : 'Silakan diambil di toko dengan kode ambil.',
            self::SELESAI => 'Pesanan selesai.',
        };
    }

    /**
     * Subprogres yang ditampilkan di dalam fase ini. Hanya Produksi yang punya:
     * di situlah penungguan paling lama terasa, dan justru di situ pelanggan
     * paling sering bertanya "sampai mana".
     *
     * @return array<int, OrderItemStatus>
     */
    public function subtahap(): array
    {
        return $this === self::PRODUKSI
            ? [
                OrderItemStatus::PRINTING,
                OrderItemStatus::PRESS,
                OrderItemStatus::POTONG,
                OrderItemStatus::JAHIT,
                OrderItemStatus::QC,
            ]
            : [];
    }
}
