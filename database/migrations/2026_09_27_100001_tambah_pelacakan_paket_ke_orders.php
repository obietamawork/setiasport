<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Posisi paket menurut kurir, disimpan di pesanan (KEP-061).
 *
 * Disimpan, bukan ditanyakan ulang tiap halaman dibuka: tiap panggilan ke
 * penyedia berbiaya, dan halaman pesanan dibuka pelanggan berkali-kali sehari
 * justru ketika paketnya sedang jalan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('lacak_status', 40)->nullable()->after('no_resi');
            $table->string('lacak_catatan', 200)->nullable()->after('lacak_status');
            $table->json('lacak_riwayat')->nullable()->after('lacak_catatan');
            $table->timestamp('lacak_pada')->nullable()->after('lacak_riwayat');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['lacak_status', 'lacak_catatan', 'lacak_riwayat', 'lacak_pada']);
        });
    }
};
