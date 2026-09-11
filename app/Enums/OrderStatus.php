<?php

namespace App\Enums;

/**
 * Status order, sengaja ringkas. Detail progres ada di OrderItemStatus.
 *
 * Status ini adalah AGREGAT dari status seluruh item (KEP-001) dan tidak pernah
 * disetel langsung oleh operator; lihat App\Services\OrderStatusResolver.
 */
enum OrderStatus: string
{
    case MENUNGGU_PEMBAYARAN = 'MENUNGGU_PEMBAYARAN';
    case DIPROSES = 'DIPROSES';

    // Barang kustom sudah jadi, tinggal sisa tagihan (KEP-028). Kode ambil
    // baru terbit setelah lunas, jadi pesanan belum boleh disebut "siap".
    case MENUNGGU_PELUNASAN = 'MENUNGGU_PELUNASAN';

    case SIAP_DIAMBIL = 'SIAP_DIAMBIL';

    // Hanya untuk pesanan stok toko yang dikirim (KEP-026). Paket sudah keluar
    // dari toko tapi belum sampai, dan selama itu pesanan tetap terhitung aktif.
    case DIKIRIM = 'DIKIRIM';

    case SELESAI = 'SELESAI';
    case DIBATALKAN = 'DIBATALKAN';
    case KADALUARSA = 'KADALUARSA';

    public function label(): string
    {
        return match ($this) {
            self::MENUNGGU_PEMBAYARAN => 'Menunggu Pembayaran',
            self::DIPROSES => 'Sedang Diproses',
            self::MENUNGGU_PELUNASAN => 'Menunggu Pelunasan',
            self::SIAP_DIAMBIL => 'Siap Diambil',
            self::DIKIRIM => 'Dalam Pengiriman',
            self::SELESAI => 'Selesai',
            self::DIBATALKAN => 'Dibatalkan',
            self::KADALUARSA => 'Kadaluarsa',
        };
    }

    public function final(): bool
    {
        return in_array($this, [self::SELESAI, self::DIBATALKAN, self::KADALUARSA], true);
    }
}
