<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daftar pemain jadi sumber jumlah pesanan (KEP-039).
 *
 * Sebelumnya satu baris berarti satu potong, ukurannya teks bebas, dan
 * harganya tidak ada karena seluruh baris memakai satu harga satuan. Sekarang
 * baris menyimpan kategori ukurannya, boleh mewakili beberapa potong sekaligus
 * (pesanan "tanpa nama" 2 pcs), dan membawa harga yang berlaku saat dipesan.
 *
 * Harga ikut disimpan di sini, bukan dihitung ulang dari master, karena
 * pesanan adalah dokumen historis: daftar harga boleh naik tahun depan tanpa
 * mengubah tagihan yang sudah disepakati.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_item_rosters', function (Blueprint $table) {
            $table->string('kategori', 10)->nullable()->after('urutan');
            $table->unsignedSmallInteger('jumlah')->default(1)->after('ukuran');
            $table->unsignedBigInteger('harga_satuan')->nullable()->after('jumlah');
        });
    }

    public function down(): void
    {
        Schema::table('order_item_rosters', function (Blueprint $table) {
            $table->dropColumn(['kategori', 'jumlah', 'harga_satuan']);
        });
    }
};
