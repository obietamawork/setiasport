<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Isian penyesuaian biaya dari gambar karyawan (KEP-124): jenis tambah atau
 * potong, alasan dari daftar tetap, nominal positif. Pelanggan tetap membaca
 * deskripsinya di halaman pesanan (KEP-007).
 */
class PenyesuaianBiayaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);
    }

    private function pesanan(): Order
    {
        $varian = ProductVariant::where('sku', 'BOLA-MITRE-01')->firstOrFail();
        $this->post(route('keranjang.tambah'), ['variant_id' => $varian->id, 'qty' => 1]);
        $this->post('/checkout', ['nama' => 'Budi', 'email' => 'budi@contoh.test', 'no_hp' => '081234567890', 'setuju' => '1']);

        return Order::latest('id')->firstOrFail();
    }

    private function simpan(Order $order, array $isian): TestResponse
    {
        return $this->actingAs(User::where('email', 'owner@setiasport.test')->firstOrFail())
            ->post(route('panel.pesanan.penyesuaian.simpan', $order), $isian);
    }

    public function test_tambah_biaya_tersimpan_positif_dengan_alasan(): void
    {
        $order = $this->pesanan();

        $this->simpan($order, [
            'jenis' => 'tambah', 'alasan' => 'Tambahan desain', 'keterangan' => 'logo sponsor kedua', 'nominal' => 20_000,
        ])->assertSessionHas('sukses');

        $this->assertDatabaseHas('order_adjustments', [
            'order_id' => $order->id, 'deskripsi' => 'Tambahan desain: logo sponsor kedua', 'jumlah' => 20_000,
        ]);
    }

    public function test_potongan_tersimpan_negatif(): void
    {
        $order = $this->pesanan();

        $this->simpan($order, ['jenis' => 'potong', 'alasan' => 'Perubahan spesifikasi', 'nominal' => 15_000])
            ->assertSessionHas('sukses');

        $this->assertDatabaseHas('order_adjustments', [
            'order_id' => $order->id, 'deskripsi' => 'Perubahan spesifikasi', 'jumlah' => -15_000,
        ]);
    }

    public function test_diskon_khusus_selalu_potongan(): void
    {
        $order = $this->pesanan();

        $this->simpan($order, ['jenis' => 'tambah', 'alasan' => 'Diskon khusus', 'nominal' => 10_000])
            ->assertSessionHas('sukses');

        $this->assertSame(-10_000, $order->adjustments()->value('jumlah'));
    }

    public function test_lainnya_wajib_keterangan_dan_keterangannya_jadi_deskripsi(): void
    {
        $order = $this->pesanan();

        $this->simpan($order, ['jenis' => 'tambah', 'alasan' => 'Lainnya', 'nominal' => 5_000])
            ->assertSessionHasErrors('keterangan');
        $this->assertSame(0, $order->adjustments()->count());

        $this->simpan($order, ['jenis' => 'tambah', 'alasan' => 'Lainnya', 'keterangan' => 'Ongkos kirim JNE', 'nominal' => 25_000])
            ->assertSessionHas('sukses');
        $this->assertSame('Ongkos kirim JNE', $order->adjustments()->value('deskripsi'));
    }

    public function test_alasan_di_luar_daftar_dan_nominal_nol_ditolak(): void
    {
        $order = $this->pesanan();

        $this->simpan($order, ['jenis' => 'tambah', 'alasan' => 'Asal', 'nominal' => 0])
            ->assertSessionHasErrors(['alasan', 'nominal']);

        $this->assertSame(0, $order->adjustments()->count());
    }
}
