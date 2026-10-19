<?php

namespace Database\Seeders;

use App\Models\Material;
use Illuminate\Database\Seeder;

/**
 * Kamus bahan (KEP-072), diisi dari nama bahan yang sudah dipakai di
 * CatalogSeeder supaya opsi "Bahan" produk contoh langsung tertaut begitu
 * seeder dijalankan -- bukan menyisakan kamus kosong yang mesti diisi
 * manual satu-satu.
 *
 * Deskripsinya sengaja dibiarkan kosong: ini istilah dagang konveksi toko
 * sendiri (bukan istilah tekstil umum), jadi hanya pemilik toko yang tahu
 * persis apa bedanya. firstOrCreate, bukan updateOrCreate, supaya deskripsi
 * yang sudah ditulis admin tidak pernah tertimpa saat seeder dijalankan ulang.
 */
class MaterialSeeder extends Seeder
{
    private const NAMA = [
        'Serena', 'Jarum', 'Milano', 'Wafel', 'Embos',
        'Lotto', 'Diadora', 'Micro', 'Diadora Lokal', 'Diadora Import',
        'PE Printing Lotto', 'PE Printing Diadora', 'Katun Lotto', 'Katun Diadora',
        'PE', 'PE 20', 'Lakos PE', 'Katun', 'Lakos CVC', 'PE 20 (Semi Katun)',
        'Katun 24s', 'Katun 30s', 'Dryfit Serena', 'Hyget',
        'Cotton Combed 30s', 'Lacoste CVC',
    ];

    public function run(): void
    {
        foreach (self::NAMA as $nama) {
            Material::firstOrCreate(['nama' => $nama]);
        }
    }
}
