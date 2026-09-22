<?php

namespace App\Services\Ongkir;

/**
 * Satu pilihan pengiriman yang ditawarkan ke pelanggan (KEP-057).
 *
 * Sengaja bukan array bebas: penyedia ongkir berganti-ganti bentuk jawabannya,
 * dan seluruh sisa aplikasi tidak boleh ikut berubah karenanya.
 */
final readonly class TarifKirim
{
    public function __construct(
        public string $kurir,          // jne
        public string $layanan,        // REG
        public string $nama,           // JNE Reguler
        public int $harga,             // rupiah
        public ?string $estimasi,      // "2-3 hari"
    ) {}

    public function kode(): string
    {
        return $this->kurir.':'.$this->layanan;
    }

    public function label(): string
    {
        return trim($this->nama.($this->estimasi ? ' ('.$this->estimasi.')' : ''));
    }
}
