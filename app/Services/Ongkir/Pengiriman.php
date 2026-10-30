<?php

namespace App\Services\Ongkir;

use App\Models\Setting;

/**
 * Aturan pengiriman toko, di atas penyedia ongkir (KEP-058).
 *
 * Penyedia hanya tahu tarif kurir. Yang tahu kapan ongkir digratiskan, berapa
 * berat bawaan sebuah barang, dan bahwa pilihan dari browser harus dihitung
 * ulang sebelum dipercaya, adalah kelas ini.
 *
 * Seluruh checkout memanggil kelas ini, bukan penyedianya langsung, supaya
 * aturan yang sama berlaku di halaman pemesanan maupun saat pesanan disimpan.
 */
class Pengiriman
{
    public function __construct(private PenyediaOngkir $penyedia) {}

    /** Ongkir otomatis sedang bisa dipakai. Kalau tidak, admin mengisinya manual. */
    public function otomatis(): bool
    {
        return $this->penyedia->aktif();
    }

    /** Belanja di atas nominal ini bebas ongkir. 0 berarti aturannya mati. */
    public function gratisMulai(): int
    {
        return (int) Setting::ambil('gratis_ongkir_minimal', 0);
    }

    public function gratis(int $subtotal): bool
    {
        $batas = $this->gratisMulai();

        return $batas > 0 && $subtotal >= $batas;
    }

    /**
     * Pilihan pengiriman yang boleh ditawarkan ke pelanggan.
     *
     * Ongkir yang digratiskan tetap ditampilkan sebagai pilihan kurir, hanya
     * harganya nol: pelanggan tetap berhak memilih lewat siapa paketnya jalan.
     *
     * @return list<TarifKirim>
     */
    public function pilihan(string $areaTujuan, int $beratGram, int $subtotal = 0): array
    {
        $tarif = $this->penyedia->tarif($areaTujuan, $beratGram);

        // Termurah lebih dulu. Urutan penyedia bisa menaruh JNE Trucking, yang
        // bertarif minimum 10 kg, di atas Reguler untuk paket satu kaus.
        usort($tarif, fn (TarifKirim $a, TarifKirim $b) => $a->harga <=> $b->harga);

        if (! $this->gratis($subtotal)) {
            return $tarif;
        }

        return array_map(fn (TarifKirim $t) => new TarifKirim(
            kurir: $t->kurir,
            layanan: $t->layanan,
            nama: $t->nama,
            harga: 0,
            estimasi: $t->estimasi,
        ), $tarif);
    }

    /** @return list<array{kode: string, nama: string}> */
    public function cariArea(string $kata): array
    {
        return $this->penyedia->cariArea($kata);
    }
}
