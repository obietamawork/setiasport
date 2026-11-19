<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Waktu pengerjaan kustom ±7 hari kerja (KEP-132).
 *
 * Klien menyatakan tidak ada produk kustom yang 10 hari kerja. Lima produk
 * bawaan katalog masih berisi 10 (jaket, celana training, seragam, kaos
 * sekolah, jersey polos). Hanya yang masih 10 yang diubah: angka lain
 * berarti pemilik sudah mengaturnya sendiri di panel. Pesanan yang sudah
 * ada tidak tersentuh; target selesainya sudah tercatat di pesanan.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')
            ->whereIn('fulfillment_type', ['PREORDER', 'BOTH'])
            ->where('lead_time_hari', 10)
            ->update(['lead_time_hari' => 7]);
    }

    /**
     * Tidak dikembalikan: setelah migrasi ini, produk ber-7 hari tidak bisa
     * dibedakan antara yang diubah di sini dan yang memang 7 sejak awal.
     */
    public function down(): void {}
};
