<?php

namespace App\Services\Ongkir;

/**
 * Penyedia yang sengaja tidak melakukan apa pun (KEP-057).
 *
 * Dipakai selama kunci API belum ada, dan itu bukan keadaan darurat: sampai
 * hari ini toko memang menghitung ongkir sendiri lalu menuliskannya sebagai
 * penyesuaian biaya. Yang penting checkout tidak pernah ikut mati.
 */
class OngkirMati implements PenyediaOngkir
{
    public function aktif(): bool
    {
        return false;
    }

    public function tarif(string $areaTujuan, int $beratGram): array
    {
        return [];
    }

    public function lacak(string $noResi, string $kurir): ?StatusPaket
    {
        return null;
    }

    public function cariArea(string $kata): array
    {
        return [];
    }
}
