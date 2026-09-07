<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('email');                 // wajib: satu-satunya kanal otomatis (KEP-004)
            $table->string('no_hp', 20);
            $table->string('catatan')->nullable();

            // Tetap nullable. Akun pelanggan tidak ada sekarang (KEP-008), tapi
            // kolom ini membuatnya bisa ditambahkan nanti tanpa migrasi ulang.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();

            $table->index('email');
            $table->index('no_hp');
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 20)->unique();               // SS-2609-0042
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();

            // Agregat dari status seluruh item (KEP-001), tidak disetel manual.
            $table->string('status', 30);

            // Akses tanpa password (KEP-008). Sengaja panjang & acak.
            $table->string('access_token', 64)->unique();

            $table->string('tipe_pembayaran', 10);              // LUNAS | DP
            $table->bigInteger('diskon')->default(0);

            // TIDAK ADA kolom subtotal/total/sudah_dibayar/sisa_tagihan.
            // Semuanya dihitung ulang dari items + adjustments + payments (KEP-003).

            $table->date('target_siap_pada')->nullable();
            $table->timestamp('kadaluarsa_pada')->nullable();   // batas waktu bayar
            $table->timestamp('siap_pada')->nullable();
            $table->timestamp('diambil_pada')->nullable();

            $table->string('kode_ambil', 12)->nullable();
            $table->string('diambil_oleh')->nullable();         // nama pengambil bila diwakilkan
            $table->foreignId('diserahkan_oleh')->nullable()->constrained('users')->nullOnDelete();

            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['status', 'siap_pada']);
            $table->index('kadaluarsa_pada');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->restrictOnDelete();

            // Di-SNAPSHOT saat checkout, bukan di-join ke produk: order adalah
            // dokumen historis dan harus tetap terbaca benar setelah master berubah.
            $table->string('nama_produk');
            $table->string('fulfillment_type', 20);
            $table->unsignedInteger('qty');
            $table->unsignedBigInteger('harga_satuan');   // tier + opsi PER_PCS

            $table->string('status_item', 30);

            // Desain yang disetujui pelanggan. FK ditambahkan di migrasi terpisah
            // karena design_assets baru dibuat setelah tabel ini.
            $table->unsignedBigInteger('approved_design_asset_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('approved_by_name')->nullable();   // nama yang DIKETIK pelanggan
            $table->string('approved_ip', 45)->nullable();
            $table->unsignedSmallInteger('jumlah_revisi')->default(0);

            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status_item']);
            $table->index('status_item');
        });

        Schema::create('order_item_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();

            // Snapshot lengkap: label maupun basis ikut disimpan agar rincian
            // harga lama tetap bisa dijelaskan meski master opsi sudah berubah.
            $table->string('option_nama');
            $table->string('option_value');
            $table->bigInteger('tambahan_harga');
            $table->string('basis', 10);
            $table->timestamps();

            $table->index('order_item_id');
        });

        Schema::create('order_item_rosters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('urutan');
            $table->string('nama_punggung', 60)->nullable();
            $table->string('nomor', 5)->nullable();
            $table->string('ukuran', 20);
            $table->string('catatan')->nullable();
            $table->timestamps();

            $table->index(['order_item_id', 'urutan']);
        });

        Schema::create('order_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('deskripsi');
            $table->bigInteger('jumlah');           // boleh negatif (diskon/kompensasi)
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_adjustments');
        Schema::dropIfExists('order_item_rosters');
        Schema::dropIfExists('order_item_options');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('customers');
    }
};
