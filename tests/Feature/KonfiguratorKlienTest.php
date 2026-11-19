<?php

namespace Tests\Feature;

use App\Models\Product;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Revisi klien untuk halaman pemesanan kustom (KEP-138): tanpa rekomendasi
 * produk lain, penjelasan tiap pilihan desain, dan kalimat yang diganti.
 */
class KonfiguratorKlienTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);
    }

    private function halaman(): TestResponse
    {
        return $this->get(route('kustom.buat', Product::where('sku', 'CUST-SETELAN')->firstOrFail()))->assertOk();
    }

    public function test_tidak_ada_rekomendasi_produk_lain(): void
    {
        $this->halaman()->assertDontSee('Mau memesan yang lain?');
    }

    public function test_tiap_pilihan_desain_punya_penjelasan(): void
    {
        $this->halaman()
            ->assertSeeInOrder(['Pilih dari katalog', 'Kirim desain sendiri', 'Dibantu admin'])
            ->assertSee('Pakai desain dari galeri kami.')
            ->assertSee('Sudah punya desain jadi?')
            ->assertSee('Belum punya desain?')
            ->assertSee(route('portofolio'));
    }

    public function test_kalimat_dari_klien(): void
    {
        $this->halaman()
            ->assertSee('Mockup desain akan ditampilkan pada halaman pesanan untuk diperiksa dan disetujui sebelum proses produksi dimulai.')
            ->assertSee('Jumlah pesanan mengikuti daftar yang diisi. Nama dan nomor punggung dapat dilengkapi kemudian, sebelum desain disetujui.')
            ->assertSee('Pembayaran DP sebesar 50%')
            ->assertSee('Sisa pembayaran dilunasi sebelum pesanan diambil.')
            ->assertSee('Masih ragu menentukan bahan atau ukuran? Konsultasikan dengan admin, lalu lanjutkan pemesanan melalui website.');
    }
}
