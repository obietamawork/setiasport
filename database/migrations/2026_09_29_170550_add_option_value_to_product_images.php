<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pelengkap KEP-066: warna hanya berlaku untuk produk ready stock
 * (product_variants.warna). Produk kustom membedakan dirinya lewat pilihan
 * (ProductOption/OptionValue) — bahan, kerah, lengan, dan sejenisnya — jadi
 * gambar juga perlu bisa ditautkan ke SALAH SATU nilai pilihan itu, bukan
 * cuma warna.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->foreignId('option_value_id')->nullable()->after('warna')
                ->constrained('option_values')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->dropConstrainedForeignId('option_value_id');
        });
    }
};
