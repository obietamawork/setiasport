<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Barang masuk dari supplier (KEP-120).
 *
 * Dipisah dari penyesuaian stok, yang tetap dicatat di log aktivitas: yang
 * satu menambah stok karena ada kiriman, yang lain membetulkan angka yang
 * salah. Satu baris per varian per kiriman, supaya "berapa Jersey Polos L
 * yang kita beli bulan ini, dari siapa" bisa dijawab.
 *
 * Harga beli boleh kosong: tidak semua kiriman sudah ada notanya saat
 * barang diterima.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barang_masuk', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('jumlah');
            $table->string('supplier', 100);
            $table->unsignedBigInteger('harga_beli')->nullable();
            $table->date('tanggal');
            $table->string('catatan')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['tanggal', 'id']);
            $table->index('supplier');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barang_masuk');
    }
};
