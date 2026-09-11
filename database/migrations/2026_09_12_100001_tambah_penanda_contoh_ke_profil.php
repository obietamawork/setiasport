<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda data contoh di portofolio dan testimoni (KEP-035).
 *
 * Klien ingin melihat seperti apa halaman depan yang sudah terisi sebelum punya
 * isinya sendiri. Testimoni dan portofolio karangan yang ikut tayang di website
 * asli sama dengan ulasan palsu, jadi setiap baris contoh ditandai: panel
 * menampilkannya, dashboard memperingatkannya, dan semuanya bisa dihapus sekali
 * klik tanpa menyentuh isian klien yang asli.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['portofolios', 'testimonis'] as $tabel) {
            Schema::table($tabel, function (Blueprint $table) {
                $table->boolean('contoh')->default(false)->after('aktif');
            });
        }
    }

    public function down(): void
    {
        foreach (['portofolios', 'testimonis'] as $tabel) {
            Schema::table($tabel, function (Blueprint $table) {
                $table->dropColumn('contoh');
            });
        }
    }
};
