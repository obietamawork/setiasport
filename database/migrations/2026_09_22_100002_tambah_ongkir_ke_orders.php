<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ongkos kirim pada pesanan (KEP-058).
 *
 * Dibekukan di pesanan seperti biaya layanan (KEP-048): tarif kurir berubah
 * sewaktu-waktu, dan tagihan yang sudah disepakati tidak boleh ikut berubah.
 *
 * Kolom area_tujuan menyimpan kode area milik penyedia ongkir, bukan alamat
 * yang dibaca manusia. Alamat lengkapnya tetap di alamat_kirim, karena kurir
 * membaca alamat, bukan kode.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('ongkir')->default(0)->after('biaya_layanan');
            $table->string('area_tujuan', 60)->nullable()->after('alamat_kirim');
            $table->string('layanan_kirim', 40)->nullable()->after('jasa_kirim');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['ongkir', 'area_tujuan', 'layanan_kirim']);
        });
    }
};
