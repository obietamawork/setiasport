<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Harga per potong menurut kategori ukuran (KEP-039).
 *
 * Daftar harga klien tidak punya satu harga per produk: satu jersey Serena
 * berharga Rp45.000 untuk ukuran SD dan Rp60.000 untuk dewasa, dan satu tim
 * bisa memesan keduanya sekaligus. Rumus lama (satu harga_satuan dikali qty)
 * tidak bisa menyatakan itu.
 *
 * Rumus barunya:
 *   harga_potong  = harga(produk, kategori, bahan)
 *                   + SUM(opsi PER_PCS)        ← lengan panjang, kerah, saku
 *                   + tambahan_ukuran(ukuran)  ← 3XL, 5XL
 *   subtotal_item = SUM(harga_potong * jumlah) + SUM(opsi PER_ITEM)
 *
 * Kenapa matriks, bukan penjumlahan bertingkat: selisih antar kategori TIDAK
 * selalu tetap. Serena naik Rp10.000 dari SD ke SMP lalu Rp5.000 ke dewasa,
 * sedangkan Embos naik Rp10.000 dua kali. Rumus bertingkat akan memaksa harga
 * klien dibulatkan agar cocok, dan itu mengubah harga jual tanpa izin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            // null = berlaku untuk semua kategori. Celana pendek fullprint
            // memang satu harga dari SD sampai dewasa.
            $table->string('kategori', 10)->nullable();

            // Opsi penentu harga, dalam praktiknya bahan. null = semua bahan.
            $table->foreignId('option_value_id')->nullable()
                ->constrained('option_values')->cascadeOnDelete();

            $table->unsignedBigInteger('harga_satuan');
            $table->timestamps();

            $table->unique(['product_id', 'kategori', 'option_value_id'], 'produk_kategori_bahan_unik');
        });

        Schema::create('product_size_surcharges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            // Ukuran badan, bukan kategori: 3XL dan 5XL makan kain lebih banyak
            // berapa pun umur pemakainya.
            $table->string('ukuran', 10);
            $table->bigInteger('tambahan_harga');
            $table->timestamps();

            $table->unique(['product_id', 'ukuran']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_size_surcharges');
        Schema::dropIfExists('product_prices');
    }
};
