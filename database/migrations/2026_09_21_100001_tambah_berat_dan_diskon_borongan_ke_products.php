<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Berat produk dan potongan borongan (KEP-050).
 *
 * Berat dibutuhkan hitung ongkir: kurir menagih per kilogram, dan tanpa angka
 * ini tidak ada penyedia ongkir mana pun yang bisa dipanggil.
 *
 * Potongan borongan mengikuti kebiasaan toko: di atas satu lusin, harga dipotong
 * sekian rupiah per potong. Disimpan per produk, bukan sebagai satu angka global,
 * karena tiap produk punya margin sendiri.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('berat_gram')->default(0)->after('harga_dasar');

            // 0 berarti produk ini tidak punya potongan borongan.
            $table->unsignedSmallInteger('diskon_min_qty')->default(0)->after('moq');
            $table->unsignedInteger('diskon_per_pcs')->default(0)->after('diskon_min_qty');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['berat_gram', 'diskon_min_qty', 'diskon_per_pcs']);
        });
    }
};
