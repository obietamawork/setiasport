<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nilai bisnis yang pasti akan diubah pemilik toko (persentase DP, batas waktu
 * bayar, jumlah revisi gratis) disimpan di sini, bukan di-hardcode.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->text('value')->nullable();
            $table->string('tipe', 20)->default('string');   // string|int|bool|json
            $table->string('grup', 50)->default('umum');
            $table->string('label');
            $table->string('deskripsi')->nullable();
            $table->timestamps();

            $table->index('grup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
