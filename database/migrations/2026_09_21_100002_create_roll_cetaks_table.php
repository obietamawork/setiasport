<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roll kertas printing (KEP-051).
 *
 * Printing tidak berjalan per pesanan, melainkan per roll kertas: satu roll
 * memuat beberapa pesanan sekaligus, dan pesanan kecil diselipkan untuk
 * menggenapi sisa kertas. Menandainya satu per satu berarti operator menandai
 * hal yang sebenarnya sudah selesai bersamaan, dan itu yang selama ini bikin
 * status printing telat diperbarui.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roll_cetaks', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 20)->unique();

            // Kunci QR roll, sama panjangnya dengan token lembar kerja: ia juga
            // beredar sebagai gambar di grup WhatsApp.
            $table->string('token', 64)->unique();

            $table->string('catatan', 200)->nullable();
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('pekerja_id')->nullable()->constrained('pekerjas')->nullOnDelete();
            $table->timestamp('selesai_pada')->nullable();
            $table->timestamps();
        });

        Schema::create('roll_cetak_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('roll_cetak_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['roll_cetak_id', 'order_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roll_cetak_item');
        Schema::dropIfExists('roll_cetaks');
    }
};
