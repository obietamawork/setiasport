<?php

namespace App\Services;

/**
 * Rincian harga pesanan kustom yang isinya bermacam ukuran (KEP-039).
 *
 * Berbeda dari PriceBreakdown yang memakai satu harga satuan untuk seluruh
 * baris, di sini setiap baris ukuran punya harganya sendiri. Rinciannya tetap
 * dibawa apa adanya, karena pelanggan yang memesan 6 seragam SD dan 4 dewasa
 * berhak melihat kenapa totalnya bukan sekadar sepuluh kali satu harga.
 */
final readonly class RincianPerUkuran
{
    /**
     * @param  array<int, array{kategori: string, ukuran: string, jumlah: int, harga_satuan: int, subtotal: int}>  $baris
     * @param  array<int, array{label: string, nilai: string, jumlah: int}>  $komponenPerPcs
     * @param  array<int, array{label: string, jumlah: int}>  $komponenPerItem
     */
    public function __construct(
        public array $baris,
        public array $komponenPerPcs,
        public array $komponenPerItem,
        public int $qty,
        public int $subtotal,
    ) {}

    /** Harga rata-rata per potong, untuk ditulis di baris pesanan. */
    public function hargaRataRata(): int
    {
        return $this->qty === 0 ? 0 : intdiv($this->subtotal - $this->totalPerItem(), $this->qty);
    }

    public function totalPerItem(): int
    {
        return array_sum(array_column($this->komponenPerItem, 'jumlah'));
    }

    public function toArray(): array
    {
        return [
            'baris' => $this->baris,
            'komponen_per_pcs' => $this->komponenPerPcs,
            'komponen_per_item' => $this->komponenPerItem,
            'qty' => $this->qty,
            'subtotal' => $this->subtotal,
        ];
    }
}
