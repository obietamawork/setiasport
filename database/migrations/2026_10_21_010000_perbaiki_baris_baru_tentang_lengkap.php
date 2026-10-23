<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/*
 * Draf "tentang_lengkap" dari SettingSeeder dulu menulis pemisah paragrafnya di
 * dalam kutip tunggal PHP, sehingga yang tersimpan adalah garis miring + "n"
 * apa adanya, bukan baris baru. Halaman depan menampilkannya sebagai "\n\n"
 * di tengah kalimat.
 *
 * Hanya nilai yang masih memuat urutan itu yang diubah. Teks yang sudah
 * diketik ulang pemilik toko di Pengaturan selalu memakai baris baru sungguhan,
 * jadi tidak ikut tersentuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        $nilai = DB::table('settings')->where('key', 'tentang_lengkap')->value('value');

        if ($nilai === null || ! str_contains($nilai, '\n')) {
            return;
        }

        DB::table('settings')->where('key', 'tentang_lengkap')
            ->update(['value' => str_replace(['\r\n', '\n'], "\n", $nilai)]);

        Cache::forget('setting.tentang_lengkap');
    }

    public function down(): void
    {
        // Sengaja tidak dikembalikan: yang diperbaiki adalah data rusak.
    }
};
