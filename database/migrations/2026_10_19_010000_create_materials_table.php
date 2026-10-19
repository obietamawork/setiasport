<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kamus bahan, dipakai lintas produk (KEP-072).
 *
 * Nama bahan seperti "Milano" atau "Hyget" adalah istilah dagang konveksi;
 * pelanggan awam tidak tahu artinya. Deskripsinya ditulis SEKALI di sini,
 * bukan diketik ulang di tiap produk yang kebetulan memakai bahan yang sama
 * -- harga tetap per produk (tambahan_harga di option_values), yang dibagi
 * di sini cuma penjelasannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->unique();
            $table->text('deskripsi')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materials');
    }
};
