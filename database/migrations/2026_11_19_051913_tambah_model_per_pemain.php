<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Model baju per pemain (KEP-123): satu pesanan jersey boleh sebagian
 * berkerah, sebagian oblong.
 *
 * - product_options.per_pemain: opsi yang dipilih per baris daftar pemain,
 *   bukan sekali untuk seluruh barang. Paling banyak satu per produk.
 * - order_item_rosters.option_value_id dan model: nilai yang dipilih untuk
 *   baris itu. Labelnya disimpan sebagai salinan, seperti nama_produk di
 *   order_items, supaya lembar kerja lama tidak berubah kalau nama nilainya
 *   kelak diganti.
 *
 * Opsi bernama "Model Baju" yang sudah ada langsung ditandai, sesuai
 * keputusan pemilik toko: kerah dan oblong memang pilihan per pemain.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_options', function (Blueprint $table) {
            $table->boolean('per_pemain')->default(false)->after('wajib');
        });

        Schema::table('order_item_rosters', function (Blueprint $table) {
            $table->foreignId('option_value_id')->nullable()->after('harga_satuan')
                ->constrained('option_values')->nullOnDelete();
            $table->string('model', 60)->nullable()->after('option_value_id');
        });

        DB::table('product_options')->whereRaw('LOWER(nama) = ?', ['model baju'])->update(['per_pemain' => true]);
    }

    public function down(): void
    {
        Schema::table('order_item_rosters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('option_value_id');
            $table->dropColumn('model');
        });

        Schema::table('product_options', function (Blueprint $table) {
            $table->dropColumn('per_pemain');
        });
    }
};
