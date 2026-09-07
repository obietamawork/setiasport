<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Akun pelanggan bersifat TAMBAHAN, bukan pengganti (KEP-020).
 *
 * Tautan bertoken di email tetap jalur utama dan tidak pernah dimatikan:
 * memaksa pendaftaran di checkout terbukti menurunkan konversi, dan tautan
 * yang bisa dibagikan ke grup tim adalah fitur, bukan kekurangan (KEP-008).
 * Akun ditawarkan bagi pelanggan berulang yang ingin melihat semua pesanannya
 * dalam satu tempat tanpa mencari-cari email lama.
 *
 * Pelanggan disimpan di tabelnya sendiri, bukan digabung dengan `users`.
 * Penjaga sesi yang terpisah membuat pelanggan mustahil ikut terbawa ke panel
 * internal karena salah konfigurasi peran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('password')->nullable()->after('no_hp');
            $table->timestamp('akun_dibuat_pada')->nullable()->after('password');
            $table->timestamp('login_terakhir_pada')->nullable()->after('akun_dibuat_pada');
            $table->rememberToken();

            // Email adalah kunci pelanggan di seluruh sistem: checkout tamu
            // mencocokkan dengannya, dan pendaftaran mengandalkannya.
            $table->unique('email');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->dropColumn(['password', 'akun_dibuat_pada', 'login_terakhir_pada', 'remember_token']);
        });
    }
};
