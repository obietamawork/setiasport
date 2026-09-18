<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengajuan pembatalan dari pelanggan (KEP-041).
 *
 * Pembatalan tidak pernah terjadi sendiri: pelanggan mengajukan, toko yang
 * memutuskan, lalu uangnya dikembalikan di luar sistem (transfer atau tunai)
 * dan pengembaliannya dicatat di sini.
 *
 * Nilai yang jadi dasar keputusan ikut dibekukan: berapa yang sudah dibayar
 * dan sampai tahap mana pekerjaan berjalan saat pengajuan masuk. Tanpa itu,
 * pesanan yang statusnya sudah berubah membuat keputusan lama tidak bisa lagi
 * dijelaskan ketika pelanggan bertanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pembatalans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            $table->string('status', 20)->default('DIAJUKAN');  // DIAJUKAN|DISETUJUI|DITOLAK
            $table->string('tahap', 20);                        // AWAL|DESAIN|PRODUKSI
            $table->text('alasan');

            $table->unsignedBigInteger('sudah_dibayar');
            $table->unsignedBigInteger('refund_usulan');
            $table->unsignedBigInteger('refund_jumlah')->nullable();
            $table->string('refund_metode', 20)->nullable();

            $table->text('catatan_admin')->nullable();
            $table->foreignId('diputuskan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diputuskan_pada')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pembatalans');
    }
};
