<?php

namespace App\Support;

use App\Enums\FulfillmentType;
use App\Enums\OrderItemStatus;
use App\Models\OrderItem;
use App\Services\AlurPesanan;

/**
 * Progres satu barang seperti yang dibaca staf di detail pesanan (KEP-124).
 *
 * fase() untuk deret tahap di kartu kepala: tahap besar seperti gambar
 * karyawan, dengan subtahap produksi sebagai keterangan. pilihan() untuk
 * pop-up Perbarui progres: semua status rinci, tetapi hanya langkah yang
 * sah yang bisa dipilih, dengan alasan dari AlurPesanan supaya yang tampil
 * aktif selalu diterima server.
 */
final class ProgresStaf
{
    /**
     * @return list<array{label: string, ikon: string, keadaan: 'lewat'|'kini'|'nanti', keterangan: ?string}>
     */
    public static function fase(OrderItem $item): array
    {
        /** @var OrderItemStatus $kini */
        $kini = $item->status_item;
        /** @var FulfillmentType $jenis */
        $jenis = $item->fulfillment_type;

        if ($kini === OrderItemStatus::DIBATALKAN) {
            return [];
        }

        $kelompok = $jenis === FulfillmentType::READY_STOCK
            ? [
                ['Menunggu pembayaran', 'dompet', [OrderItemStatus::MENUNGGU_PEMBAYARAN]],
                ['Disiapkan', 'produk', [OrderItemStatus::DIPROSES, OrderItemStatus::PACKING]],
                ['Siap', 'toko', [OrderItemStatus::SIAP]],
                ['Selesai', 'centang', [OrderItemStatus::SELESAI]],
            ]
            : [
                ['Menunggu pembayaran', 'dompet', [OrderItemStatus::MENUNGGU_PEMBAYARAN]],
                ['Antrian desain', 'gambar', [OrderItemStatus::ANTRIAN_DESAIN]],
                ['Proses desain', 'pensil', [OrderItemStatus::PROSES_DESAIN]],
                ['Persetujuan desain', 'daftar', [OrderItemStatus::MENUNGGU_PERSETUJUAN]],
                ['Produksi', 'pengaturan', [
                    OrderItemStatus::ANTRIAN_PRODUKSI, OrderItemStatus::PRINTING, OrderItemStatus::PRESS,
                    OrderItemStatus::POTONG, OrderItemStatus::JAHIT, OrderItemStatus::QC,
                ]],
                ['Siap', 'toko', [OrderItemStatus::MENUNGGU_PELUNASAN, OrderItemStatus::SIAP]],
                ['Selesai', 'centang', [OrderItemStatus::SELESAI]],
            ];

        $posisi = null;

        foreach ($kelompok as $i => [, , $status]) {
            if (in_array($kini, $status, true)) {
                $posisi = $i;
            }
        }

        $hasil = [];

        foreach ($kelompok as $i => [$label, $ikon, $status]) {
            $keadaan = match (true) {
                $posisi === null => 'nanti',
                $i < $posisi => 'lewat',
                $i === $posisi => 'kini',
                default => 'nanti',
            };

            $hasil[] = [
                'label' => $label,
                'ikon' => $ikon,
                'keadaan' => $keadaan,
                // Tahap besar yang memuat beberapa status menyebut status
                // persisnya, misalnya Produksi: Jahit.
                'keterangan' => $keadaan === 'kini' && count($status) > 1 ? $kini->labelStaf() : null,
            ];
        }

        return $hasil;
    }

    /**
     * @return list<array{status: string, label: string, keadaan: 'lewat'|'kini'|'nanti', aksi: 'maju'|'mundur'|null, alasan: ?string}>
     */
    public static function pilihan(OrderItem $item, AlurPesanan $alur): array
    {
        /** @var OrderItemStatus $kini */
        $kini = $item->status_item;
        /** @var FulfillmentType $jenis */
        $jenis = $item->fulfillment_type;

        $daftar = OrderItemStatus::alur($jenis);
        $posisi = array_search($kini, $daftar, true);
        $berikutnya = $kini->berikutnya($jenis);
        $sebelumnya = $kini->sebelumnya($jenis);

        $alasanMaju = $berikutnya ? $alur->alasanTidakBisaMaju($item) : null;
        $alasanMundur = $sebelumnya ? $alur->alasanTidakBisaMundur($item) : null;

        $hasil = [];

        foreach ($daftar as $i => $status) {
            [$aksi, $alasan] = match (true) {
                $status === $berikutnya => [$alasanMaju === null ? 'maju' : null, $alasanMaju],
                $status === $sebelumnya => [$alasanMundur === null ? 'mundur' : null, $alasanMundur],
                default => [null, null],
            };

            $hasil[] = [
                'status' => $status->value,
                'label' => $status->labelStaf(),
                'keadaan' => match (true) {
                    $posisi === false => 'nanti',
                    $i < $posisi => 'lewat',
                    $i === $posisi => 'kini',
                    default => 'nanti',
                },
                'aksi' => $aksi,
                'alasan' => $alasan,
            ];
        }

        return $hasil;
    }
}
