<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menutup pengambilalihan akun lewat pendaftaran (KEP-021).
 *
 * Tanpa pembuktian penguasaan email, siapa pun yang tahu alamat email seorang
 * pelanggan dapat mendaftar memakai alamat itu dan langsung mewarisi seluruh
 * riwayat pesanannya, termasuk kode ambil. Email pelanggan bukan rahasia, jadi
 * kepemilikannya harus dibuktikan sebelum akun bisa dipakai.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->timestamp('email_terverifikasi_pada')->nullable()->after('password');
            $table->string('token_verifikasi', 64)->nullable()->after('email_terverifikasi_pada');
            $table->timestamp('token_dibuat_pada')->nullable()->after('token_verifikasi');

            $table->index('token_verifikasi');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['token_verifikasi']);
            $table->dropColumn(['email_terverifikasi_pada', 'token_verifikasi', 'token_dibuat_pada']);
        });
    }
};
