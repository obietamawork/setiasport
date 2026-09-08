<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\KesehatanSistem;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Paket penutup: kegagalan senyap, laporan, dan halaman galat.
 */
class PenutupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);
    }

    private function staf(string $email = 'owner@setiasport.test'): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    public function test_penjadwal_yang_mati_muncul_sebagai_masalah(): void
    {
        Cache::forget('denyut_penjadwal');

        $masalah = app(KesehatanSistem::class)->masalah();

        $this->assertNotEmpty($masalah);
        $this->assertStringContainsString('Tugas otomatis berhenti', $masalah[0]['judul']);

        // Pemilik toko harus tahu akibatnya dan apa yang harus dilakukan,
        // bukan sekadar diberi tahu ada yang salah.
        $this->assertStringContainsString('stoknya', $masalah[0]['isi']);
        $this->assertStringContainsString('hosting', $masalah[0]['isi']);
    }

    public function test_penjadwal_yang_hidup_tidak_memunculkan_masalah(): void
    {
        Cache::forever('denyut_penjadwal', now()->toIso8601String());

        $this->assertTrue(app(KesehatanSistem::class)->penjadwalHidup());
        $this->assertSame([], app(KesehatanSistem::class)->masalah());
    }

    public function test_denyut_basi_dianggap_mati(): void
    {
        Cache::forever('denyut_penjadwal', now()->subHour()->toIso8601String());

        $this->assertFalse(app(KesehatanSistem::class)->penjadwalHidup());
    }

    public function test_peringatan_penjadwal_tampil_di_dashboard(): void
    {
        Cache::forget('denyut_penjadwal');

        $this->actingAs($this->staf())
            ->get('/panel')
            ->assertOk()
            ->assertSee('Tugas otomatis berhenti berjalan');
    }

    public function test_halaman_tidak_ditemukan_memakai_tampilan_toko(): void
    {
        $this->get('/alamat-yang-tidak-ada')
            ->assertNotFound()
            ->assertSee('Halaman tidak ditemukan')
            ->assertSee('Buka katalog');
    }

    public function test_laporan_hanya_untuk_pemilik(): void
    {
        $this->actingAs($this->staf('admin@setiasport.test'))->get('/panel/laporan')->assertForbidden();
        $this->actingAs($this->staf())->get('/panel/laporan')->assertOk();
    }

    public function test_laporan_menghitung_uang_yang_benar_benar_diterima(): void
    {
        $varian = ProductVariant::where('sku', 'BOLA-MITRE-01')->firstOrFail();

        $this->post('/keranjang', ['variant_id' => $varian->id, 'qty' => 2]);
        $this->post('/checkout', [
            'nama' => 'Budi', 'email' => 'budi@contoh.test',
            'no_hp' => '081234567890', 'setuju' => '1',
        ]);

        $order = Order::latest('id')->firstOrFail();

        // Belum dibayar: nilai pesanan tercatat, uang diterima belum.
        $this->actingAs($this->staf())->get('/panel/laporan')
            ->assertOk()
            ->assertSee('Rp 570.000')
            ->assertSee('Rp 0');

        $order->payments()->first()->update([
            'status' => PaymentStatus::LUNAS,
            'dibayar_pada' => now(),
        ]);

        // Sesudah dibayar, sisa tagihan tertahan ikut kosong.
        $this->actingAs($this->staf())->get('/panel/laporan')
            ->assertOk()
            ->assertSee('Transfer Bank');
    }

    public function test_katalog_tab_kustom_hanya_menampilkan_produk_kustom(): void
    {
        $this->get('/katalog?jalur=kustom')
            ->assertOk()
            ->assertSee('Untuk berapa pcs?')
            ->assertSee('Jersey Futsal Full Printing')
            ->assertDontSee('Sepatu Futsal Ortuseight Catalyst');
    }

    public function test_katalog_tab_stok_tidak_menampilkan_kolom_jumlah(): void
    {
        $this->get('/katalog?jalur=stok')
            ->assertOk()
            ->assertSee('Sepatu Futsal Ortuseight Catalyst')
            ->assertDontSee('Untuk berapa pcs?');
    }
}
