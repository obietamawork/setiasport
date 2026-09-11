<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Toko memutuskan tidak memakai payment gateway (KEP-023).
 *
 * Pembayaran diterima lewat QRIS statis dan transfer bank, buktinya dikirim
 * pelanggan lewat WhatsApp, dan admin yang memverifikasi di panel. Kolom yang
 * hanya bermakna bagi gateway ikut dibuang supaya skema tetap jujur: kolom mati
 * membuat orang berikutnya menebak-nebak apakah masih terpakai.
 *
 * Kalau kelak toko berubah pikiran, mengembalikannya cuma satu migrasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Metode GATEWAY tidak lagi ada; QRIS_EDC diringkas jadi QRIS karena
        // sekarang QRIS statis dipakai untuk transfer, bukan hanya EDC di toko.
        DB::table('payments')->where('metode', 'GATEWAY')->update(['metode' => 'TRANSFER']);
        DB::table('payments')->where('metode', 'QRIS_EDC')->update(['metode' => 'QRIS']);

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['provider_ref']);
            $table->dropColumn(['provider', 'provider_ref', 'payload_json']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('provider', 30)->nullable()->after('bukti_path');
            $table->string('provider_ref')->nullable()->after('provider');
            $table->json('payload_json')->nullable()->after('provider_ref');

            $table->index('provider_ref');
        });
    }
};
