<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengiriman terbatas untuk barang stok toko (KEP-026).
 *
 * Rancangan awal menolak pengiriman sepenuhnya, dan sebagian besar penolakan itu
 * masih berlaku: tidak ada hitung ongkir, tidak ada API kurir, tidak ada resi
 * yang ditarik otomatis. Yang ditambahkan hanya apa yang benar-benar dikerjakan
 * orang di toko: alamat yang disepakati lewat WhatsApp, nama jasa kirim, dan
 * nomor resi yang diketik admin setelah paket diserahkan ke kurir.
 *
 * Ongkir tidak punya kolom sendiri. Ia dicatat sebagai baris penyesuaian biaya
 * (KEP-007) supaya muncul apa adanya di rincian yang dilihat pelanggan, bukan
 * tersembunyi sebagai angka yang tidak bisa dijelaskan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('metode_ambil', 20)->default('AMBIL_TOKO')->after('status');

            $table->text('alamat_kirim')->nullable()->after('catatan');
            $table->string('jasa_kirim', 50)->nullable()->after('alamat_kirim');
            $table->string('no_resi', 60)->nullable()->after('jasa_kirim');
            $table->timestamp('dikirim_pada')->nullable()->after('no_resi');

            // Dipisahkan dari dikirim_pada supaya "sudah berangkat" dan "sudah
            // sampai" tidak tertukar: di antara keduanya paket masih di jalan,
            // dan pesanan tetap terhitung aktif.
            $table->timestamp('diterima_pada')->nullable()->after('dikirim_pada');

            $table->index('metode_ambil');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['metode_ambil']);
            $table->dropColumn([
                'metode_ambil', 'alamat_kirim', 'jasa_kirim',
                'no_resi', 'dikirim_pada', 'diterima_pada',
            ]);
        });
    }
};
