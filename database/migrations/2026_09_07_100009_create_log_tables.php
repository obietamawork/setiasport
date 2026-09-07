<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('dari', 30)->nullable();
            $table->string('ke', 30);
            $table->foreignId('oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->string('catatan')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['order_id', 'created_at']);
        });

        // Alat penyelesaian perselisihan internal (docs/04): siapa mengubah apa, kapan.
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('aksi', 60);
            $table->string('subjek_tipe', 60)->nullable();
            $table->unsignedBigInteger('subjek_id')->nullable();
            $table->json('data_json')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['subjek_tipe', 'subjek_id']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('ke');
            $table->string('template', 60);
            $table->string('subjek');
            $table->string('resend_id')->nullable();
            $table->string('status', 20)->default('TERKIRIM');
            $table->string('error')->nullable();
            $table->timestamp('dikirim_pada')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'template']);
            $table->index('status');
            $table->index('resend_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('order_status_logs');
    }
};
