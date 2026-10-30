<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alasan bukti pembayaran ditolak (KEP-097).
 *
 * Dulu alasannya hanya masuk log aktivitas staf. Pelanggan tidak pernah tahu
 * kenapa pembayarannya tidak diterima, padahal dialah yang harus memperbaikinya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('alasan_tolak', 150)->nullable()->after('bukti_path');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('alasan_tolak');
        });
    }
};
