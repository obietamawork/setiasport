<?php

namespace App\Services\Pembayaran;

use App\Models\Payment;
use RuntimeException;

/**
 * Dipakai selama merchant gateway belum disetujui.
 *
 * Sengaja bukan null: kode pemanggil tidak perlu memeriksa keberadaan gerbang
 * di mana-mana, cukup menanyakan aktif() sekali di tempat yang menampilkannya.
 */
class GerbangNonaktif implements GerbangPembayaran
{
    public function aktif(): bool
    {
        return false;
    }

    public function nama(): string
    {
        return 'Nonaktif';
    }

    public function buatTransaksi(Payment $payment): string
    {
        throw new RuntimeException('Pembayaran online belum diaktifkan untuk toko ini.');
    }

    public function bacaNotifikasi(array $payload): ?array
    {
        return null;
    }
}
