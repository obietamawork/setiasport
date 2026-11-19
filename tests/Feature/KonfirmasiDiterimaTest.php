<?php

namespace Tests\Feature;

use App\Enums\FasePesanan;
use App\Enums\MetodeAmbil;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Mail\EmailPesanan;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Support\LinimasaItem;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pelanggan mengonfirmasi sendiri paket sudah diterima (KEP-131). Tombolnya
 * di halaman pesanan; email "pesanan dikirim" hanya mengarahkan ke sana,
 * karena tautan di email dibuka otomatis oleh pemindai keamanan.
 */
class KonfirmasiDiterimaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);
    }

    private function pesanan(OrderStatus $status = OrderStatus::DIKIRIM): Order
    {
        $order = Order::create([
            'kode' => Order::kodeBaru(),
            'customer_id' => Customer::create([
                'nama' => 'Tim Luar Kota', 'email' => Str::random(6).'@contoh.test', 'no_hp' => '081234567890',
            ])->id,
            'status' => $status,
            'access_token' => Order::tokenBaru(),
            'tipe_pembayaran' => 'FULL',
            'metode_ambil' => MetodeAmbil::KIRIM,
            'alamat_kirim' => 'Jl. Raya Solo KM 20, Sragen',
            'jasa_kirim' => 'JNE', 'no_resi' => 'JNE123456789',
            'dikirim_pada' => $status === OrderStatus::DIKIRIM ? now()->subDay() : null,
        ]);

        $item = $order->items()->create([
            'product_id' => Product::where('sku', 'CUST-JRS-ATASAN')->value('id'),
            'nama_produk' => 'Custom Jersey Atasan', 'fulfillment_type' => 'PREORDER',
            'qty' => 6, 'harga_satuan' => 70_000,
            'status_item' => $status === OrderStatus::DIKIRIM ? OrderItemStatus::SELESAI : OrderItemStatus::JAHIT,
            'approved_at' => now(),
        ]);
        $order->statusLogs()->create(['order_item_id' => $item->id, 'dari' => 'SIAP', 'ke' => 'SELESAI', 'created_at' => now()->subDay()]);

        $order->payments()->create([
            'jenis' => 'FULL', 'metode' => 'TRANSFER', 'status' => PaymentStatus::LUNAS,
            'jumlah' => $order->fresh(['items.options', 'items.rosters', 'adjustments'])->total, 'dibayar_pada' => now(),
        ]);

        return $order->fresh();
    }

    public function test_pelanggan_bisa_konfirmasi_paket_diterima(): void
    {
        $order = $this->pesanan();

        $this->get(route('lacak.token', $order->access_token))
            ->assertOk()
            ->assertSee('id="konfirmasi-diterima"', false)
            ->assertSee('Pesanan sudah saya terima');

        $this->post(route('lacak.diterima', $order->access_token))
            ->assertSessionHas('sukses');

        $order->refresh();
        $this->assertSame(OrderStatus::SELESAI, $order->status);
        $this->assertNotNull($order->diterima_pada);
    }

    public function test_konfirmasi_ditolak_kalau_pesanan_belum_dikirim(): void
    {
        $order = $this->pesanan(OrderStatus::DIPROSES);

        $this->get(route('lacak.token', $order->access_token))
            ->assertOk()
            ->assertDontSee('Pesanan sudah saya terima');

        $this->post(route('lacak.diterima', $order->access_token))->assertSessionHasErrors();

        $this->assertSame(OrderStatus::DIPROSES, $order->fresh()->status);
    }

    public function test_email_dikirim_mengarahkan_ke_tombol_konfirmasi(): void
    {
        $order = $this->pesanan();

        $html = (new EmailPesanan($order, 'pesanan-dikirim', 'Subjek'))->render();

        $this->assertStringContainsString(route('lacak.token', $order->access_token).'#konfirmasi-diterima', $html);
        $this->assertStringContainsString('Konfirmasi pesanan diterima', $html);
    }

    /** Tahap yang belum terjadi tidak boleh membawa tanggal (gambar klien). */
    public function test_tahap_yang_belum_terjadi_tidak_bertanggal(): void
    {
        $order = $this->pesanan()->load('statusLogs');
        $item = $order->items()->firstOrFail();

        $selesai = collect(LinimasaItem::untuk($item, $order)['fase'])->firstWhere('fase', FasePesanan::SELESAI);

        $this->assertSame('nanti', $selesai['keadaan']);
        $this->assertNull($selesai['waktu']);
    }
}
