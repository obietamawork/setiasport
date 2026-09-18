<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\User;
use App\Services\AlurPesanan;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Barang yang tidak pernah diambil (KEP-043).
 *
 * Klien memutuskan satu bulan lalu uangnya hangus. Yang dijaga di sini: batasnya
 * benar-benar dihormati, tombolnya tidak tersedia sebelum lewat, dan pelanggan
 * membaca tanggal batasnya di halaman pesanannya sendiri.
 */
class BarangHangusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);
    }

    private function owner(): User
    {
        return User::where('email', 'owner@setiasport.test')->firstOrFail();
    }

    /** Pesanan stok yang sudah lunas dan siap diambil. */
    private function siapDiambil(): Order
    {
        $varian = ProductVariant::where('sku', 'BOLA-MITRE-01')->firstOrFail();

        $this->post(route('keranjang.tambah'), ['variant_id' => $varian->id, 'qty' => 1]);
        $this->post('/checkout', [
            'nama' => 'Budi Santoso',
            'email' => 'budi@contoh.test',
            'no_hp' => '081234567890',
            'setuju' => '1',
        ]);

        $order = Order::latest('id')->firstOrFail();

        app(AlurPesanan::class)->terimaPembayaran($order->payments->first());

        $alur = app(AlurPesanan::class);
        $item = $order->items->first();

        // DIPROSES -> PACKING -> SIAP
        $alur->majukanItem($item->fresh(), $this->owner()->id);
        $alur->majukanItem($item->fresh(), $this->owner()->id);

        return $order->fresh(['items.options', 'payments', 'adjustments']);
    }

    public function test_belum_lewat_batas_belum_bisa_dihanguskan(): void
    {
        $order = $this->siapDiambil();

        $this->assertSame(OrderStatus::SIAP_DIAMBIL, $order->status);
        $this->assertFalse($order->bolehDihanguskan());

        $this->actingAs($this->owner())
            ->post(route('panel.pesanan.hangus', $order))
            ->assertStatus(422);

        $this->assertSame(OrderStatus::SIAP_DIAMBIL, $order->fresh()->status);
    }

    public function test_lewat_batas_bisa_dihanguskan_oleh_admin(): void
    {
        $order = $this->siapDiambil();
        $order->update(['siap_pada' => now()->subDays(31)]);

        $this->assertTrue($order->fresh()->bolehDihanguskan());

        $this->actingAs($this->owner())
            ->post(route('panel.pesanan.hangus', $order))
            ->assertSessionHas('sukses');

        $order->refresh();

        $this->assertSame(OrderStatus::KADALUARSA, $order->status);
        $this->assertStringContainsString(
            'tidak diambil',
            $order->statusLogs()->latest('id')->first()->catatan
        );
    }

    /** Batasnya pengaturan, bukan angka yang tertanam di kode. */
    public function test_batas_hari_mengikuti_pengaturan(): void
    {
        Setting::simpan('hangus_hari', '7');

        $order = $this->siapDiambil();
        $order->update(['siap_pada' => now()->subDays(10)]);

        $this->assertTrue($order->fresh()->bolehDihanguskan());
    }

    public function test_pelanggan_membaca_tanggal_batasnya(): void
    {
        $order = $this->siapDiambil();
        $order->update(['siap_pada' => now()]);

        $this->get(route('lacak.token', $order->access_token))
            ->assertOk()
            ->assertSee('Diambil paling lambat')
            ->assertSee(now()->addDays(30)->translatedFormat('j F Y'));
    }

    public function test_dashboard_menandai_yang_lewat_batas(): void
    {
        $order = $this->siapDiambil();
        $order->update(['siap_pada' => now()->subDays(31)]);

        $this->actingAs($this->owner())
            ->get(route('panel.dashboard'))
            ->assertOk()
            ->assertSee('Lewat batas pengambilan')
            ->assertSee($order->kode);
    }

    /** Pesanan yang belum siap tidak boleh ikut terhanguskan. */
    public function test_pesanan_yang_belum_siap_ditolak(): void
    {
        $varian = ProductVariant::where('sku', 'BOLA-MITRE-01')->firstOrFail();
        $this->post(route('keranjang.tambah'), ['variant_id' => $varian->id, 'qty' => 1]);
        $this->post('/checkout', [
            'nama' => 'Budi Santoso', 'email' => 'budi@contoh.test',
            'no_hp' => '081234567890', 'setuju' => '1',
        ]);

        $order = Order::latest('id')->firstOrFail();

        $this->actingAs($this->owner())
            ->post(route('panel.pesanan.hangus', $order))
            ->assertStatus(422);
    }
}
