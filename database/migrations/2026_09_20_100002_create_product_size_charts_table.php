<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Panduan ukuran per produk (KEP-049).
 *
 * Daftar ukurannya ikut diambil dari sini, bukan dari daftar tetap di kode:
 * celana pendek berhenti di 3XL sementara jersey dewasa sampai 5XL, dan yang
 * tahu batasnya adalah panduan ukuran yang dikirim klien, bukan program.
 *
 * Angkanya disimpan sebagai JSON bernama, misalnya {"Lebar": 35, "Panjang": 45},
 * karena tiap produk mengukur hal yang berbeda: celana diukur lebar dan panjang,
 * jersey diukur lingkar dada dan panjang baju.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_size_charts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('kategori', 10)->nullable();   // null = berlaku semua umur
            $table->string('ukuran', 10);
            $table->json('ukuran_cm');
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'kategori', 'ukuran'], 'produk_kategori_ukuran_unik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_size_charts');
    }
};
