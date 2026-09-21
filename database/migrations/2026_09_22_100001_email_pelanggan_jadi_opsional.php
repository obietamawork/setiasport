<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Email pelanggan jadi opsional (KEP-054).
 *
 * Dulu email wajib karena ia satu-satunya kanal pemberitahuan otomatis
 * (KEP-004). Sejak pesanan bisa diketik admin di panel, itu tidak lagi benar:
 * pembeli yang datang ke toko atau reseller yang memesan lewat WhatsApp sering
 * tidak punya email, dan memaksa admin mengarang alamat palsu jauh lebih buruk
 * daripada tidak punya alamat sama sekali. Alamat karangan membuat pemberitahuan
 * terkirim ke ruang hampa, dan tidak ada yang tahu pelanggannya tidak pernah
 * dikabari.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });
    }
};
