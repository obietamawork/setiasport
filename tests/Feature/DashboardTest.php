<?php

namespace Tests\Feature;

use App\Enums\FulfillmentType;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductPrice;
use App\Models\ProductPriceTier;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dashboard sebagai daftar tindakan (KEP-075).
 *
 * Yang dijaga: kartu "Harga belum lengkap" hanya menyebut produk kustom yang
 * BENAR-BENAR belum bisa dihitung harganya pelanggan -- bukan produk yang
 * sudah berharga lewat matriks kategori/bahan (KEP-039) tapi belum punya tier
 * per qty, karena produk kustom cuma butuh salah satu cara, bukan keduanya.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class]);
    }

    private function owner(): User
    {
        return User::where('email', 'owner@setiasport.test')->firstOrFail();
    }

    private function produkKustom(string $sku): Product
    {
        $kategori = Category::create(['nama' => 'Jersey', 'slug' => 'jersey-'.$sku, 'urutan' => 0]);

        return Product::create([
            'category_id' => $kategori->id,
            'sku' => $sku,
            'nama' => 'Produk uji '.$sku,
            'slug' => 'produk-uji-'.strtolower($sku),
            'fulfillment_type' => FulfillmentType::PREORDER,
            'harga_dasar' => 0,
            'moq' => 1,
            'lead_time_hari' => 7,
            'aktif' => true,
        ]);
    }

    /** Dihargai lewat matriks kategori/bahan, bukan tier -- harusnya tidak ditagih. */
    public function test_produk_berharga_lewat_matriks_tidak_dianggap_belum_berharga(): void
    {
        $produk = $this->produkKustom('CUST-UJI-MATRIKS');

        $opsi = ProductOption::create([
            'product_id' => $produk->id, 'nama' => 'Bahan', 'wajib' => true, 'urutan' => 0,
        ]);
        $nilai = $opsi->values()->create([
            'label' => 'Serena', 'tambahan_harga' => 0, 'basis' => 'PER_PCS', 'urutan' => 0, 'aktif' => true,
        ]);
        ProductPrice::create([
            'product_id' => $produk->id, 'kategori' => null, 'option_value_id' => $nilai->id, 'harga_satuan' => 65_000,
        ]);

        $response = $this->actingAs($this->owner())->get(route('panel.dashboard'))->assertOk();

        $this->assertTrue($response->viewData('tanpaTier')->doesntContain('id', $produk->id));
    }

    /** Dihargai lewat tier qty -- harusnya juga tidak ditagih (jalur yang sudah benar sebelumnya). */
    public function test_produk_berharga_lewat_tier_tidak_dianggap_belum_berharga(): void
    {
        $produk = $this->produkKustom('CUST-UJI-TIER');
        ProductPriceTier::create(['product_id' => $produk->id, 'min_qty' => 1, 'max_qty' => null, 'harga_satuan' => 50_000]);

        $response = $this->actingAs($this->owner())->get(route('panel.dashboard'))->assertOk();

        $this->assertTrue($response->viewData('tanpaTier')->doesntContain('id', $produk->id));
    }

    /** Benar-benar tanpa harga apa pun -- harus tetap ditagih. */
    public function test_produk_tanpa_harga_sama_sekali_tetap_ditagih(): void
    {
        $produk = $this->produkKustom('CUST-UJI-KOSONG');

        $response = $this->actingAs($this->owner())->get(route('panel.dashboard'))->assertOk();

        $this->assertTrue($response->viewData('tanpaTier')->contains('id', $produk->id));
    }
}
