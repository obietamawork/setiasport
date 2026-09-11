<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chat CS di website (KEP-030).
 *
 * Klien menetapkan komplain HANYA dilayani lewat chat CS. Itu menjadikan log
 * chat sebagai catatan resmi toko, jadi pesannya disimpan di sini, di database
 * kita sendiri, bukan dititipkan ke penyedia realtime yang riwayat gratisnya
 * pendek. Pusher hanya pengantar kabar "ada pesan baru".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('percakapan_cs', function (Blueprint $table) {
            $table->id();

            // Pelanggan tanpa akun dikenali lewat cookie. Yang disimpan di sini
            // hanya hash-nya: kalau tabel ini bocor, isinya tidak bisa dipakai
            // untuk membuka percakapan siapa pun.
            $table->char('token_hash', 64)->unique();

            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();

            // Kode pesanan yang sekadar DIKETIK pelanggan belum membuktikan
            // apa-apa. Terverifikasi hanya bila percakapan dibuka dari halaman
            // pesanan bertoken, atau oleh akun pemilik pesanan itu.
            $table->boolean('order_terverifikasi')->default(false);

            $table->string('nama', 100);
            $table->string('kontak', 100)->nullable();
            $table->string('topik', 20);                    // STOK|KUSTOM|KOMPLAIN|LAIN
            $table->string('status', 20)->default('TERBUKA'); // TERBUKA|SELESAI

            $table->unsignedSmallInteger('belum_dibaca_staf')->default(0);
            $table->unsignedSmallInteger('belum_dibaca_pelanggan')->default(0);

            $table->timestamp('pesan_terakhir_pada')->nullable();

            // Kapan pelanggan mulai menunggu jawaban, dan apakah staf sudah
            // diperingatkan. Dikosongkan begitu staf membalas.
            $table->timestamp('menunggu_sejak')->nullable();
            $table->timestamp('diperingatkan_pada')->nullable();

            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['status', 'pesan_terakhir_pada']);
            $table->index('menunggu_sejak');
        });

        Schema::create('pesan_cs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('percakapan_id')->constrained('percakapan_cs')->cascadeOnDelete();
            $table->string('pengirim', 20);                 // PELANGGAN|STAF|SISTEM
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('isi')->nullable();
            $table->string('lampiran_path')->nullable();    // disk privat
            $table->timestamp('created_at')->useCurrent();

            $table->index(['percakapan_id', 'id']);
        });

        // Klien: pertanyaan custom "tinggal dijawab sesuai template".
        Schema::create('template_balasan', function (Blueprint $table) {
            $table->id();
            $table->string('judul', 100);
            $table->text('isi');
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_balasan');
        Schema::dropIfExists('pesan_cs');
        Schema::dropIfExists('percakapan_cs');
    }
};
