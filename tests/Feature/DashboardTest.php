<?php

namespace Tests\Feature;

use App\Enums\FulfillmentType;
use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductPrice;
use App\Models\ProductPriceTier;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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

    // ------------------------------------------- tata letak revisi klien (KEP-112)

    private function pesanan(string $status, array $statusItem, array $ubah = []): Order
    {
        $produk = Product::first() ?? $this->produkKustom('CUST-UJI-DASH');

        $order = Order::create(array_merge([
            'kode' => Order::kodeBaru(),
            'customer_id' => Customer::create(['nama' => 'Tim Uji', 'email' => Str::random(6).'@contoh.test', 'no_hp' => '0812'.random_int(1000000, 9999999)])->id,
            'status' => $status,
            'access_token' => Order::tokenBaru(),
            'tipe_pembayaran' => 'LUNAS',
        ], $ubah));

        foreach ($statusItem as $s) {
            $order->items()->create([
                'product_id' => $produk->id, 'nama_produk' => 'Jersey Voli', 'fulfillment_type' => 'PREORDER',
                'qty' => 12, 'harga_satuan' => 30_000, 'status_item' => $s,
            ]);
        }

        return $order;
    }

    public function test_dashboard_menyapa_pemakainya_dan_menyebut_tanggal_hari_ini(): void
    {
        $this->actingAs($this->owner())->get(route('panel.dashboard'))
            ->assertOk()
            ->assertSee('Selamat datang, '.$this->owner()->name)
            ->assertSee(now()->translatedFormat('l, j F Y'));
    }

    public function test_pesanan_terbaru_tampil_dari_yang_paling_baru(): void
    {
        $lama = $this->pesanan(OrderStatus::DIPROSES->value, ['JAHIT']);
        $baru = $this->pesanan(OrderStatus::MENUNGGU_PEMBAYARAN->value, ['MENUNGGU_PEMBAYARAN']);

        $this->actingAs($this->owner())->get(route('panel.dashboard'))
            ->assertOk()
            ->assertSeeInOrder(['Pesanan terbaru', $baru->kode, $lama->kode]);
    }

    public function test_antrean_produksi_jersey_dihitung_per_pesanan(): void
    {
        // Dua barang di meja desain dalam satu pesanan tetap satu pesanan.
        $this->pesanan(OrderStatus::DIPROSES->value, ['ANTRIAN_DESAIN', 'PROSES_DESAIN']);
        $this->pesanan(OrderStatus::DIPROSES->value, ['JAHIT']);
        $this->pesanan(OrderStatus::SIAP_DIAMBIL->value, ['SIAP'], ['metode_ambil' => 'AMBIL_TOKO']);

        $this->actingAs($this->owner())->get(route('panel.dashboard'))
            ->assertOk()
            ->assertViewHas('antreanJersey', ['desain' => 1, 'produksi' => 1, 'siap' => 1]);
    }

    public function test_kartu_siap_menghitung_yang_diambil_maupun_dikirim(): void
    {
        $this->pesanan(OrderStatus::SIAP_DIAMBIL->value, ['SIAP'], ['metode_ambil' => 'AMBIL_TOKO']);
        $this->pesanan(OrderStatus::SIAP_DIAMBIL->value, ['SIAP'], ['metode_ambil' => 'KIRIM']);

        $this->actingAs($this->owner())->get(route('panel.dashboard'))
            ->assertOk()
            ->assertViewHas('siapDiambil', 2);
    }

    public function test_jalan_pintas_mengikuti_izin(): void
    {
        $peran = Role::create(['nama' => 'Kasir uji', 'slug' => 'kasir-uji']);
        $peran->permissions()->sync(Permission::whereIn('slug', ['order.lihat'])->pluck('id'));
        $kasir = User::create(['name' => 'Kasir', 'email' => 'kasir@setiasport.test', 'password' => bcrypt('rahasia-panjang')]);
        $kasir->roles()->attach($peran);

        $this->actingAs($kasir)->get(route('panel.dashboard'))
            ->assertOk()
            ->assertDontSee(route('panel.produk.create'))
            ->assertDontSee(route('panel.stok.index'));

        $this->actingAs($this->owner())->get(route('panel.dashboard'))
            ->assertSee(route('panel.produk.create'))
            ->assertSee(route('panel.stok.index'));
    }
}
