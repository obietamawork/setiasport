<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Banner promo di puncak halaman depan (KEP-092), diatur dari panel.
 *
 * Periode tayang ada di sini, bukan diingat-ingat: promo "diskon jersey tim
 * sampai akhir bulan" yang lupa dimatikan adalah janji yang tidak ditepati.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('judul', 120);
            $table->string('subjudul', 200)->nullable();
            $table->string('teks_tombol', 40)->nullable();
            $table->string('tautan', 500)->nullable();

            $table->string('gambar_path');
            // Opsional. Tanpa ini, gambar lebar dipotong tengahnya di HP.
            $table->string('gambar_hp_path')->nullable();

            // Mati untuk gambar yang tulisannya sudah tercetak (desain Canva):
            // tanpa saklar ini judulnya tampil dua kali.
            $table->boolean('teks_di_atas')->default(true);

            $table->date('mulai_pada')->nullable();
            $table->date('selesai_pada')->nullable();

            $table->boolean('aktif')->default(true);
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->timestamps();

            $table->index(['aktif', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
