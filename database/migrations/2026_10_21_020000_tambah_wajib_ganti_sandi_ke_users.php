<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Akun yang dibuat atau kata sandinya diatur ulang oleh Pemilik memakai sandi
 * sementara yang ikut diketahui Pemilik. Kolom ini memaksa pemilik akunnya
 * mengganti sendiri pada kesempatan masuk pertama (KEP-084).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('wajib_ganti_sandi')->default(false)->after('aktif');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('wajib_ganti_sandi');
        });
    }
};
