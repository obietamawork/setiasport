<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductSizeChart;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\PanduanUkuranSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Panduan ukuran di langkah Data Pemain berupa tab per kategori (KEP-146):
 * tiga tabel bertumpuk membuat langkah itu terlalu penuh.
 */
class PanduanUkuranTabTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class, PanduanUkuranSeeder::class]);
    }

    private function halaman(string $sku): TestResponse
    {
        return $this->get(route('kustom.buat', Product::where('sku', $sku)->firstOrFail()))->assertOk();
    }

    public function test_panduan_berkategori_tampil_sebagai_tab(): void
    {
        $this->halaman('CUST-JRS-ATASAN')
            ->assertSee('aria-label="Kategori panduan ukuran"', false)
            ->assertSeeInOrder(['role="tab"', 'SD', 'role="tab"', 'SMP', 'role="tab"', 'SMA / Dewasa'], false)
            ->assertSee('role="tabpanel"', false)
            // Satuan di tiap sel, seperti contoh klien.
            ->assertSee('32 cm');
    }

    public function test_panduan_memakai_catatan_klien(): void
    {
        $this->halaman('CUST-JRS-ATASAN')
            ->assertSeeInOrder([
                'Catatan',
                'Toleransi ukuran ±1–2 cm dapat terjadi selama proses produksi.',
                'Untuk hasil yang lebih sesuai, bandingkan ukuran pada tabel dengan jersey yang biasa Anda gunakan.',
            ])
            ->assertDontSee('Ukuran bisa berbeda 1-2 cm')
            ->assertDontSee('Ragu ukurannya?');
    }

    public function test_panduan_tanpa_kategori_tidak_memakai_tab(): void
    {
        // Celana tidak berharga per kategori; sisakan hanya panduan umumnya.
        ProductSizeChart::where('product_id', Product::where('sku', 'CUST-CELANA-PENDEK')->value('id'))
            ->whereNotNull('kategori')->delete();

        $this->halaman('CUST-CELANA-PENDEK')
            ->assertDontSee('aria-label="Kategori panduan ukuran"', false)
            ->assertSee('35 cm');
    }
}
