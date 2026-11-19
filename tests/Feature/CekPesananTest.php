<?php

namespace Tests\Feature;

use App\Models\Product;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Langkah Cek pesanan di konfigurator kustom (KEP-133): tabel Detail pesanan
 * bernomor seperti gambar klien, dengan kalimat pengingat sebelum membayar.
 */
class CekPesananTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);
    }

    public function test_langkah_cek_pesanan_memakai_tabel_detail_pesanan(): void
    {
        $produk = Product::where('sku', 'CUST-SETELAN')->firstOrFail();

        $this->get(route('kustom.buat', $produk))
            ->assertOk()
            ->assertSeeInOrder(['Cek pesanan', 'Detail pesanan', 'No.', 'Nama / Nomor', 'Ukuran', 'Jumlah', 'Harga'])
            ->assertSee('Pastikan detail pesanan sudah benar sebelum lanjut ke pembayaran.');
    }
}
