<?php

namespace Database\Seeders;

use App\Services\ContohProfil;
use Illuminate\Database\Seeder;

/**
 * Contoh portofolio dan testimoni untuk demo ke klien (KEP-035).
 *
 * SENGAJA tidak dipanggil DatabaseSeeder: instalasi baru tidak boleh dimulai
 * dengan testimoni karangan. Jalankan sendiri di lokal:
 *
 *     php artisan db:seed --class=ContohProfilSeeder
 *
 * dan hapus dari Panel → Portofolio sebelum website dibuka untuk umum.
 */
class ContohProfilSeeder extends Seeder
{
    public function run(ContohProfil $contoh): void
    {
        if (app()->isProduction()) {
            $this->command?->error('Data contoh tidak dipasang di produksi: testimoni karangan di website asli sama dengan ulasan palsu.');

            return;
        }

        $hasil = $contoh->pasang();

        $this->command?->info("Terpasang {$hasil['portofolio']} portofolio dan {$hasil['testimoni']} testimoni contoh.");
        $this->command?->warn('Hapus lewat Panel → Portofolio → "Hapus semua data contoh" sebelum website tayang.');
    }
}
