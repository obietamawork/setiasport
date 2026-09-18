<?php

namespace App\Enums;

/**
 * Sampai mana pekerjaan berjalan saat pembatalan diajukan (KEP-041).
 *
 * Inilah yang menentukan berapa uang kembali. Ketentuannya datang dari klien:
 * sebelum dikerjakan uang kembali utuh, setelah desain dikerjakan dipotong
 * biaya desain, dan setelah masuk produksi kain sudah tercetak dengan nama
 * tim yang tidak bisa dijual ke siapa pun.
 */
enum TahapPembatalan: string
{
    case AWAL = 'AWAL';
    case DESAIN = 'DESAIN';
    case PRODUKSI = 'PRODUKSI';

    public function label(): string
    {
        return match ($this) {
            self::AWAL => 'Belum dikerjakan',
            self::DESAIN => 'Desain sedang dikerjakan',
            self::PRODUKSI => 'Sudah masuk produksi',
        };
    }

    /** Kalimat yang dibaca pelanggan sebelum ia menekan tombol. */
    public function ketentuan(): string
    {
        return match ($this) {
            self::AWAL => 'Pekerjaan belum dimulai, jadi pembayaran Anda dikembalikan seluruhnya.',
            self::DESAIN => 'Desain sudah dikerjakan, jadi pengembalian dipotong biaya desain.',
            self::PRODUKSI => 'Pesanan sudah disetujui dan masuk produksi. Kain sudah tercetak dengan '
                .'nama tim Anda dan tidak bisa dipakai pesanan lain, jadi uang muka tidak dapat dikembalikan.',
        };
    }

    public function adaPengembalian(): bool
    {
        return $this !== self::PRODUKSI;
    }
}
