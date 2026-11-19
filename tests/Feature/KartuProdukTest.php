<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kartu produk di katalog seperti gambar klien (KEP-132): label Ready Stock
 * atau Pre Order, "Mulai Rp.../pcs", "±7 hari kerja" berikon kalender, dan
 * tombol keranjang. Waktu pengerjaan produk kustom 10 hari menjadi 7.
 */
class KartuProdukTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);
    }

    private function bola(): ProductVariant
    {
        return ProductVariant::where('sku', 'BOLA-MITRE-01')->firstOrFail();
    }

    public function test_kartu_stok_satu_varian_bisa_langsung_masuk_keranjang(): void
    {
        $this->get(route('katalog', ['jalur' => 'stok']))
            ->assertOk()
            ->assertSee('Ready Stock')
            ->assertSee('Stok tersedia')
            ->assertSee('name="variant_id" value="'.$this->bola()->id.'"', false)
            ->assertSee('Tambah Bola Futsal Mitre Ultimatch ke keranjang');
    }

    public function test_kartu_kustom_pre_order_dengan_hari_kerja(): void
    {
        $halaman = $this->get(route('katalog', ['jalur' => 'kustom']))
            ->assertOk()
            ->assertSee('Pre Order')
            ->assertSee('Mulai')
            ->assertSee('±7 hari kerja')
            ->assertSee('Pesan Custom Celana Training Fullprint');

        // Kartu produk tidak lagi menyebut 10 hari (KEP-132). Satu-satunya
        // "7–10 hari kerja" adalah kalimat klien di kartu Custom Apparel
        // (KEP-137), yang sejak KEP-142 tetap tampil sebagai penyaring.
        $this->assertSame(1, substr_count((string) $halaman->getContent(), '10 hari kerja'));
    }

    public function test_stok_habis_tidak_menawarkan_tambah_keranjang(): void
    {
        $this->bola()->update(['stok' => 0, 'stok_dipesan' => 0]);

        $this->get(route('katalog', ['jalur' => 'stok', 'q' => 'Mitre']))
            ->assertOk()
            ->assertSee('Stok habis')
            ->assertDontSee('name="variant_id" value="'.$this->bola()->id.'"', false);
    }

    public function test_migrasi_mengubah_produk_kustom_10_hari_menjadi_7(): void
    {
        $training = Product::where('sku', 'CUST-TRAINING')->firstOrFail();
        $training->update(['lead_time_hari' => 10]);
        $lain = Product::where('sku', 'CUST-JRS-ATASAN')->firstOrFail();
        $lain->update(['lead_time_hari' => 14]);

        $migrasi = require database_path('migrations/2026_11_19_120000_waktu_pengerjaan_tujuh_hari.php');
        $migrasi->up();

        $this->assertSame(7, $training->fresh()->lead_time_hari);
        $this->assertSame(14, $lain->fresh()->lead_time_hari);
    }
}
