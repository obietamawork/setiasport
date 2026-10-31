<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Email gagal yang sudah ditangani (KEP-098).
 *
 * Dashboard menampilkan email yang gagal atau terpental supaya pelanggannya
 * dikabari lewat WhatsApp. Dulu daftar itu tidak pernah berkurang: email yang
 * sudah ditindaklanjuti tetap tampil, dan peringatan yang selalu ada akhirnya
 * tidak dibaca lagi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_logs', function (Blueprint $table) {
            $table->timestamp('ditindaklanjuti_pada')->nullable()->after('error');
            $table->foreignId('ditindaklanjuti_oleh')->nullable()->after('ditindaklanjuti_pada')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('email_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ditindaklanjuti_oleh');
            $table->dropColumn('ditindaklanjuti_pada');
        });
    }
};
