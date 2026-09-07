<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gambar boleh datang dari dua sumber: diunggah ke hosting, atau ditunjuk ke
 * URL luar (CDN, Cloudflare Images, bucket, atau apa pun yang dipakai toko).
 *
 * Dua-duanya dibutuhkan. Unggah langsung membuat toko bisa jalan tanpa layanan
 * tambahan; URL luar menyelamatkan kuota disk shared hosting ketika katalognya
 * bertambah besar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->string('path')->nullable()->change();
            $table->string('external_url')->nullable()->after('path');
            $table->string('thumb_path')->nullable()->after('external_url');
            $table->unsignedInteger('lebar')->nullable()->after('thumb_path');
            $table->unsignedInteger('tinggi')->nullable()->after('lebar');
        });
    }

    public function down(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->dropColumn(['external_url', 'thumb_path', 'lebar', 'tinggi']);
        });
    }
};
