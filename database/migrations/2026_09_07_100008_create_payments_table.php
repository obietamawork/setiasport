<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satu order punya BANYAK pembayaran sejak hari pertama (KEP-003), meski payment
 * gateway baru dipasang di fase akhir. Kolom provider/provider_ref/payload_json
 * dibiarkan kosong dulu, itulah yang membuat pemasangan Midtrans atau Xendit
 * nanti menjadi pekerjaan kecil, bukan refactor besar.
 *
 * CATATAN: gerbang pembayaran akhirnya tidak jadi dipakai. Ketiga kolom itu
 * sudah dibuang oleh migrasi 2026_09_09_100001 (KEP-023); berkas ini dibiarkan
 * apa adanya karena migrasi menceritakan sejarah, bukan keadaan sekarang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            $table->string('jenis', 20);        // DP | PELUNASAN | FULL
            $table->string('metode', 20);       // TUNAI | TRANSFER | QRIS_EDC | GATEWAY
            $table->string('status', 20)->default('MENUNGGU');
            $table->unsignedBigInteger('jumlah');

            $table->string('bukti_path')->nullable();

            // Diisi hanya bila metode GATEWAY.
            $table->string('provider', 30)->nullable();
            $table->string('provider_ref')->nullable();
            $table->json('payload_json')->nullable();

            // Wajib untuk pembayaran offline: titik rawan uang kas.
            $table->foreignId('dicatat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('diverifikasi_oleh')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('dibayar_pada')->nullable();
            $table->timestamp('kadaluarsa_pada')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index('status');
            $table->index('provider_ref');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
