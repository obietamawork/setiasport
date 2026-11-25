<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pilihan potongan Pria / Wanita untuk jersey kustom (KEP-161).
 *
 * Pelanggan sering lupa bahwa jersey wanita dipotong berbeda (pinggang
 * mengecil, lengan lebih pendek). Harganya sama, jadi kedua nilai +0.
 * Dipasang lewat migrasi, bukan langkah seeder yang berjalan di setiap rilis:
 * opsi yang sengaja dihapus pemilik toko tidak boleh muncul lagi.
 *
 * Opsinya wajib dan ditaruh paling atas; wizard memilih "Pria" lebih dulu,
 * jadi setiap lembar kerja menyebut potongannya dengan jelas.
 */
return new class extends Migration
{
    private const SKU = ['CUST-JRS-ATASAN', 'CUST-SETELAN'];

    public function up(): void
    {
        $produk = DB::table('products')->whereIn('sku', self::SKU)->pluck('id');

        foreach ($produk as $id) {
            if (DB::table('product_options')->where('product_id', $id)->where('nama', 'Model Jersey')->exists()) {
                continue;
            }

            DB::transaction(function () use ($id) {
                // Urutan opsi lain digeser, urutan relatifnya tetap.
                DB::table('product_options')->where('product_id', $id)->increment('urutan');

                $opsi = DB::table('product_options')->insertGetId([
                    'product_id' => $id,
                    'nama' => 'Model Jersey',
                    'wajib' => true,
                    'per_pemain' => false,
                    'urutan' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                foreach (['Pria', 'Wanita'] as $i => $label) {
                    DB::table('option_values')->insert([
                        'product_option_id' => $opsi,
                        'label' => $label,
                        'tambahan_harga' => 0,
                        'basis' => 'PER_PCS',
                        'urutan' => $i,
                        'aktif' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
        }
    }

    public function down(): void
    {
        $produk = DB::table('products')->whereIn('sku', self::SKU)->pluck('id');

        foreach ($produk as $id) {
            $opsi = DB::table('product_options')->where('product_id', $id)->where('nama', 'Model Jersey')->value('id');

            if ($opsi === null) {
                continue;
            }

            DB::table('product_options')->where('id', $opsi)->delete();
            DB::table('product_options')->where('product_id', $id)->where('urutan', '>', 0)->decrement('urutan');
        }
    }
};
