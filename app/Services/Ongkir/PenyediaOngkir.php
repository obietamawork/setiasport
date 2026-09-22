<?php

namespace App\Services\Ongkir;

/**
 * Penyedia hitung ongkir dan pelacakan paket (KEP-057).
 *
 * Dipisah jadi antarmuka karena harga dan syarat layanan penyedia semacam ini
 * berubah tiap tahun. Berpindah penyedia harus berarti mengganti satu kelas,
 * bukan menyentuh checkout.
 */
interface PenyediaOngkir
{
    public function aktif(): bool;

    /**
     * Pilihan pengiriman ke satu tujuan. Mengembalikan daftar kosong berarti
     * "tidak bisa dihitung sekarang", dan pemanggilnya wajib tetap jalan.
     *
     * @return list<TarifKirim>
     */
    public function tarif(string $areaTujuan, int $beratGram): array;

    /** Posisi paket menurut kurirnya, atau null kalau tidak bisa dilacak. */
    public function lacak(string $noResi, string $kurir): ?StatusPaket;

    /**
     * Mencari kode area tujuan dari nama tempat atau kode pos.
     *
     * @return list<array{kode: string, nama: string}>
     */
    public function cariArea(string $kata): array;
}
