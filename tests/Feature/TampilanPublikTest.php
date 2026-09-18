<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\ProductVariant;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Permintaan tampilan dari klien (KEP-042): keranjang jadi ikon di sebelah
 * tombol masuk, judul halaman ikut menempel di HP, tautan pesanan bisa disalin,
 * dan bantuan WhatsApp ada tepat di samping tombol terakhir checkout.
 */
class TampilanPublikTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);
    }

    public function test_keranjang_berupa_ikon_dan_mendahului_tombol_masuk(): void
    {
        $this->get(route('katalog'))
            ->assertOk()
            // Namanya tetap terbaca pembaca layar walau yang tampil ikon.
            ->assertSee('<span class="sr-only">Keranjang</span>', false)
            ->assertSeeInOrder(['<span class="sr-only">Keranjang</span>', 'Masuk'], false);
    }

    public function test_judul_halaman_menempel_di_tampilan_hp(): void
    {
        $this->get(route('katalog'))
            ->assertOk()
            ->assertSee('Pilih produk, lihat harganya');

        $this->get(route('beranda'))
            ->assertOk()
            // Halaman depan tidak punya judul kedua; bilahnya tidak boleh muncul kosong.
            ->assertDontSee('sticky top-16', false);
    }

    public function test_tautan_pesanan_bisa_disalin(): void
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

        $this->get(route('lacak.token', $order->access_token))
            ->assertOk()
            ->assertSee('Salin tautan pesanan');
    }

    /** Pelanggan yang ragu di langkah terakhir menutup halaman kalau tidak ada jalan bertanya. */
    public function test_checkout_menawarkan_bantuan_whatsapp(): void
    {
        $varian = ProductVariant::where('sku', 'BOLA-MITRE-01')->firstOrFail();
        $this->post(route('keranjang.tambah'), ['variant_id' => $varian->id, 'qty' => 1]);

        $this->get(route('checkout'))
            ->assertOk()
            ->assertSee('Tanya lewat WhatsApp')
            ->assertSee('wa.me', false);
    }
}
