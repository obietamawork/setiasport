<?php

namespace Tests\Feature;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Laporan "produk terlaris" (KEP-075).
 *
 * Yang dijaga di sini: produk yang pernah diganti namanya tetap dihitung
 * sebagai satu produk, bukan terpecah jadi dua baris karena order_items
 * menyimpan nama_produk sebagai snapshot pada tanggal pesanan dibuat.
 */
class LaporanTest extends TestCase
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

    private function order(string $sku, int $qty, string $namaProdukSnapshot): Order
    {
        $product = Product::where('sku', $sku)->firstOrFail();

        $customer = Customer::firstOrCreate(
            ['email' => 'budi@contoh.test'],
            ['nama' => 'Budi Santoso', 'no_hp' => '081234567890'],
        );

        $order = Order::create([
            'kode' => Order::kodeBaru(),
            'customer_id' => $customer->id,
            'status' => OrderStatus::DIPROSES,
            'access_token' => Order::tokenBaru(),
            'tipe_pembayaran' => 'FULL',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            // Nama ditulis langsung (bukan $product->nama) supaya mewakili
            // snapshot nama produk SAAT pesanan itu dibuat, yang bisa berbeda
            // dari nama produk sekarang kalau produknya sudah diganti nama.
            'nama_produk' => $namaProdukSnapshot,
            'fulfillment_type' => $product->fulfillment_type,
            'qty' => $qty,
            'harga_satuan' => 100_000,
            'status_item' => OrderItemStatus::DIPROSES,
        ]);

        return $order;
    }

    public function test_produk_yang_pernah_diganti_nama_tetap_dihitung_sebagai_satu_produk(): void
    {
        $produk = Product::where('sku', 'JRS-FUTSAL-FP')->firstOrFail();

        // Dipesan 5 pcs saat nama produknya masih "Jersey Futsal FP".
        $this->order('JRS-FUTSAL-FP', 5, 'Jersey Futsal FP');

        // Admin membetulkan nama produknya.
        $produk->update(['nama' => 'Jersey Futsal Pro']);

        // Dipesan lagi 3 pcs, kali ini snapshot-nya memakai nama baru.
        $this->order('JRS-FUTSAL-FP', 3, 'Jersey Futsal Pro');

        $response = $this->actingAs($this->owner())
            ->get(route('panel.laporan', ['periode' => 'tahun-ini']))
            ->assertOk();

        $terlaris = $response->viewData('terlaris');
        $baris = $terlaris->firstWhere('nama_produk', 'Jersey Futsal Pro');

        $this->assertNotNull($baris, 'Produk yang pernah diganti nama harus tetap muncul di produk terlaris.');
        $this->assertSame(1, $terlaris->count(), 'Harus jadi satu baris, bukan terpecah per nama snapshot.');
        $this->assertSame(8, (int) $baris->total_qty);
        $this->assertSame(2, (int) $baris->total_pesanan);
    }

    /**
     * subMonth() dari tanggal 29-31 membulatkan MAJU ke bulan ini lagi kalau
     * bulan sebelumnya lebih pendek (Maret, Mei, Juli, Oktober, Desember) --
     * "Bulan lalu" tanpa sadar melaporkan bulan ini lagi. Maret dipakai di
     * sini karena Februari (28/29 hari) selalu lebih pendek dari Maret (31).
     */
    public function test_bulan_lalu_tidak_meleset_ke_bulan_ini_pada_tanggal_31(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-03-31 10:00:00'));

        // Dipesan di bulan ini (Maret) -- TIDAK boleh ikut masuk "Bulan lalu".
        $this->order('JRS-FUTSAL-FP', 1, 'Jersey Futsal FP');

        $response = $this->actingAs($this->owner())
            ->get(route('panel.laporan', ['periode' => 'bulan-lalu']))
            ->assertOk();

        $this->assertSame('2026-02-01', $response->viewData('dari')->format('Y-m-d'));
        $this->assertSame('2026-02-28', $response->viewData('sampai')->format('Y-m-d'));
        $this->assertSame(0, $response->viewData('jumlahPesanan'));

        CarbonImmutable::setTestNow();
    }
}
