<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satu gambar boleh ditandai mewakili satu warna varian (KEP-066). Memilih
 * warna di halaman produk lalu mengganti foto yang tampil, seperti kebiasaan
 * marketplace, tanpa produk butuh galeri terpisah per warna.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->string('warna', 40)->nullable()->after('alt');
        });
    }

    public function down(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->dropColumn('warna');
        });
    }
};
