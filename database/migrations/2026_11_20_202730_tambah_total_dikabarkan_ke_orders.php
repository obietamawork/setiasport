<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Total terakhir yang dikabarkan ke pelanggan (KEP-158). Kabar "tagihan
 * diperbarui" dilewati bila totalnya sama dengan yang sudah ia ketahui,
 * misalnya penyesuaian yang ditambah lalu dihapus lagi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->bigInteger('total_dikabarkan')->nullable()->after('ongkir');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('total_dikabarkan');
        });
    }
};
