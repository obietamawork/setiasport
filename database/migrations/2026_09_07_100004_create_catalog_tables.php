<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('nama');
            $table->string('slug')->unique();
            $table->string('deskripsi')->nullable();
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->index(['parent_id', 'urutan']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('sku', 60)->unique();
            $table->string('nama');
            $table->string('slug')->unique();
            $table->text('deskripsi')->nullable();

            // READY_STOCK | PREORDER | BOTH (KEP-011)
            $table->string('fulfillment_type', 20);

            // Jalur ready stock. Untuk produk PREORDER murni nilainya 0.
            $table->unsignedBigInteger('harga_dasar')->default(0);

            // Jalur kustom. Berlaku untuk PREORDER dan BOTH.
            $table->unsignedInteger('moq')->default(1);
            $table->unsignedSmallInteger('lead_time_hari')->default(0);
            $table->boolean('roster_wajib')->default(false);

            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->index(['category_id', 'aktif']);
            $table->index('fulfillment_type');
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 60)->unique();
            $table->string('ukuran', 20)->nullable();
            $table->string('warna', 40)->nullable();
            $table->bigInteger('tambahan_harga')->default(0);

            // Stok tersedia = stok - stok_dipesan. Pemisahan ini mencegah dua
            // pelanggan membeli barang terakhir yang sama (docs/04).
            $table->unsignedInteger('stok')->default(0);
            $table->unsignedInteger('stok_dipesan')->default(0);

            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->unique(['product_id', 'ukuran', 'warna']);
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('alt')->nullable();
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
    }
};
