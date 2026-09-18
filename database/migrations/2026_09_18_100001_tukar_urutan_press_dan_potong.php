<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Press mendahului potong (KEP-037). Pekerjaan produksi yang sudah dibuat
 * sebelum keputusan ini masih memakai urutan lama, sehingga lembar kerja dan
 * daftar antrian menampilkannya terbalik.
 *
 * Yang ditukar hanya nomor urut. Status item tidak disentuh: item yang sedang
 * berada di satu meja tidak boleh berpindah meja karena migrasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->tukar();
    }

    public function down(): void
    {
        $this->tukar();
    }

    /**
     * Penukaran ini simetris: press selalu tepat sebelum atau sesudah potong,
     * jadi fungsi yang sama mengembalikannya ke keadaan semula.
     */
    private function tukar(): void
    {
        DB::table('production_jobs')
            ->whereIn('tahap', ['PRESS', 'POTONG'])
            ->update([
                'urutan' => DB::raw("CASE tahap WHEN 'PRESS' THEN urutan - 1 ELSE urutan + 1 END"),
            ]);
    }
};
