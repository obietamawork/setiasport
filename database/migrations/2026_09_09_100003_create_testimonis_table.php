<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Testimoni pelanggan, diketik ulang oleh admin (KEP-024).
 *
 * Sengaja tidak ada formulir publik: tanpa moderasi ia jadi sasaran spam, dan
 * moderasi berarti antrian kerja yang tidak dibayar. Yang tampil di web adalah
 * kutipan yang memang diizinkan pemiliknya, dimasukkan lewat panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('testimonis', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('peran')->nullable();          // "Pelatih SSB Garuda", dsb.
            $table->text('isi');
            $table->unsignedTinyInteger('bintang')->nullable();
            $table->boolean('aktif')->default(true);
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->timestamps();

            $table->index(['aktif', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonis');
    }
};
