<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Teks profil dari klien (KEP-137): bagian "Tentang Setia Sport" di halaman
 * depan, dan alamat konveksi yang menulis "Donoharjo" dua kali.
 *
 * Keduanya pengaturan yang bisa diubah pemilik di panel. Hanya nilai bawaan
 * lama yang diganti; teks yang sudah diubah pemilik tidak disentuh.
 */
return new class extends Migration
{
    /** @return array<string, array{0: string, 1: string}> */
    private function ganti(): array
    {
        return [
            'tentang_lengkap' => [
                implode("\n\n", [
                    'Setia Sport berdiri di Wonogiri sebagai toko perlengkapan olahraga, lalu tumbuh menjadi konveksi jersey karena permintaannya datang terus dari pelanggan sendiri: sekolah yang butuh seragam olahraga, klub futsal yang ingin jersey dengan nama dan nomor punggung, dan komunitas lari yang memesan kaos untuk acara.',
                    'Jersey dikerjakan di workshop kami sendiri, bukan dilempar ke pihak lain. Itu yang membuat revisi desain bisa cepat, ukuran bisa dicampur dari anak SD sampai dewasa dalam satu pesanan, dan kalau ada yang kurang pas, kami perbaiki tanpa harus menunggu kiriman dari kota lain.',
                    'Barang yang ada di rak bisa diambil hari itu juga di toko. Pesanan konveksi punya halaman sendiri di website ini: progres pengerjaan, tagihan, dan nomor resi bisa dilihat kapan saja tanpa perlu menelepon toko.',
                ]),
                implode("\n\n", [
                    'Setia Sport Wonogiri adalah toko perlengkapan olahraga yang telah hadir selama lebih dari 15 tahun untuk memenuhi berbagai kebutuhan olahraga masyarakat. Kami menyediakan beragam produk, mulai dari sepatu, pakaian olahraga, peralatan, hingga aksesori untuk berbagai cabang olahraga.',
                    'Seiring berkembangnya usaha, Setia Sport juga menghadirkan layanan konveksi yang melayani pembuatan jersey custom, seragam tim, dan berbagai kebutuhan apparel olahraga. Seluruh proses produksi dilakukan di konveksi kami sendiri, dengan pilihan desain, bahan, warna, dan ukuran yang dapat disesuaikan dengan kebutuhan pelanggan.',
                    'Berlokasi di pusat Kota Wonogiri, Setia Sport melayani pembelian secara langsung di toko maupun pemesanan secara online. Kami terus berupaya memberikan pelayanan terbaik melalui produk yang lengkap, harga yang bersaing, serta kemudahan berbelanja bagi pelanggan dari berbagai daerah.',
                ]),
            ],
            'konveksi_alamat' => [
                'Donoharjo, RT.02/RW.01, Donoharjo, Wuryorejo, Kec. Wonogiri, Kabupaten Wonogiri, Jawa Tengah 57614',
                'Donoharjo, RT.02/RW.01, Wuryorejo, Kec. Wonogiri, Kabupaten Wonogiri, Jawa Tengah 57614',
            ],
        ];
    }

    public function up(): void
    {
        $this->tukar(fn (array $pasangan) => $pasangan);
    }

    public function down(): void
    {
        $this->tukar(fn (array $pasangan) => array_reverse($pasangan));
    }

    /**
     * Teks yang pernah disimpan lewat formulir Pengaturan memakai baris baru
     * CRLF dari peramban, jadi perbandingannya setelah baris barunya
     * disamakan. Selain itu harus persis sama: suntingan pemilik tidak
     * pernah tertimpa.
     *
     * @param  callable(array{0: string, 1: string}): array{0: string, 1: string}  $arah
     */
    private function tukar(callable $arah): void
    {
        foreach ($this->ganti() as $kunci => $pasangan) {
            [$dari, $ke] = $arah($pasangan);
            $sekarang = DB::table('settings')->where('key', $kunci)->value('value');

            if (is_string($sekarang) && trim(str_replace("\r\n", "\n", $sekarang)) === $dari) {
                DB::table('settings')->where('key', $kunci)->update(['value' => $ke]);
            }
        }

        Cache::forget('setting.semua');
    }
};
