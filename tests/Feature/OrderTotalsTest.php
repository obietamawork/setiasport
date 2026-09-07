<?php

namespace Tests\Feature;

use App\Enums\FulfillmentType;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\PriceBasis;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\OrderStatusResolver;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Menjaga tiga aturan yang paling mudah rusak diam-diam:
 * agregat status order (KEP-001), uang yang selalu dihitung ulang (KEP-003,
 * KEP-007), dan larangan menyelesaikan order yang masih punya sisa tagihan
 * (KEP-006).
 */
class OrderTotalsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
        $this->seed(CatalogSeeder::class);
    }

    private function order(): Order
    {
        $customer = Customer::create([
            'nama' => 'Budi Santoso',
            'email' => 'budi@contoh.test',
            'no_hp' => '081234567890',
        ]);

        return Order::create([
            'kode' => Order::kodeBaru(),
            'customer_id' => $customer->id,
            'status' => OrderStatus::MENUNGGU_PEMBAYARAN,
            'access_token' => Order::tokenBaru(),
            'tipe_pembayaran' => 'DP',
        ]);
    }

    private function item(Order $order, string $sku, int $qty, int $harga, OrderItemStatus $status): OrderItem
    {
        $product = Product::where('sku', $sku)->firstOrFail();

        return $order->items()->create([
            'product_id' => $product->id,
            'nama_produk' => $product->nama,
            'fulfillment_type' => $product->fulfillment_type === FulfillmentType::READY_STOCK
                ? FulfillmentType::READY_STOCK
                : FulfillmentType::PREORDER,
            'qty' => $qty,
            'harga_satuan' => $harga,
            'status_item' => $status,
        ]);
    }

    public function test_order_belum_siap_selama_ada_satu_item_yang_belum_selesai(): void
    {
        $order = $this->order();
        $this->item($order, 'BOLA-MITRE-01', 1, 285_000, OrderItemStatus::SIAP);
        $this->item($order, 'JRS-FUTSAL-FP', 18, 120_000, OrderItemStatus::PACKING);

        $status = (new OrderStatusResolver)->sinkronkan($order->load('items'));

        // Pelanggan datang sekali dan mengambil semuanya (KEP-001).
        $this->assertSame(OrderStatus::DIPROSES, $status);
    }

    public function test_order_siap_diambil_ketika_semua_item_siap(): void
    {
        $order = $this->order();
        $this->item($order, 'BOLA-MITRE-01', 1, 285_000, OrderItemStatus::SIAP);
        $this->item($order, 'JRS-FUTSAL-FP', 18, 120_000, OrderItemStatus::SIAP);

        $status = (new OrderStatusResolver)->sinkronkan($order->load('items'));
        $order->refresh();

        $this->assertSame(OrderStatus::SIAP_DIAMBIL, $status);
        $this->assertNotNull($order->siap_pada);
        $this->assertNotNull($order->kode_ambil);
        $this->assertSame(1, $order->statusLogs()->count());
    }

    public function test_total_menghitung_ulang_opsi_per_item_dan_penyesuaian(): void
    {
        $order = $this->order();
        $item = $this->item($order, 'JRS-FUTSAL-FP', 18, 120_000, OrderItemStatus::ANTRIAN_DESAIN);

        $item->options()->create([
            'option_nama' => 'Setup Desain',
            'option_value' => 'Setup Desain Kustom',
            'tambahan_harga' => 75_000,
            'basis' => PriceBasis::PER_ITEM,
        ]);

        $order->load(['items.options', 'adjustments', 'payments']);
        $this->assertSame(2_235_000, $order->total);

        // Tim bertambah 2 orang setelah DP masuk (KEP-007).
        $order->adjustments()->create(['deskripsi' => 'Tambah 2 pemain', 'jumlah' => 240_000]);

        $order->load('adjustments');
        $this->assertSame(2_475_000, $order->total);
        $this->assertSame(1_237_500, $order->dpMinimum());
    }

    public function test_penyesuaian_negatif_mengurangi_total(): void
    {
        $order = $this->order();
        $this->item($order, 'BOLA-MITRE-01', 2, 285_000, OrderItemStatus::DIPROSES);
        $order->adjustments()->create(['deskripsi' => 'Diskon langganan', 'jumlah' => -70_000]);

        $order->load(['items.options', 'adjustments', 'payments']);

        $this->assertSame(500_000, $order->total);
    }

    public function test_order_tidak_boleh_diserahkan_selama_masih_ada_sisa_tagihan(): void
    {
        $order = $this->order();
        $this->item($order, 'JRS-FUTSAL-FP', 12, 95_000, OrderItemStatus::SIAP);

        (new OrderStatusResolver)->sinkronkan($order->load('items'));
        $order->load(['items.options', 'adjustments', 'payments']);

        $this->assertSame(1_140_000, $order->total);

        // DP 50% lewat gateway — barang tetap diproduksi, tapi belum boleh diserahkan.
        $order->payments()->create([
            'jenis' => PaymentType::DP,
            'metode' => PaymentMethod::GATEWAY,
            'status' => PaymentStatus::LUNAS,
            'jumlah' => 570_000,
            'dibayar_pada' => now(),
        ]);

        $order->load('payments');
        $this->assertSame(570_000, $order->sisa_tagihan);
        $this->assertFalse($order->bolehDiserahkan());

        // Pelunasan tunai di kasir saat pengambilan (KEP-006).
        $order->payments()->create([
            'jenis' => PaymentType::PELUNASAN,
            'metode' => PaymentMethod::TUNAI,
            'status' => PaymentStatus::LUNAS,
            'jumlah' => 570_000,
            'dibayar_pada' => now(),
        ]);

        $order->load('payments');
        $this->assertSame(0, $order->sisa_tagihan);
        $this->assertTrue($order->bolehDiserahkan());
    }

    public function test_pembayaran_belum_lunas_tidak_ikut_dihitung(): void
    {
        $order = $this->order();
        $this->item($order, 'BOLA-MITRE-01', 1, 285_000, OrderItemStatus::MENUNGGU_PEMBAYARAN);

        $order->payments()->create([
            'jenis' => PaymentType::FULL,
            'metode' => PaymentMethod::GATEWAY,
            'status' => PaymentStatus::MENUNGGU,
            'jumlah' => 285_000,
        ]);

        $order->load(['items.options', 'adjustments', 'payments']);

        $this->assertSame(0, $order->sudah_dibayar);
        $this->assertSame(285_000, $order->sisa_tagihan);
    }
}
