<?php

namespace Tests\Feature;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Layar antrian per divisi seperti gambar klien (KEP-129): tab semua divisi
 * dengan jumlahnya, kotak cari, foto produk, ringkasan ukuran, dan target.
 */
class AntrianDivisiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);
    }

    private function owner(): User
    {
        return User::where('email', 'owner@setiasport.test')->firstOrFail();
    }

    private function barang(OrderItemStatus $status, string $pelanggan, string $produk = 'Custom Jersey Atasan'): OrderItem
    {
        $order = Order::create([
            'kode' => Order::kodeBaru(),
            'customer_id' => Customer::create([
                'nama' => $pelanggan, 'email' => Str::random(6).'@contoh.test', 'no_hp' => '081234567890',
            ])->id,
            'status' => OrderStatus::DIPROSES,
            'access_token' => Order::tokenBaru(),
            'tipe_pembayaran' => 'DP',
            'target_siap_pada' => '2026-11-24',
        ]);

        return $order->items()->create([
            'product_id' => Product::where('sku', 'CUST-JRS-ATASAN')->value('id'),
            'nama_produk' => $produk, 'fulfillment_type' => 'PREORDER',
            'qty' => 5, 'harga_satuan' => 70_000, 'status_item' => $status, 'approved_at' => now(),
        ]);
    }

    public function test_tab_semua_divisi_dengan_tab_aktif_dan_jumlahnya(): void
    {
        $this->barang(OrderItemStatus::ANTRIAN_PRODUKSI, 'Tim Voli');
        $this->barang(OrderItemStatus::PRINTING, 'Tim Futsal');
        $this->barang(OrderItemStatus::JAHIT, 'Tim Basket');

        $this->actingAs($this->owner())
            ->get(route('panel.antrian.tahap', 'print'))
            ->assertOk()
            ->assertSee('Antrian Printing Jersey')
            ->assertSee('Daftar pesanan pada divisi printing jersey.')
            ->assertSee('Printing Jersey (2)')
            ->assertSee('Jahit (1)')
            ->assertSee('aria-current="page"', false);
    }

    public function test_cari_kode_nama_pelanggan_atau_produk_menyaring_pekerjaan(): void
    {
        $voli = $this->barang(OrderItemStatus::PRINTING, 'Tim Voli', 'Custom Jersey Voli');
        $futsal = $this->barang(OrderItemStatus::PRINTING, 'Tim Futsal', 'Custom Jersey Futsal');

        $cari = fn (string $q) => $this->actingAs($this->owner())
            ->get(route('panel.antrian.tahap', ['tahap' => 'print', 'q' => $q]))
            ->assertOk()
            ->viewData('pekerjaan')
            ->pluck('id')
            ->all();

        $this->assertSame([$voli->id], $cari($voli->order->kode));
        $this->assertSame([$futsal->id], $cari('tim futsal'));
        $this->assertSame([$voli->id], $cari('Jersey Voli'));
    }

    public function test_baris_menyebut_rentang_ukuran_dan_target(): void
    {
        $item = $this->barang(OrderItemStatus::PRINTING, 'Tim Voli');
        foreach ([['M', 3], ['L', 2]] as $i => [$ukuran, $jumlah]) {
            $item->rosters()->create([
                'urutan' => $i + 1, 'kategori' => 'DEWASA', 'ukuran' => $ukuran, 'jumlah' => $jumlah, 'harga_satuan' => 70_000,
            ]);
        }

        $this->actingAs($this->owner())
            ->get(route('panel.antrian.tahap', 'print'))
            ->assertOk()
            ->assertSee('SMA / Dewasa M–L')
            ->assertSee('Target: 24 Nov 2026');
    }

    public function test_papan_kerja_menaruh_kartu_pekerjaan_di_kolom_divisinya(): void
    {
        $printing = $this->barang(OrderItemStatus::PRINTING, 'Tim Voli', 'Custom Jersey Voli');
        $jahit = $this->barang(OrderItemStatus::JAHIT, 'Tim Futsal', 'Custom Jersey Futsal');

        $respons = $this->actingAs($this->owner())->get(route('panel.antrian.index'));

        $respons->assertOk()
            ->assertSeeInOrder([
                'Printing Jersey', $printing->order->kode, 'Custom Jersey Voli · 5 pcs', 'Target: 24 Nov 2026',
                'Jahit', $jahit->order->kode, 'Custom Jersey Futsal · 5 pcs',
            ]);
    }

    public function test_kolom_papan_kerja_memuat_enam_kartu_dan_sisanya_lewat_halaman_divisi(): void
    {
        foreach (range(1, 7) as $i) {
            $this->barang(OrderItemStatus::PRINTING, 'Tim '.$i);
        }

        $respons = $this->actingAs($this->owner())->get(route('panel.antrian.index'));

        $respons->assertOk();
        $kolom = $respons->viewData('ringkas')['PRINT'];
        $this->assertSame(7, $kolom['jumlah']);
        $this->assertCount(6, $kolom['kartu']);
    }
}
