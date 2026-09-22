<?php

namespace App\Services\Ongkir;

use App\Enums\MetodeAmbil;

/**
 * Cara satu pesanan diserahkan, sebagaimana dipilih pelanggan (KEP-058).
 *
 * Dibekukan ke pesanan begitu pesanan dibuat: tarif kurir boleh naik minggu
 * depan tanpa mengubah tagihan yang sudah disepakati hari ini (KEP-048).
 *
 * Tarifnya boleh kosong meski barangnya dikirim. Itulah keadaan toko selama
 * penyedia ongkir belum dinyalakan: alamatnya tercatat, ongkirnya diisi admin
 * setelah paket ditimbang di konter.
 */
final readonly class PilihanKirim
{
    public function __construct(
        public MetodeAmbil $metode,
        public ?string $alamat = null,
        public ?string $areaTujuan = null,
        public ?TarifKirim $tarif = null,
    ) {}

    public static function ambilDiToko(): self
    {
        return new self(MetodeAmbil::AMBIL_TOKO);
    }

    public function dikirim(): bool
    {
        return $this->metode === MetodeAmbil::KIRIM;
    }

    public function ongkir(): int
    {
        return $this->dikirim() ? ($this->tarif?->harga ?? 0) : 0;
    }

    /** @return array<string, mixed> */
    public function kolomPesanan(): array
    {
        return [
            'metode_ambil' => $this->metode,
            'alamat_kirim' => $this->dikirim() ? $this->alamat : null,
            'area_tujuan' => $this->dikirim() ? $this->areaTujuan : null,
            'jasa_kirim' => $this->dikirim() ? $this->tarif?->kurir : null,
            'layanan_kirim' => $this->dikirim() ? $this->tarif?->layanan : null,
            'ongkir' => $this->ongkir(),
        ];
    }
}
