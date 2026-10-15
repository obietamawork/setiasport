<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pilihan "Pilih dari katalog / Kirim desain sendiri / Dibantu admin" dari
 * konfigurator kustom (KEP-040) selama ini cuma dilebur jadi kalimat bebas di
 * kolom catatan -- tidak bisa dicek ulang oleh kode. Akibatnya pelanggan yang
 * sudah bilang "saya kirim sendiri" tidak pernah ditawari kotak unggahnya
 * secara otomatis di halaman pesanan; mereka harus menemukan sendiri menu
 * "Kirim desain sendiri" yang tertutup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('desain_sumber', 10)->nullable()->after('catatan');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('desain_sumber');
        });
    }
};
