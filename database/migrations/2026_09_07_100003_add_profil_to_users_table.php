<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** users = pengguna INTERNAL saja. Pelanggan tidak punya akun (KEP-008). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('no_hp', 20)->nullable()->after('email');
            $table->boolean('aktif')->default(true)->after('no_hp');
            $table->timestamp('login_terakhir_pada')->nullable()->after('aktif');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['no_hp', 'aktif', 'login_terakhir_pada']);
        });
    }
};
