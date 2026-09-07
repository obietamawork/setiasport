<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Struktur harga produk kustom (KEP-009).
 *
 * Rumus resmi:
 *   harga_satuan  = tier(product, qty) + SUM(opsi PER_PCS)
 *   subtotal_item = harga_satuan * qty  + SUM(opsi PER_ITEM)
 *
 * Kolom option_values.basis adalah inti dari rumus itu: tanpa pemisahan PER_PCS
 * dan PER_ITEM, biaya setup desain akan ikut dikali qty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_price_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('min_qty');
            $table->unsignedInteger('max_qty')->nullable();   // null = tak terbatas
            $table->unsignedBigInteger('harga_satuan');
            $table->timestamps();

            $table->index(['product_id', 'min_qty']);
        });

        Schema::create('product_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('nama');                            // Bahan, Lengan, Kerah
            $table->boolean('wajib')->default(true);
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'urutan']);
        });

        Schema::create('option_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_option_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->bigInteger('tambahan_harga')->default(0); // boleh negatif (mis. Hyget -8.000)
            $table->string('basis', 10)->default('PER_PCS');  // PER_PCS | PER_ITEM
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->index(['product_option_id', 'urutan']);
        });

        Schema::create('design_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('nama');
            $table->string('kode', 40)->unique();
            $table->string('preview_path');
            $table->string('source_url')->nullable();   // file mentah di Drive (KEP-002)
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_templates');
        Schema::dropIfExists('option_values');
        Schema::dropIfExists('product_options');
        Schema::dropIfExists('product_price_tiers');
    }
};
