<?php

namespace Tests\Feature;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Pusat akun pelanggan seperti papan kanvas Kit Tim (KEP-177): sapaan,
 * saringan status, dan satu tombol aksi per pesanan sesuai keadaannya.
 */
class PesananSayaTest extends TestCase
{
    use RefreshDatabase;

    private Customer $pelanggan;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);
        $this->pelanggan = Customer::create(['nama' => 'Budi', 'email' => 'budi@contoh.test', 'no_hp' => '081234567890']);
        $this->pelanggan->forceFill(['password' => 'sandi-lama-123', 'email_terverifikasi_pada' => now()])->save();
    }

    public function test_pesanan_saya_dan_akun_saya_menyapa_pelanggan_dengan_namanya(): void
    {
        $pesanan = $this->actingAs($this->pelanggan, 'pelanggan')->get(route('pesanan-saya'));
        $akun = $this->actingAs($this->pelanggan, 'pelanggan')->get(route('akun'));

        $pesanan->assertOk()->assertSee('Halo, Budi');
        $akun->assertOk()->assertSee('Halo, Budi');
    }

    public function test_saringan_dibatalkan_menghitung_semua_status_dan_hanya_menampilkan_yang_batal(): void
    {
        $berjalan = $this->pesanan(OrderStatus::DIPROSES)->order;
        $selesai = $this->pesanan(OrderStatus::SELESAI, OrderItemStatus::SELESAI)->order;
        $dibatalkan = $this->pesanan(OrderStatus::DIBATALKAN)->order;
        $kedaluwarsa = $this->pesanan(OrderStatus::KADALUARSA)->order;

        $respons = $this->actingAs($this->pelanggan, 'pelanggan')->get(route('pesanan-saya', ['status' => 'batal']));

        $respons->assertOk()
            ->assertSeeInOrder(['Semua', '4', 'Berjalan', '1', 'Selesai', '1', 'Dibatalkan', '2'])
            ->assertSee($dibatalkan->kode)
            ->assertSee($kedaluwarsa->kode)
            ->assertDontSee($berjalan->kode)
            ->assertDontSee($selesai->kode);
    }

    /**
     * @return array<string, array{OrderStatus, OrderItemStatus, array<string, string>, string, string}>
     */
    public static function keadaanPesanan(): array
    {
        return [
            'desain menunggu persetujuan' => [OrderStatus::DIPROSES, OrderItemStatus::MENUNGGU_PERSETUJUAN, [], 'Periksa mockup', 'persetujuan'],
            'siap diambil di toko' => [OrderStatus::SIAP_DIAMBIL, OrderItemStatus::SIAP, ['kode_ambil' => '7QK-2611'], 'Lihat petunjuk ambil', 'ambil'],
            'selesai' => [OrderStatus::SELESAI, OrderItemStatus::SELESAI, [], 'Pesan lagi', 'produk'],
            'dibatalkan' => [OrderStatus::DIBATALKAN, OrderItemStatus::DIPROSES, [], 'Lihat detail', 'lacak'],
        ];
    }

    /** @param  array<string, string>  $tambahan */
    #[DataProvider('keadaanPesanan')]
    public function test_kartu_pesanan_memberi_tombol_aksi_sesuai_keadaannya(
        OrderStatus $status, OrderItemStatus $statusBarang, array $tambahan, string $label, string $tujuan,
    ): void {
        $item = $this->pesanan($status, $statusBarang, $tambahan);
        $lacak = route('lacak.token', $item->order->access_token);
        $href = match ($tujuan) {
            'persetujuan' => $lacak.'#persetujuan-'.$item->id,
            'ambil' => $lacak.'#ambil-di-toko',
            'produk' => route('produk', $item->product),
            'lacak' => $lacak,
        };

        $respons = $this->actingAs($this->pelanggan, 'pelanggan')->get(route('pesanan-saya'));

        $respons->assertOk()->assertSeeInOrder(['href="'.$href.'"', $label], false);
        foreach (array_diff(['Periksa mockup', 'Lihat petunjuk ambil', 'Pesan lagi', 'Lihat detail'], [$label]) as $lain) {
            $respons->assertDontSee($lain);
        }
    }

    /** @param  array<string, string>  $tambahan */
    private function pesanan(OrderStatus $status, OrderItemStatus $statusBarang = OrderItemStatus::DIPROSES, array $tambahan = []): OrderItem
    {
        $order = Order::create([
            'kode' => Order::kodeBaru(), 'customer_id' => $this->pelanggan->id, 'status' => $status,
            'access_token' => Order::tokenBaru(), 'tipe_pembayaran' => 'DP', ...$tambahan,
        ]);

        return $order->items()->create([
            'product_id' => Product::where('sku', 'CUST-JRS-ATASAN')->value('id'),
            'nama_produk' => 'Custom Jersey Atasan', 'fulfillment_type' => 'PREORDER',
            'qty' => 6, 'harga_satuan' => 70_000, 'status_item' => $statusBarang,
        ]);
    }
}
