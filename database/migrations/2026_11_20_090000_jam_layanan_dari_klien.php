<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Jam operasional dengan penulisan klien (KEP-148): "setiap hari, 07.00–16.00
 * WIB" di halaman pembayaran. Teks ini hanya tampilan; jam yang dibaca
 * logika peringatan chat ada di cs_jam_mulai dan cs_jam_selesai.
 *
 * Hanya nilai bawaan lama yang diganti; teks yang sudah diubah pemilik di
 * panel tidak disentuh.
 */
return new class extends Migration
{
    private const LAMA = 'Setiap hari 07.00-16.00';

    private const BARU = 'Setiap hari, 07.00–16.00 WIB';

    public function up(): void
    {
        $this->tukar(self::LAMA, self::BARU);
    }

    public function down(): void
    {
        $this->tukar(self::BARU, self::LAMA);
    }

    private function tukar(string $dari, string $ke): void
    {
        $sekarang = DB::table('settings')->where('key', 'cs_jam_layanan')->value('value');

        if (is_string($sekarang) && trim($sekarang) === $dari) {
            DB::table('settings')->where('key', 'cs_jam_layanan')->update(['value' => $ke]);
        }

        Cache::forget('setting.semua');
    }
};
