<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Menjaga akses panel: setiap peran hanya melihat yang menjadi tanggung jawabnya. */
class PanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);
    }

    private function sebagai(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    public function test_tamu_diarahkan_ke_halaman_masuk(): void
    {
        $this->get('/panel')->assertRedirect('/panel/login');
        $this->get('/panel/produk')->assertRedirect('/panel/login');
    }

    public function test_kata_sandi_salah_ditolak_dengan_pesan_yang_jelas(): void
    {
        $this->post('/panel/login', ['email' => 'owner@setiasport.test', 'password' => 'salah'])
            ->assertSessionHasErrors(['email' => 'Email atau kata sandi tidak cocok.']);

        $this->assertGuest();
    }

    public function test_akun_nonaktif_tidak_bisa_masuk(): void
    {
        $this->sebagai('gudang@setiasport.test')->update(['aktif' => false]);

        $this->post('/panel/login', ['email' => 'gudang@setiasport.test', 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_owner_bisa_membuka_seluruh_halaman_panel(): void
    {
        $this->actingAs($this->sebagai('owner@setiasport.test'));

        foreach (['/panel', '/panel/produk', '/panel/kategori', '/panel/stok', '/panel/pengaturan'] as $url) {
            $this->get($url)->assertOk();
        }

        $produk = Product::where('sku', 'JRS-FUTSAL-FP')->firstOrFail();
        $this->get(route('panel.produk.edit', $produk))->assertOk();
        $this->get(route('panel.kategori.create'))->assertOk();
    }

    public function test_desainer_tidak_bisa_menyentuh_katalog(): void
    {
        $this->actingAs($this->sebagai('desainer@setiasport.test'));

        $this->get('/panel')->assertOk();
        $this->get('/panel/produk')->assertForbidden();
        $this->get('/panel/stok')->assertForbidden();
        $this->get('/panel/pengaturan')->assertForbidden();
    }

    public function test_gudang_boleh_mengelola_stok_tapi_bukan_produk(): void
    {
        $this->actingAs($this->sebagai('gudang@setiasport.test'));

        $this->get('/panel/stok')->assertOk();
        $this->get('/panel/produk')->assertForbidden();
    }

    public function test_produk_kustom_wajib_punya_moq_dan_waktu_pengerjaan(): void
    {
        $this->actingAs($this->sebagai('owner@setiasport.test'));

        $this->post('/panel/produk', [
            'category_id' => Category::whereNotNull('parent_id')->value('id'),
            'sku' => 'UJI-KUSTOM-01',
            'nama' => 'Uji Kustom',
            'fulfillment_type' => 'PREORDER',
        ])->assertSessionHasErrors(['moq', 'lead_time_hari']);
    }

    public function test_tier_harga_yang_tumpang_tindih_ditolak(): void
    {
        $this->actingAs($this->sebagai('owner@setiasport.test'));
        $produk = Product::where('sku', 'JRS-FUTSAL-FP')->firstOrFail();

        // Tier 12-23 sudah ada; 20-30 akan membuat jumlah 20 cocok dengan dua tier.
        $this->post(route('panel.produk.tier.simpan', $produk), [
            'min_qty' => 20, 'max_qty' => 30, 'harga_satuan' => 90_000,
        ])->assertSessionHasErrors();

        $this->assertSame(3, $produk->priceTiers()->count());
    }

    public function test_penyesuaian_stok_wajib_menyertakan_alasan(): void
    {
        $this->actingAs($this->sebagai('gudang@setiasport.test'));
        $varian = ProductVariant::where('sku', 'BOLA-MITRE-01')->firstOrFail();

        $this->put('/panel/stok', ['stok' => [$varian->id => 5]])
            ->assertSessionHasErrors('alasan');

        $this->assertSame(12, $varian->fresh()->stok);
    }

    public function test_penyesuaian_stok_tercatat_di_log_aktivitas(): void
    {
        $gudang = $this->sebagai('gudang@setiasport.test');
        $this->actingAs($gudang);

        $varian = ProductVariant::where('sku', 'BOLA-MITRE-01')->firstOrFail();

        $this->put('/panel/stok', [
            'stok' => [$varian->id => 5],
            'alasan' => 'Opname 7 September',
        ])->assertSessionHas('sukses');

        $this->assertSame(5, $varian->fresh()->stok);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $gudang->id,
            'aksi' => 'stok.disesuaikan',
        ]);
    }

    public function test_pengaturan_dp_bisa_diubah_dan_langsung_berlaku(): void
    {
        $this->actingAs($this->sebagai('owner@setiasport.test'));

        $this->put('/panel/pengaturan', ['setting' => ['dp_minimum_persen' => 40]])
            ->assertSessionHas('sukses');

        $this->assertSame(40, Setting::ambil('dp_minimum_persen'));
    }

    public function test_kategori_yang_masih_dipakai_tidak_bisa_dihapus(): void
    {
        $this->actingAs($this->sebagai('owner@setiasport.test'));
        $kategori = Category::where('slug', 'jersey-futsal')->firstOrFail();

        $this->delete(route('panel.kategori.destroy', $kategori))->assertSessionHasErrors();

        $this->assertDatabaseHas('categories', ['id' => $kategori->id]);
    }
}
