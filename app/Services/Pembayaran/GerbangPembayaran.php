<?php

namespace App\Services\Pembayaran;

use App\Enums\PaymentStatus;
use App\Models\Payment;

/**
 * Kontrak gerbang pembayaran.
 *
 * Midtrans dan Xendit sama-sama muat di sini, jadi mengganti penyedia berarti
 * menukar satu kelas — bukan menyentuh alur pesanan. Struktur tabel `payments`
 * memang sudah disiapkan untuk ini sejak hari pertama (KEP-003).
 */
interface GerbangPembayaran
{
    public function aktif(): bool;

    public function nama(): string;

    /**
     * Membuat transaksi di sisi penyedia dan mengembalikan URL bayar.
     * Nomor rujukan penyedia disimpan ke `payments.provider_ref`.
     */
    public function buatTransaksi(Payment $payment): string;

    /**
     * Menerjemahkan notifikasi penyedia menjadi status pembayaran kita.
     * Mengembalikan null bila notifikasi tidak sah atau tidak dikenali —
     * pemanggil wajib memperlakukan null sebagai penolakan.
     *
     * @param  array<string, mixed>  $payload
     * @return array{ref: string, status: PaymentStatus}|null
     */
    public function bacaNotifikasi(array $payload): ?array;
}
