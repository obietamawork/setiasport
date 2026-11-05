<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gambar pola hitam putih per produk kustom (KEP-106).
 *
 * Produk yang sudah ada diisi menurut SKU katalog awal; pemilik toko bisa
 * mengubahnya di formulir produk. Produk yang tidak dikenal dibiarkan kosong,
 * jadi tidak ada gambar yang menyesatkan.
 */
return new class extends Migration
{
    private const POLA = [
        'CUST-JRS-ATASAN' => 'kaos',
        'CUST-SETELAN' => 'setelan',
        'CUST-CELANA-PENDEK' => 'celana_pendek',
        'CUST-JAKET' => 'jaket',
        'CUST-TRAINING' => 'celana_panjang',
        'CUST-SERAGAM' => 'seragam',
        'CUST-KAOS-KERAH' => 'kaos_kerah',
        'CUST-KAOS-OBLONG' => 'kaos',
        'CUST-KAOS-KATUN' => 'kaos',
        'JRS-FUTSAL-FP' => 'kaos',
        'KAOS-SEKOLAH-SB' => 'kaos',
        'JRS-POLOS-01' => 'kaos',
    ];

    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('pola', 20)->nullable()->after('fulfillment_type');
        });

        foreach (self::POLA as $sku => $pola) {
            DB::table('products')->where('sku', $sku)->whereNull('pola')->update(['pola' => $pola]);
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('pola');
        });
    }
};
