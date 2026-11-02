<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * Kalimat pembuka halaman depan diganti teks dari klien (KEP-103).
 *
 * Seeder tidak pernah menimpa nilai yang sudah tersimpan, jadi teks bawaan yang
 * lama tetap tampil di website yang sudah berjalan. Hanya diganti kalau isinya
 * masih teks bawaan itu: yang sudah diubah pemilik toko di Pengaturan dibiarkan.
 * Disimpan lewat model supaya cache pengaturannya ikut dibuang.
 */
return new class extends Migration
{
    private const LAMA = 'Bola, sepatu, kaos, dan aksesoris olahraga dari merek yang jelas, ditambah layanan konveksi jersey yang dikerjakan sendiri di workshop kami. Melayani ecer maupun tim.';

    private const BARU = 'Tersedia berbagai kebutuhan olahraga mulai dari sepatu, bola, jersey, pakaian olahraga, aksesoris, hingga layanan custom jersey sesuai dengan desainmu.';

    public function up(): void
    {
        $this->ganti(self::LAMA, self::BARU);
    }

    public function down(): void
    {
        $this->ganti(self::BARU, self::LAMA);
    }

    private function ganti(string $dari, string $ke): void
    {
        $setting = Setting::find('tentang_ringkas');

        if ($setting && $setting->value === $dari) {
            $setting->value = $ke;
            $setting->save();
        }
    }
};
