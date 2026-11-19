<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Klien meresmikan Customer Service (KEP-149), menggantikan pembatalan
 * sementara di KEP-034. Saklar "Tampilkan Chat CS" dinyalakan sekali saat
 * deploy; sesudahnya tetap bisa dimatikan di Pengaturan.
 *
 * CS baru benar-benar tampil bila fiturnya juga dipasang lewat FITUR_CS=true
 * di .env server (KEP-036, docs/12-deploy.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->simpan('1');
    }

    public function down(): void
    {
        $this->simpan('0');
    }

    private function simpan(string $nilai): void
    {
        DB::table('settings')->where('key', 'cs_aktif')->update(['value' => $nilai]);

        Cache::forget('setting.semua');
    }
};
