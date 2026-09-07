<?php

namespace App\Services;

/**
 * Rincian harga yang bisa ditampilkan apa adanya ke pelanggan.
 *
 * Konfigurator sengaja memperlihatkan rincian, bukan hanya total (KEP-010):
 * pelanggan yang paham dari mana angkanya tidak perlu bertanya ke admin, dan
 * itulah seluruh tujuan web ini.
 */
final readonly class PriceBreakdown
{
    /**
     * @param  array<int, array{label: string, nilai: string, jumlah: int}>  $komponenPerPcs
     * @param  array<int, array{label: string, jumlah: int}>  $komponenPerItem
     */
    public function __construct(
        public int $hargaTier,
        public array $komponenPerPcs,
        public int $hargaSatuan,
        public array $komponenPerItem,
        public int $qty,
        public int $subtotal,
    ) {}

    public function totalPerItem(): int
    {
        return array_sum(array_column($this->komponenPerItem, 'jumlah'));
    }

    public function toArray(): array
    {
        return [
            'harga_tier' => $this->hargaTier,
            'komponen_per_pcs' => $this->komponenPerPcs,
            'harga_satuan' => $this->hargaSatuan,
            'komponen_per_item' => $this->komponenPerItem,
            'qty' => $this->qty,
            'subtotal' => $this->subtotal,
        ];
    }
}
