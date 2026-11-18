<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Foto bahan (KEP-110). Tiap bahan punya emboss dan tekstur sendiri, dan
 * itu tidak bisa diceritakan lewat kalimat: Milano dan Hyget sama-sama
 * "polyester" di atas kertas, tetapi berbeda begitu diraba.
 *
 * Kolomnya boleh kosong di basis data karena bahan lama belum berfoto.
 * Kewajibannya dijaga formulir: bahan tanpa foto tidak bisa disimpan lagi
 * sebelum diberi foto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->string('gambar_path')->nullable()->after('deskripsi');
            $table->string('thumb_path')->nullable()->after('gambar_path');
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn(['gambar_path', 'thumb_path']);
        });
    }
};
