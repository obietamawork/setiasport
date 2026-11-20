<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Akun pelanggan lengkap (KEP-157): status aktif yang bisa diubah staf, token
 * atur ulang kata sandi, dan buku alamat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('aktif')->default(true)->after('password');
            // Hash SHA-256 dari token di tautan email; tokennya sendiri tidak disimpan.
            $table->string('token_atur_sandi', 64)->nullable()->unique()->after('token_dibuat_pada');
            $table->timestamp('token_atur_sandi_dibuat_pada')->nullable()->after('token_atur_sandi');
        });

        Schema::create('alamat_pelanggan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('label', 40);
            $table->string('alamat', 500);
            // Area Biteship (KEP-057): disimpan supaya tarif langsung dihitung di checkout.
            $table->string('area_kode', 60)->nullable();
            $table->string('area_nama', 150)->nullable();
            $table->string('kode_pos', 10)->nullable();
            $table->string('tautan_lokasi', 500)->nullable();
            $table->boolean('utama')->default(false);
            $table->timestamps();
            $table->index(['customer_id', 'utama']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alamat_pelanggan');

        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['token_atur_sandi']);
            $table->dropColumn(['aktif', 'token_atur_sandi', 'token_atur_sandi_dibuat_pada']);
        });
    }
};
