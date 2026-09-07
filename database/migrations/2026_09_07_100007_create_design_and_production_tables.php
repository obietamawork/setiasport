<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->string('tipe', 30);                  // UPLOAD_PELANGGAN | MOCKUP
            $table->unsignedSmallInteger('versi');

            // Hanya preview yang benar-benar tersimpan di hosting, maks 10 MB.
            // File mentah (AI/PSD/CDR) ditukar lewat Drive/WA; sistem hanya
            // menyimpan tautannya (KEP-002).
            $table->string('path')->nullable();
            $table->string('external_url')->nullable();

            $table->text('catatan')->nullable();
            $table->foreignId('diunggah_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['order_item_id', 'tipe', 'versi']);
        });

        // FK ditunda sampai design_assets ada — relasinya melingkar.
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreign('approved_design_asset_id')
                ->references('id')->on('design_assets')
                ->nullOnDelete();
        });

        Schema::create('production_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->string('tahap', 20);                 // DESAIN|PRINT|JAHIT|QC|PACKING
            $table->unsignedSmallInteger('urutan');
            $table->string('status', 20)->default('ANTRI');

            $table->foreignId('ditugaskan_ke')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('diselesaikan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dimulai_pada')->nullable();
            $table->timestamp('selesai_pada')->nullable();

            $table->text('catatan')->nullable();
            $table->string('foto_path')->nullable();     // foto progres, opsional
            $table->timestamps();

            $table->unique(['order_item_id', 'tahap']);

            // Indeks utama layar antrian per divisi: "pekerjaan tahap X yang antri".
            $table->index(['tahap', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_jobs');

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['approved_design_asset_id']);
        });

        Schema::dropIfExists('design_assets');
    }
};
