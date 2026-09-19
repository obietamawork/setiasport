<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Biaya layanan per pesanan (KEP-048).
 *
 * Yang dijaga: angkanya terbaca pelanggan SEBELUM membayar, ikut terhitung di
 * tagihan, dan dibekukan di pesanan supaya kenaikan tarif tidak mengubah
 * tagihan yang sudah berjalan.
 */
class BiayaLayananTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);
    }

    private function pesan(): Order
    {
        $varian = ProductVariant::where('sku', 'BOLA-MITRE-01')->firstOrFail();

        $this->post(route('keranjang.tambah'), ['variant_id' => $varian->id, 'qty' => 1]);
        $this->post('/checkout', [
            'nama' => 'Budi Santoso', 'email' => 'budi@contoh.test',
            'no_hp' => '081234567890', 'setuju' => '1',
        ]);

        return Order::latest('id')->firstOrFail();
    }

    public function test_biaya_layanan_masuk_tagihan(): void
    {
        Setting::simpan('biaya_layanan', '500');

        $order = $this->pesan();

        $this->assertSame(500, $order->biaya_layanan);
        $this->assertSame($order->subtotal + 500, $order->total);
    }

    /** Angka yang baru muncul di halaman tagihan terasa seperti ditambah diam-diam. */
    public function test_terbaca_di_checkout_sebelum_membayar(): void
    {
        Setting::simpan('biaya_layanan', '500');

        $varian = ProductVariant::where('sku', 'BOLA-MITRE-01')->firstOrFail();
        $this->post(route('keranjang.tambah'), ['variant_id' => $varian->id, 'qty' => 1]);

        $this->get(route('checkout'))
            ->assertOk()
            ->assertSee('Biaya layanan')
            ->assertSee(rupiah(285_000 + 500));
    }

    public function test_terbaca_di_halaman_pesanan_dan_invoice(): void
    {
        Setting::simpan('biaya_layanan', '500');

        $order = $this->pesan();

        $this->get(route('lacak.token', $order->access_token))
            ->assertOk()
            ->assertSee('Biaya layanan');

        $this->actingAs(User::where('email', 'owner@setiasport.test')->firstOrFail())
            ->get(route('panel.pesanan.invoice', $order))
            ->assertOk()
            ->assertSee('Biaya layanan');
    }

    /** Tarif boleh naik; tagihan yang sudah disepakati tidak ikut berubah. */
    public function test_kenaikan_tarif_tidak_mengubah_pesanan_lama(): void
    {
        Setting::simpan('biaya_layanan', '500');
        $order = $this->pesan();

        Setting::simpan('biaya_layanan', '5000');

        $this->assertSame(500, $order->fresh()->biaya_layanan);
        $this->assertSame(
            $order->subtotal + 500,
            $order->fresh(['items.options', 'adjustments'])->total
        );
    }

    public function test_nol_berarti_tidak_ada_biaya_layanan(): void
    {
        Setting::simpan('biaya_layanan', '0');

        $order = $this->pesan();

        $this->assertSame(0, $order->biaya_layanan);
        $this->assertSame($order->subtotal, $order->total);

        $this->get(route('lacak.token', $order->access_token))
            ->assertOk()
            ->assertDontSee('Biaya layanan');
    }
}
