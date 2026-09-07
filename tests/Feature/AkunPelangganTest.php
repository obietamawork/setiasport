<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\ProductVariant;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Akun pelanggan adalah jalur KEDUA, bukan pengganti (KEP-020).
 *
 * Yang paling penting diuji bukan fitur akunnya, melainkan bahwa jalur tanpa
 * akun tetap utuh: memaksa pendaftaran di checkout persis yang ingin dihindari.
 */
class AkunPelangganTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);
    }

    private function isiKeranjang(int $qty = 1): void
    {
        $this->post('/keranjang', [
            'variant_id' => ProductVariant::where('sku', 'BOLA-MITRE-01')->value('id'),
            'qty' => $qty,
        ]);
    }

    private function dataCheckout(array $tambahan = []): array
    {
        return array_merge([
            'nama' => 'Budi Santoso',
            'email' => 'budi@contoh.test',
            'no_hp' => '081234567890',
            'setuju' => '1',
        ], $tambahan);
    }

    private function pelanggan(string $email = 'rian@contoh.test'): Customer
    {
        return Customer::create([
            'nama' => 'Rian Pratama',
            'email' => $email,
            'no_hp' => '081298765432',
            'password' => Hash::make('rahasia12345'),
        ]);
    }

    public function test_checkout_tamu_tetap_jalan_tanpa_akun(): void
    {
        $this->isiKeranjang();
        $this->post('/checkout', $this->dataCheckout())->assertRedirect();

        $pelanggan = Customer::where('email', 'budi@contoh.test')->firstOrFail();

        $this->assertFalse($pelanggan->punyaAkun());
        $this->assertGuest('pelanggan');
        $this->assertSame(1, Order::count());
    }

    public function test_tautan_bertoken_tetap_membuka_pesanan_tanpa_masuk(): void
    {
        $this->isiKeranjang();
        $this->post('/checkout', $this->dataCheckout());

        $order = Order::latest('id')->firstOrFail();

        $this->get(route('lacak.token', $order->access_token))
            ->assertOk()
            ->assertSee($order->kode);
    }

    public function test_akun_bisa_dibuat_sekalian_saat_checkout(): void
    {
        $this->isiKeranjang();

        $this->post('/checkout', $this->dataCheckout([
            'buat_akun' => '1',
            'password' => 'rahasia12345',
            'password_confirmation' => 'rahasia12345',
        ]))->assertRedirect();

        $pelanggan = Customer::where('email', 'budi@contoh.test')->firstOrFail();

        $this->assertTrue($pelanggan->punyaAkun());
        $this->assertAuthenticatedAs($pelanggan, 'pelanggan');
    }

    public function test_kata_sandi_hanya_diperiksa_bila_akun_dibuat(): void
    {
        $this->isiKeranjang();

        $this->post('/checkout', $this->dataCheckout(['password' => '']))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Order::count());
    }

    public function test_kata_sandi_lemah_ditolak_saat_membuat_akun(): void
    {
        $this->isiKeranjang();

        $this->post('/checkout', $this->dataCheckout([
            'buat_akun' => '1',
            'password' => '123',
            'password_confirmation' => '123',
        ]))->assertSessionHasErrors('password');

        $this->assertSame(0, Order::count());
    }

    public function test_pemesan_tamu_yang_mendaftar_belakangan_mewarisi_riwayatnya(): void
    {
        $this->isiKeranjang();
        $this->post('/checkout', $this->dataCheckout());

        $this->post('/daftar', [
            'nama' => 'Budi Santoso',
            'email' => 'budi@contoh.test',
            'no_hp' => '081234567890',
            'password' => 'rahasia12345',
            'password_confirmation' => 'rahasia12345',
        ])->assertRedirect(route('pesanan-saya'));

        // Email adalah kunci pelanggan sejak awal, jadi tidak ada yang perlu dipindahkan.
        $this->get('/pesanan-saya')->assertOk()->assertSee(Order::first()->kode);

        $this->assertSame(1, Customer::count());
    }

    public function test_pelanggan_lama_tanpa_kata_sandi_diarahkan_ke_pendaftaran(): void
    {
        Customer::create(['nama' => 'Rian', 'email' => 'rian@contoh.test', 'no_hp' => '0812']);

        $this->post('/masuk', ['email' => 'rian@contoh.test', 'password' => 'apa-saja'])
            ->assertRedirect(route('daftar', ['email' => 'rian@contoh.test']));

        $this->assertGuest('pelanggan');
    }

    public function test_kata_sandi_salah_ditolak(): void
    {
        $this->pelanggan();

        $this->post('/masuk', ['email' => 'rian@contoh.test', 'password' => 'salah'])
            ->assertSessionHasErrors('email');

        $this->assertGuest('pelanggan');
    }

    public function test_halaman_pesanan_saya_tertutup_bagi_tamu(): void
    {
        // Diarahkan ke halaman masuk pelanggan, bukan ke halaman masuk staf.
        $this->get('/pesanan-saya')->assertRedirect(route('masuk'));
    }

    public function test_pelanggan_tidak_bisa_masuk_panel_internal(): void
    {
        // Penjaga sesi terpisah: akun pelanggan tidak pernah menyentuh panel.
        $this->actingAs($this->pelanggan(), 'pelanggan')
            ->get('/panel')
            ->assertRedirect(route('panel.login'));
    }

    public function test_pelanggan_yang_masuk_tidak_perlu_mengetik_ulang_datanya(): void
    {
        $pelanggan = $this->pelanggan();

        $this->actingAs($pelanggan, 'pelanggan');
        $this->isiKeranjang();

        // Email tidak dikirim dari formulir karena kolomnya terkunci.
        $this->post('/checkout', ['nama' => 'Rian Pratama', 'setuju' => '1'])
            ->assertSessionHasNoErrors();

        $order = Order::latest('id')->firstOrFail();

        $this->assertSame($pelanggan->id, $order->customer_id);
        $this->assertSame(1, Customer::count());
    }

    public function test_lacak_manual_mengalihkan_pelanggan_yang_sudah_masuk(): void
    {
        $this->actingAs($this->pelanggan(), 'pelanggan')
            ->get('/lacak')
            ->assertRedirect(route('pesanan-saya'));
    }

    public function test_tawaran_akun_hanya_muncul_bagi_yang_belum_punya(): void
    {
        $this->isiKeranjang();
        $this->post('/checkout', $this->dataCheckout());
        $order = Order::latest('id')->firstOrFail();

        $this->get(route('lacak.token', $order->access_token))
            ->assertSee('Sering memesan di sini?');

        $order->customer->forceFill(['password' => Hash::make('rahasia12345')])->save();

        $this->get(route('lacak.token', $order->access_token))
            ->assertDontSee('Sering memesan di sini?');
    }
}
