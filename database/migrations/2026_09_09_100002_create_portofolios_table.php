<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pekerjaan yang pernah diselesaikan untuk instansi (KEP-024).
 *
 * Calon pembeli terbesar di sini adalah sekolah, dinas, dan klub, dan mereka
 * memilih berdasarkan bukti pernah mengerjakan yang serupa, bukan berdasarkan
 * daftar harga. Karena itu portofolio berdiri sendiri, tidak diturunkan dari
 * tabel orders: order yang layak dipamerkan belum tentu masih ada datanya, dan
 * nama pemesan tidak otomatis boleh disebut.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portofolios', function (Blueprint $table) {
            $table->id();
            $table->string('judul');
            $table->string('slug')->unique();

            // Nama instansi ditulis manual: hanya yang sudah mengizinkan.
            $table->string('instansi')->nullable();
            $table->string('kategori', 50)->nullable();   // Jersey bola, kaos seragam, dsb.
            $table->unsignedSmallInteger('tahun')->nullable();
            $table->unsignedInteger('jumlah_pcs')->nullable();

            $table->text('ringkasan')->nullable();

            $table->string('gambar_path')->nullable();
            $table->string('thumb_path')->nullable();

            $table->boolean('aktif')->default(true);
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->timestamps();

            $table->index(['aktif', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portofolios');
    }
};
