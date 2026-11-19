<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chatbot CS berlapis (KEP-151): FAQ yang dikelola di panel, catatan setiap
 * pertanyaan yang ditangani bot (untuk kurasi dan biaya AI), siapa yang
 * sedang menangani percakapan, dan tombol pilihan di pesan bot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faq_bot', function (Blueprint $table) {
            $table->id();
            $table->string('pertanyaan', 200);
            $table->text('jawaban');
            // Frasa pemicu dipisah koma, misalnya "minimal pesan, min order, moq".
            $table->text('kata_kunci')->nullable();
            $table->boolean('tampil_di_menu')->default(false);
            $table->boolean('aktif')->default(true);
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->unsignedInteger('dipakai')->default(0);
            $table->timestamps();
        });

        Schema::create('log_bot', function (Blueprint $table) {
            $table->id();
            $table->foreignId('percakapan_id')->nullable()->constrained('percakapan_cs')->cascadeOnDelete();
            $table->foreignId('pesan_id')->nullable()->constrained('pesan_cs')->nullOnDelete();
            $table->text('pertanyaan');
            // menu | faq | sistem | ai | admin
            $table->string('lapis', 10);
            $table->foreignId('faq_id')->nullable()->constrained('faq_bot')->nullOnDelete();
            $table->string('alasan', 200)->nullable();
            $table->text('jawaban')->nullable();
            $table->string('model', 100)->nullable();
            $table->unsignedInteger('token_masuk')->nullable();
            $table->unsignedInteger('token_keluar')->nullable();
            $table->decimal('biaya', 12, 8)->nullable();
            $table->unsignedInteger('durasi_ms')->nullable();
            $table->timestamp('dikurasi_pada')->nullable();
            $table->timestamps();

            $table->index(['lapis', 'dikurasi_pada']);
        });

        Schema::table('percakapan_cs', function (Blueprint $table) {
            // BOT selama asisten otomatis yang menjawab; ADMIN begitu diserahkan.
            // Percakapan lama tetap ADMIN.
            $table->string('ditangani', 10)->default('ADMIN')->after('status');
            $table->string('alasan_serah', 200)->nullable()->after('ditangani');
            $table->unsignedTinyInteger('gagal_bot')->default(0)->after('alasan_serah');
        });

        Schema::table('pesan_cs', function (Blueprint $table) {
            $table->json('pilihan')->nullable()->after('lampiran_path');
        });
    }

    public function down(): void
    {
        Schema::table('pesan_cs', function (Blueprint $table) {
            $table->dropColumn('pilihan');
        });

        Schema::table('percakapan_cs', function (Blueprint $table) {
            $table->dropColumn(['ditangani', 'alasan_serah', 'gagal_bot']);
        });

        Schema::dropIfExists('log_bot');
        Schema::dropIfExists('faq_bot');
    }
};
