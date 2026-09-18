<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Kustom dan stok toko dipisah karena alurnya berbeda (KEP-027).
 *
 * Titik rawannya adalah pesanan campuran: ia harus muncul di satu tab saja
 * supaya hitungannya masuk akal, tapi tidak boleh menyembunyikan fakta bahwa
 * di dalamnya ada barang dari jalur satunya.
 */
class PemisahanJalurTest extends TestCase
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

    private function jersey(): Product
    {
        return Product::where('sku', 'JRS-FUTSAL-FP')->firstOrFail();
    }

    private function checkout(array $tambahan = []): Order
    {
        $this->post('/checkout', array_merge([
            'nama' => 'Budi Santoso',
            'email' => 'budi@contoh.test',
            'no_hp' => '081234567890',
            'setuju' => '1',
        ], $tambahan));

        return Order::latest('id')->firstOrFail();
    }

    private function pesananStok(): Order
    {
        $bola = ProductVariant::where('sku', 'BOLA-MITRE-01')->firstOrFail();
        $this->post('/keranjang', ['variant_id' => $bola->id, 'qty' => 2]);

        return $this->checkout();
    }

    private function pesananKustom(): Order
    {
        $jersey = $this->jersey();
        $this->post(route('keranjang.kustom', $jersey), ['qty' => $jersey->moq, 'opsi' => []]);

        return $this->checkout(['tipe_pembayaran' => 'DP']);
    }

    private function pesananCampuran(): Order
    {
        $jersey = $this->jersey();
        $bola = ProductVariant::where('sku', 'BOLA-MITRE-01')->firstOrFail();

        $this->post(route('keranjang.kustom', $jersey), ['qty' => $jersey->moq, 'opsi' => []]);
        $this->post('/keranjang', ['variant_id' => $bola->id, 'qty' => 1]);

        return $this->checkout(['tipe_pembayaran' => 'DP']);
    }

    public function test_tab_stok_tidak_memuat_pesanan_kustom(): void
    {
        $stok = $this->pesananStok();
        $kustom = $this->pesananKustom();

        $this->actingAs($this->owner())
            ->get(route('panel.pesanan.index', ['jalur' => 'stok']))
            ->assertOk()
            ->assertSee($stok->kode)
            ->assertDontSee($kustom->kode);
    }

    public function test_tab_kustom_tidak_memuat_pesanan_stok(): void
    {
        $stok = $this->pesananStok();
        $kustom = $this->pesananKustom();

        $this->actingAs($this->owner())
            ->get(route('panel.pesanan.index', ['jalur' => 'kustom']))
            ->assertOk()
            ->assertSee($kustom->kode)
            ->assertDontSee($stok->kode);
    }

    /**
     * Pesanan campuran masuk ke tab Kustom, karena bagian kustomnya yang
     * menentukan kapan seluruh pesanan bisa diserahkan.
     */
    public function test_pesanan_campuran_hanya_muncul_di_tab_kustom(): void
    {
        $campuran = $this->pesananCampuran();

        $this->actingAs($this->owner())
            ->get(route('panel.pesanan.index', ['jalur' => 'kustom']))
            ->assertOk()
            ->assertSee($campuran->kode)
            ->assertSee('Campuran');

        $this->actingAs($this->owner())
            ->get(route('panel.pesanan.index', ['jalur' => 'stok']))
            ->assertOk()
            ->assertDontSee($campuran->kode);
    }

    public function test_setiap_pesanan_masuk_tepat_satu_tab(): void
    {
        $this->pesananStok();
        $this->pesananKustom();
        $this->pesananCampuran();

        $this->assertSame(
            Order::count(),
            Order::adaKustom()->count() + Order::hanyaStok()->count(),
            'Hitungan kedua tab harus berjumlah persis sebanyak seluruh pesanan.'
        );
    }

    public function test_halaman_pesanan_pelanggan_memisahkan_kedua_kelompok(): void
    {
        $campuran = $this->pesananCampuran();

        $this->get(route('lacak.token', $campuran->access_token))
            ->assertOk()
            ->assertSee('Dibuat di workshop')
            ->assertSee('Stok toko');
    }

    public function test_judul_kelompok_disembunyikan_kalau_isinya_satu_jenis(): void
    {
        $stok = $this->pesananStok();

        $this->get(route('lacak.token', $stok->access_token))
            ->assertOk()
            ->assertDontSee('Dibuat di workshop');
    }
}
