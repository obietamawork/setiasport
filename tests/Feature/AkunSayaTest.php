<?php

namespace Tests\Feature;

use App\Enums\DesignAssetType;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Models\AlamatPelanggan;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
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
 * Akun saya (KEP-157): profil, ganti kata sandi, dan buku alamat yang
 * langsung dipakai di checkout.
 */
class AkunSayaTest extends TestCase
{
    use RefreshDatabase;

    private Customer $pelanggan;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);
        $this->pelanggan = $this->buatPelanggan('budi@contoh.test');
    }

    private function buatPelanggan(string $email): Customer
    {
        $c = Customer::create(['nama' => 'Budi', 'email' => $email, 'no_hp' => '081234567890']);
        $c->forceFill(['password' => 'sandi-lama-123', 'email_terverifikasi_pada' => now()])->save();

        return $c;
    }

    /** @param  array<string, mixed>  $tambahan */
    private function alamatBaru(array $tambahan = []): void
    {
        $this->actingAs($this->pelanggan, 'pelanggan')->post(route('akun.alamat.simpan'), [
            'label' => 'Rumah', 'alamat' => 'Jl. Mawar No. 5, RT 01/RW 02', 'area_kode' => 'LWY1',
            'area_nama' => 'Laweyan, Surakarta, Jawa Tengah. 57141', 'kode_pos' => '57141', ...$tambahan,
        ])->assertRedirect();
    }

    public function test_tamu_dialihkan_ke_halaman_masuk(): void
    {
        $this->get(route('akun'))->assertRedirect(route('masuk'));
    }

    public function test_halaman_akun_bisa_dibuka_dari_pesanan_saya(): void
    {
        $this->actingAs($this->pelanggan, 'pelanggan')->get(route('pesanan-saya'))->assertSee(route('akun'), false);
        $this->actingAs($this->pelanggan, 'pelanggan')->get(route('akun'))->assertOk()->assertSee('budi@contoh.test');
    }

    public function test_profil_bisa_diubah_tetapi_email_tidak(): void
    {
        $this->actingAs($this->pelanggan, 'pelanggan')->put(route('akun.profil'), [
            'nama' => 'Budi Santoso', 'no_hp' => '085600001111', 'email' => 'ganti@contoh.test',
        ])->assertRedirect();

        $c = $this->pelanggan->fresh();
        $this->assertSame('Budi Santoso', $c->nama);
        $this->assertSame('085600001111', $c->no_hp);
        $this->assertSame('budi@contoh.test', $c->email);
    }

    public function test_ganti_sandi_butuh_sandi_lama_yang_benar(): void
    {
        $this->actingAs($this->pelanggan, 'pelanggan')->put(route('akun.sandi'), [
            'sandi_lama' => 'salah', 'password' => 'sandi-baru-456', 'password_confirmation' => 'sandi-baru-456',
        ])->assertSessionHasErrors('sandi_lama');

        $this->actingAs($this->pelanggan, 'pelanggan')->put(route('akun.sandi'), [
            'sandi_lama' => 'sandi-lama-123', 'password' => 'sandi-baru-456', 'password_confirmation' => 'sandi-baru-456',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('sandi-baru-456', $this->pelanggan->fresh()->password));
    }

    public function test_alamat_pertama_jadi_utama_dan_utama_bisa_dipindah(): void
    {
        $this->alamatBaru();
        $this->alamatBaru(['label' => 'Kantor', 'alamat' => 'Jl. Melati 9']);

        [$rumah, $kantor] = AlamatPelanggan::orderBy('id')->get()->all();
        $this->assertTrue($rumah->utama);
        $this->assertFalse($kantor->utama);

        $this->actingAs($this->pelanggan, 'pelanggan')->post(route('akun.alamat.utama', $kantor))->assertRedirect();

        $this->assertFalse($rumah->fresh()->utama);
        $this->assertTrue($kantor->fresh()->utama);
    }

    public function test_menghapus_alamat_utama_memindahkan_utama_ke_alamat_lain(): void
    {
        $this->alamatBaru();
        $this->alamatBaru(['label' => 'Kantor', 'alamat' => 'Jl. Melati 9']);
        $rumah = AlamatPelanggan::where('label', 'Rumah')->firstOrFail();

        $this->actingAs($this->pelanggan, 'pelanggan')->delete(route('akun.alamat.hapus', $rumah))->assertRedirect();

        $this->assertTrue(AlamatPelanggan::where('label', 'Kantor')->firstOrFail()->utama);
    }

    public function test_alamat_pelanggan_lain_tidak_bisa_disentuh(): void
    {
        $this->alamatBaru();
        $alamat = AlamatPelanggan::firstOrFail();
        $lain = $this->buatPelanggan('lain@contoh.test');

        $this->actingAs($lain, 'pelanggan')->put(route('akun.alamat.ubah', $alamat), ['label' => 'X', 'alamat' => 'Y'])->assertNotFound();
        $this->actingAs($lain, 'pelanggan')->delete(route('akun.alamat.hapus', $alamat))->assertNotFound();
        $this->assertSame(1, AlamatPelanggan::count());
    }

    public function test_paling_banyak_sepuluh_alamat(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->alamatBaru(['label' => "Alamat $i"]);
        }

        $this->actingAs($this->pelanggan, 'pelanggan')->post(route('akun.alamat.simpan'), ['label' => 'Ke-11', 'alamat' => 'Jl. Baru'])
            ->assertSessionHasErrors('alamat');
        $this->assertSame(10, AlamatPelanggan::count());
    }

    public function test_ganti_sandi_memutar_token_ingat_dan_perangkat_ini_tetap_masuk(): void
    {
        $this->actingAs($this->pelanggan, 'pelanggan')->get(route('akun'))->assertOk();

        $this->put(route('akun.sandi'), [
            'sandi_lama' => 'sandi-lama-123', 'password' => 'sandi-baru-456', 'password_confirmation' => 'sandi-baru-456',
        ])->assertSessionHasNoErrors();

        $this->get(route('akun'))->assertOk();
        $this->assertNotNull($this->pelanggan->fresh()->remember_token);
    }

    public function test_sesi_lain_keluar_setelah_sandi_diganti_di_tempat_lain(): void
    {
        $this->actingAs($this->pelanggan, 'pelanggan')->get(route('akun'))->assertOk();

        // Sandi diganti dari perangkat lain atau lewat tautan lupa sandi.
        $this->pelanggan->forceFill(['password' => 'sandi-baru-456'])->save();

        $this->get(route('akun'))->assertRedirect(route('masuk'));
        $this->assertGuest('pelanggan');
    }

    public function test_checkout_mengisi_email_akun_dan_menguncinya(): void
    {
        $varian = ProductVariant::where('sku', 'BOLA-MITRE-01')->firstOrFail();
        $this->actingAs($this->pelanggan, 'pelanggan')->post(route('keranjang.tambah'), ['variant_id' => $varian->id, 'qty' => 1]);

        $this->actingAs($this->pelanggan, 'pelanggan')->get(route('checkout'))
            ->assertOk()
            ->assertSee('name="email" value="budi@contoh.test"', false)
            ->assertSee('readonly', false);
    }

    public function test_checkout_menawarkan_alamat_tersimpan_dan_bisa_menyimpan_alamat_baru(): void
    {
        $this->alamatBaru();
        $varian = ProductVariant::where('sku', 'BOLA-MITRE-01')->firstOrFail();
        $this->actingAs($this->pelanggan, 'pelanggan')->post(route('keranjang.tambah'), ['variant_id' => $varian->id, 'qty' => 1]);

        $this->actingAs($this->pelanggan, 'pelanggan')->get(route('checkout'))
            ->assertOk()->assertSee('Alamat tersimpan')->assertSee('Jl. Mawar No. 5, RT 01/RW 02');

        $this->actingAs($this->pelanggan, 'pelanggan')->post(route('checkout.proses'), [
            'nama' => 'Budi', 'no_hp' => '081234567890', 'metode_ambil' => 'KIRIM', 'setuju' => '1',
            'alamat_kirim' => 'Jl. Kenanga 7, Wonogiri', 'simpan_alamat' => '1',
        ])->assertRedirect();

        $this->assertSame(1, Order::count());
        $this->assertSame(2, $this->pelanggan->alamat()->count());
        $this->assertTrue($this->pelanggan->alamat()->where('alamat', 'Jl. Kenanga 7, Wonogiri')->exists());
    }

    public function test_kartu_pesanan_memakai_foto_produk_barang_pertama(): void
    {
        $produk = Product::where('sku', 'CUST-JRS-ATASAN')->firstOrFail();
        $produk->images()->create(['external_url' => 'https://cdn.contoh.test/jersey.jpg', 'urutan' => 0]);
        $this->pesananUntuk($produk);

        $respons = $this->actingAs($this->pelanggan, 'pelanggan')->get(route('pesanan-saya'));

        $respons->assertOk()->assertSee('src="https://cdn.contoh.test/jersey.jpg"', false);
    }

    public function test_kartu_pesanan_mendahulukan_mockup_yang_disetujui_daripada_foto_produk(): void
    {
        $produk = Product::where('sku', 'CUST-JRS-ATASAN')->firstOrFail();
        $produk->images()->create(['external_url' => 'https://cdn.contoh.test/jersey.jpg', 'urutan' => 0]);
        $item = $this->pesananUntuk($produk);
        $mockup = $item->designAssets()->create(['tipe' => DesignAssetType::MOCKUP, 'versi' => 1, 'path' => 'desain/mockup-tim.jpg']);
        $item->forceFill(['approved_design_asset_id' => $mockup->id])->save();

        $respons = $this->actingAs($this->pelanggan, 'pelanggan')->get(route('pesanan-saya'));

        $respons->assertOk()
            ->assertSee('src="'.asset('storage/desain/mockup-tim.jpg').'"', false)
            ->assertDontSee('https://cdn.contoh.test/jersey.jpg');
    }

    private function pesananUntuk(Product $produk): OrderItem
    {
        $order = Order::create([
            'kode' => Order::kodeBaru(), 'customer_id' => $this->pelanggan->id, 'status' => OrderStatus::DIPROSES,
            'access_token' => Order::tokenBaru(), 'tipe_pembayaran' => 'DP',
        ]);

        return $order->items()->create([
            'product_id' => $produk->id, 'nama_produk' => $produk->nama, 'fulfillment_type' => 'PREORDER',
            'qty' => 6, 'harga_satuan' => 70_000, 'status_item' => OrderItemStatus::DIPROSES,
        ]);
    }
}
