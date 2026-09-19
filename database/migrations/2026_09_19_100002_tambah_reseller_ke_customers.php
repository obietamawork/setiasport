<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda pelanggan reseller (KEP-046).
 *
 * Klien memutuskan reseller tidak punya harga sendiri di katalog: potongannya
 * diberikan admin per pesanan lewat penyesuaian biaya, dan kesepakatannya
 * dibicarakan lewat WhatsApp. Yang dibutuhkan sistem cuma satu hal, yaitu
 * mengingat siapa yang reseller, supaya admin tidak lupa memberi potongan dan
 * tidak perlu mengingat-ingat daftarnya sendiri.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('reseller')->default(false)->after('no_hp');
            $table->string('catatan_reseller', 200)->nullable()->after('reseller');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['reseller', 'catatan_reseller']);
        });
    }
};
