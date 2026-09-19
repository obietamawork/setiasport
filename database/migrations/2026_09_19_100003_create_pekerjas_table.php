<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pekerja konveksi dan penanda pekerjaannya (KEP-047).
 *
 * Pekerja SENGAJA bukan pengguna panel. Mereka tidak pernah membuka pesanan,
 * harga, atau data pelanggan; yang mereka lakukan hanya menandai tahap yang
 * baru saja selesai dikerjakan. Memberi mereka akun panel berarti memberi
 * delapan orang akses ke seluruh isi toko demi satu tombol.
 *
 * PIN disimpan sebagai hash HMAC, bukan bcrypt: nomor empat angka dicari dari
 * hasil hash-nya saat pekerja menekan tombol, dan mencocokkannya satu per satu
 * ke seluruh pekerja akan terasa lambat di HP. Yang menjaga pintunya bukan PIN
 * ini, melainkan token panjang di tautan lembar kerja; PIN hanya memastikan
 * yang menandai memang orang yang mengerjakan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pekerjas', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('pin_hash', 64)->unique();

            // Divisi yang boleh ditandai orang ini. Satu orang boleh memegang
            // lebih dari satu meja, seperti yang terjadi di konveksi kecil.
            $table->json('tahap');

            $table->boolean('aktif')->default(true);
            $table->string('catatan', 200)->nullable();
            $table->timestamps();
        });

        Schema::table('production_jobs', function (Blueprint $table) {
            $table->foreignId('pekerja_id')->nullable()->after('diselesaikan_oleh')
                ->constrained('pekerjas')->nullOnDelete();
        });

        Schema::table('order_items', function (Blueprint $table) {
            // Kunci lembar kerja. Panjang dan acak, karena tautannya beredar di
            // grup WhatsApp dan siapa pun yang memegangnya bisa membuka halaman
            // kerjanya (walau tetap butuh PIN untuk menandai selesai).
            $table->string('kerja_token', 64)->nullable()->unique()->after('status_item');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('kerja_token');
        });

        Schema::table('production_jobs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pekerja_id');
        });

        Schema::dropIfExists('pekerjas');
    }
};
