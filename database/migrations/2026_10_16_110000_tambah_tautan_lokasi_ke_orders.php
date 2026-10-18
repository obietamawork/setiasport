<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tautan lokasi Google Maps, pelengkap alamat_kirim (KEP-070).
 *
 * Pelanggan membagikan titik lokasinya sendiri lewat "Bagikan lokasi" di
 * aplikasi Maps yang sudah dipakai, bukan mengetik ulang koordinat atau
 * memakai widget peta buatan sendiri -- pola yang sama dengan tautan unggah
 * file besar (KEP-065): manfaatkan alat yang sudah dipercaya pelanggan,
 * toko cukup menampung tautannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('tautan_lokasi', 500)->nullable()->after('alamat_kirim');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('tautan_lokasi');
        });
    }
};
