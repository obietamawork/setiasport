<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Biaya layanan per pesanan (KEP-048).
 *
 * Dibekukan di pesanan, bukan dibaca dari pengaturan saat menghitung total:
 * kalau tarifnya naik bulan depan, tagihan yang sudah disepakati hari ini tidak
 * boleh ikut berubah. Alasan yang sama dengan harga per ukuran (KEP-039).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('biaya_layanan')->default(0)->after('diskon');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('biaya_layanan');
        });
    }
};
