<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Pekerja;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Layar serah terima dari revisi klien (KEP-115): detail pesanan selengkap
 * gambar contoh, supaya kasir tidak perlu membuka halaman pesanan untuk tahu
 * siapa pelanggannya, apa isinya, siapa yang mengerjakan, dan berapa sisanya.
 */
class SerahTerimaTampilanTest extends TestCase
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

    private function pesananSiap(int $dibayar): Order
    {
        $order = Order::create([
            'kode' => Order::kodeBaru(),
            'kode_ambil' => 'ABC-1234',
            'customer_id' => Customer::create([
                'nama' => 'Titan Setia Sport', 'email' => 'titan@contoh.test', 'no_hp' => '082321938129',
            ])->id,
            'status' => OrderStatus::SIAP_DIAMBIL,
            'metode_ambil' => 'AMBIL_TOKO',
            'access_token' => Order::tokenBaru(),
            'tipe_pembayaran' => 'DP',
            'target_siap_pada' => '2026-11-11',
            'siap_pada' => now(),
        ]);

        $item = $order->items()->create([
            'product_id' => Product::where('sku', 'CUST-JRS-ATASAN')->value('id'),
            'nama_produk' => 'Jersey Voli Custom', 'fulfillment_type' => 'PREORDER',
            'qty' => 12, 'harga_satuan' => 30_000, 'status_item' => 'SIAP',
        ]);

        $order->payments()->create([
            'jenis' => 'DP', 'metode' => 'TUNAI', 'status' => PaymentStatus::LUNAS,
            'jumlah' => $dibayar, 'dibayar_pada' => now(),
        ]);

        $this->selesaiOleh($item);

        return $order->fresh();
    }

    private function selesaiOleh(OrderItem $item): void
    {
        $pekerja = Pekerja::create(['nama' => 'Mas Joko', 'pin_hash' => bcrypt('1234'), 'tahap' => ['JAHIT'], 'aktif' => true]);

        $item->productionJobs()->create([
            'tahap' => 'JAHIT', 'urutan' => 1, 'status' => 'SELESAI', 'pekerja_id' => $pekerja->id, 'selesai_pada' => now(),
        ]);
        $item->productionJobs()->create([
            'tahap' => 'QC', 'urutan' => 2, 'status' => 'SELESAI', 'diselesaikan_oleh' => $this->owner()->id, 'selesai_pada' => now(),
        ]);
    }

    public function test_detail_menyebut_pelanggan_target_dan_pengerja(): void
    {
        $order = $this->pesananSiap(360_000);

        $this->actingAs($this->owner())
            ->get(route('panel.serah-terima', ['kode' => 'abc-1234']))
            ->assertOk()
            ->assertSee('Titan Setia Sport')
            ->assertSee('https://wa.me/6282321938129', false)
            ->assertSee('11 Nov 2026')
            ->assertSee('Mas Joko')
            ->assertSee($this->owner()->name)
            ->assertSee($order->kode);
    }

    public function test_ringkasan_pembayaran_lunas_dan_tombol_konfirmasi(): void
    {
        $this->pesananSiap(360_000);

        $this->actingAs($this->owner())
            ->get(route('panel.serah-terima', ['kode' => 'ABC-1234']))
            ->assertOk()
            ->assertSeeInOrder(['Total pesanan', 'Rp 360.000', 'Sudah dibayar', 'Rp 360.000', 'Sisa pembayaran', 'Rp 0', 'Lunas'])
            ->assertSee('Siap diserahkan')
            ->assertSee('Konfirmasi serah terima');
    }

    public function test_masih_bersisa_menampilkan_pelunasan_bukan_konfirmasi(): void
    {
        $this->pesananSiap(100_000);

        $this->actingAs($this->owner())
            ->get(route('panel.serah-terima', ['kode' => 'ABC-1234']))
            ->assertOk()
            ->assertSee('Rp 260.000')
            ->assertSee('Catat pelunasan')
            ->assertDontSee('Konfirmasi serah terima');
    }

    public function test_tombol_kembali_dan_hapus_pencarian_mengosongkan_kode(): void
    {
        $this->pesananSiap(360_000);

        $this->actingAs($this->owner())
            ->get(route('panel.serah-terima', ['kode' => 'ABC-1234']))
            ->assertOk()
            ->assertSee('aria-label="Hapus pencarian"', false)
            ->assertSee('data-kembali', false);
    }
}
