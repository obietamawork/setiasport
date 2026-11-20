<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Uji tekanan chatbot di produksi (KEP-153): "bs cod ga kak?" cocok dengan
 * kata kunci "cod" pada FAQ pengiriman, yang jawabannya dibuka dengan "Bisa.",
 * sehingga pelanggan mengira toko melayani bayar di tempat. Kata kunci itu
 * dibuang; pertanyaan COD kini dijawab AI dari data cara bayar, atau diteruskan
 * ke admin.
 *
 * Hanya mengubah kata kunci bawaan yang belum disunting staf di panel.
 */
return new class extends Migration
{
    private const PERTANYAAN = 'Apakah pesanan bisa dikirim?';

    private const LAMA = 'kirim, dikirim, pengiriman, ongkir, ongkos kirim, ekspedisi, kurir, diantar, luar kota, cod';

    private const BARU = 'kirim, dikirim, pengiriman, ongkir, ongkos kirim, ekspedisi, kurir, diantar, luar kota';

    public function up(): void
    {
        $this->ganti(self::LAMA, self::BARU);
    }

    public function down(): void
    {
        $this->ganti(self::BARU, self::LAMA);
    }

    private function ganti(string $dari, string $ke): void
    {
        DB::table('faq_bot')
            ->where('pertanyaan', self::PERTANYAAN)
            ->where('kata_kunci', $dari)
            ->update(['kata_kunci' => $ke, 'updated_at' => now()]);
    }
};
