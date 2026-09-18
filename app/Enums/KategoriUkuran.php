<?php

namespace App\Enums;

/**
 * Kelompok ukuran badan yang dipakai daftar harga klien (KEP-039).
 *
 * Bukan sekadar label: harga jersey berbeda menurut kelompok ini, karena kain
 * yang terpakai berbeda. Pelanggan memilihnya per potong, jadi satu tim boleh
 * memesan seragam untuk anak SD dan pelatihnya sekaligus.
 */
enum KategoriUkuran: string
{
    case SD = 'SD';
    case SMP = 'SMP';
    case DEWASA = 'DEWASA';

    public function label(): string
    {
        return match ($this) {
            self::SD => 'SD',
            self::SMP => 'SMP',
            self::DEWASA => 'SMA / Dewasa',
        };
    }

    /**
     * Ukuran yang tersedia di kelompok ini.
     *
     * @return list<string>
     */
    public function ukuran(): array
    {
        return match ($this) {
            self::SD, self::SMP => ['S', 'M', 'L', 'XL'],
            self::DEWASA => ['S', 'M', 'L', 'XL', 'XXL', '3XL', '4XL', '5XL'],
        };
    }

    /** Label yang dibaca pelanggan di daftar pemain, misalnya "SMP M". */
    public function labelUkuran(string $ukuran): string
    {
        return $this->label().' '.$ukuran;
    }

    public function punyaUkuran(string $ukuran): bool
    {
        return in_array($ukuran, $this->ukuran(), true);
    }
}
