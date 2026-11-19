<?php

namespace Tests\Feature;

use App\Models\Setting;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Revisi teks dari klien (KEP-137): halaman depan, katalog, dan kaki
 * halaman. Teks yang tersimpan sebagai pengaturan (tentang usaha, alamat
 * konveksi) diganti lewat migrasi hanya bila pemilik belum mengubahnya.
 */
class TeksKlienTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);
    }

    public function test_halaman_depan_memakai_teks_klien(): void
    {
        $this->get(route('beranda'))
            ->assertOk()
            ->assertSee('Seluruh proses produksi dikerjakan langsung di konveksi kami tanpa melalui pihak ketiga.')
            ->assertSee('Harga Transparan ditampilkan di website tanpa perlu menghubungi admin')
            ->assertSee('Gratis 3 Kali Revisi desain tanpa biaya tambahan')
            ->assertSeeInOrder(['Pilih Produk &amp; Ukuran', 'Pembayaran Uang Muka (DP)', 'Proses Desain', 'Pesanan diproduksi dalam waktu 7–10 hari kerja'], false)
            ->assertSee('Kenapa Belanja di Setia Sport?')
            ->assertSeeInOrder(['Produk Olahraga Lengkap', 'Konveksi Sendiri', 'Toko Fisik Terpercaya', 'Pesanan Mudah Dipantau'])
            ->assertSee('Pantau status pesanan, mulai dari proses pengerjaan hingga pengiriman, melalui website kami.')
            ->assertSeeInOrder(['Toko Olahraga &amp; Lokasi Pengambilan Pesanan', 'Lokasi produksi jersey &amp; konveksi Setia Sport'], false)
            ->assertSee('Belanja perlengkapan olahraga dan pesan jersey custom langsung melalui website')
            ->assertDontSee('Kenapa belanja di Setia Sport?');
    }

    public function test_katalog_memakai_teks_klien(): void
    {
        $this->get(route('katalog'))
            ->assertOk()
            ->assertSee('Produk tersedia dan siap dibeli. Pesanan dapat dikirim atau diambil langsung di toko.')
            ->assertSee('Custom Apparel (Pre-Order)')
            ->assertSee('Estimasi produksi 7–10 hari kerja setelah desain disetujui')
            ->assertDontSee('Ready Stock (Siap Kirim)');
    }

    public function test_kaki_halaman_memakai_teks_klien_tanpa_baris_jam_balas(): void
    {
        $this->get(route('katalog'))
            ->assertSee('Menyediakan berbagai perlengkapan olahraga dan layanan konveksi jersey custom. Pesanan dapat diambil langsung di toko atau dikirim ke alamat tujuan.')
            ->assertDontSee('dibalas setiap hari');
    }

    public function test_migrasi_mengganti_teks_bawaan_yang_belum_diubah(): void
    {
        $lama = 'Donoharjo, RT.02/RW.01, Donoharjo, Wuryorejo, Kec. Wonogiri, Kabupaten Wonogiri, Jawa Tengah 57614';
        DB::table('settings')->where('key', 'konveksi_alamat')->update(['value' => $lama]);
        DB::table('settings')->where('key', 'tentang_lengkap')->update(['value' => 'Teks yang sudah diubah pemilik sendiri.']);

        (require database_path('migrations/2026_11_19_130000_teks_profil_dari_klien.php'))->up();

        $this->assertSame('Donoharjo, RT.02/RW.01, Wuryorejo, Kec. Wonogiri, Kabupaten Wonogiri, Jawa Tengah 57614', Setting::ambil('konveksi_alamat'));
        $this->assertSame('Teks yang sudah diubah pemilik sendiri.', Setting::ambil('tentang_lengkap'));
    }

    public function test_migrasi_mengganti_teks_tentang_bawaan_lama(): void
    {
        $migrasi = require database_path('migrations/2026_11_19_130000_teks_profil_dari_klien.php');
        [$lama, $baru] = (fn () => $this->ganti())->call($migrasi)['tentang_lengkap'];
        // Disimpan lewat formulir Pengaturan: peramban mengirim baris baru CRLF.
        DB::table('settings')->where('key', 'tentang_lengkap')->update(['value' => str_replace("\n", "\r\n", $lama)]);

        $migrasi->up();

        $this->assertSame($baru, Setting::ambil('tentang_lengkap'));
        $this->assertStringContainsString('lebih dari 15 tahun', $baru);
    }

    public function test_seeder_memakai_teks_tentang_dari_klien(): void
    {
        $this->assertStringStartsWith('Setia Sport Wonogiri adalah toko perlengkapan olahraga yang telah hadir selama lebih dari 15 tahun', (string) Setting::ambil('tentang_lengkap'));
        $this->assertSame(1, substr_count((string) Setting::ambil('konveksi_alamat'), 'Donoharjo'));
    }
}
