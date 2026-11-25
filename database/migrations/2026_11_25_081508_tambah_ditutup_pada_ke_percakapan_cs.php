<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kapan percakapan CS ditutup (KEP-163). Percakapan yang ditutup lebih dari
 * tujuh hari tidak dimuat lagi di widget pelanggan: pertanyaan baru dimulai
 * sebagai percakapan baru, dan perangkat yang dipakai bersama tidak terus
 * menampilkan chat orang sebelumnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('percakapan_cs', function (Blueprint $table) {
            $table->timestamp('ditutup_pada')->nullable()->after('diperingatkan_pada');
        });

        // Perkiraan terbaik untuk yang sudah ditutup sebelum kolom ini ada.
        DB::table('percakapan_cs')->where('status', 'SELESAI')->update(['ditutup_pada' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('percakapan_cs', function (Blueprint $table) {
            $table->dropColumn('ditutup_pada');
        });
    }
};
