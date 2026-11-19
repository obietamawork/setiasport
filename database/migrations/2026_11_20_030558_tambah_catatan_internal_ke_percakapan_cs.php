<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catatan internal per percakapan CS (KEP-150), seperti gambar Ruang CS
 * klien. Hanya untuk staf; tidak pernah dikirim ke pelanggan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('percakapan_cs', function (Blueprint $table) {
            $table->text('catatan_internal')->nullable()->after('topik');
        });
    }

    public function down(): void
    {
        Schema::table('percakapan_cs', function (Blueprint $table) {
            $table->dropColumn('catatan_internal');
        });
    }
};
