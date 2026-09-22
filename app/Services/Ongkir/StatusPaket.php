<?php

namespace App\Services\Ongkir;

/** Hasil pelacakan satu nomor resi (KEP-057). */
final readonly class StatusPaket
{
    /**
     * @param  array<int, array{waktu: ?string, keterangan: string}>  $riwayat
     */
    public function __construct(
        public string $status,
        public ?string $keterangan,
        public array $riwayat,
        public bool $diterima,
    ) {}
}
