<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Hapus riwayat pesanan dari panel (KEP-119).
 *
 * Klien meminta tempat sampah dan konfirmasi; pemilik toko memutuskan yang
 * boleh dihapus hanya pesanan yang sudah mati dan tidak pernah membawa uang.
 * Menghapus pesanan ikut menghapus pembayarannya, jadi pesanan yang ada
 * uangnya akan membuat laporan penjualan berbohong.
 */
class HapusPesananTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);
    }

    private function owner(): User
    {
        return User::where('email', 'owner@setiasport.test')->firstOrFail();
    }

    private function admin(): User
    {
        return User::where('email', 'admin@setiasport.test')->firstOrFail();
    }

    private function pesanan(OrderStatus $status, ?int $lunas = null): Order
    {
        $order = Order::create([
            'kode' => Order::kodeBaru(),
            'customer_id' => Customer::create([
                'nama' => 'Tes', 'email' => Str::random(6).'@contoh.test', 'no_hp' => '0821'.random_int(1000000, 9999999),
            ])->id,
            'status' => $status,
            'access_token' => Order::tokenBaru(),
            'tipe_pembayaran' => 'LUNAS',
        ]);

        $order->items()->create([
            'product_id' => Product::where('sku', 'CUST-JRS-ATASAN')->value('id'),
            'nama_produk' => 'Jersey Custom', 'fulfillment_type' => 'PREORDER',
            'qty' => 12, 'harga_satuan' => 30_000, 'status_item' => 'DIBATALKAN',
        ]);

        if ($lunas !== null) {
            $order->payments()->create([
                'jenis' => 'FULL', 'metode' => 'TRANSFER', 'status' => PaymentStatus::LUNAS,
                'jumlah' => $lunas, 'dibayar_pada' => now(),
            ]);
        }

        return $order;
    }

    public function test_pesanan_kedaluwarsa_tanpa_uang_dihapus_beserta_isinya(): void
    {
        $order = $this->pesanan(OrderStatus::KADALUARSA);

        $this->actingAs($this->owner())
            ->delete(route('panel.pesanan.hapus', $order))
            ->assertRedirect(route('panel.pesanan.index'))
            ->assertSessionHas('sukses');

        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
        $this->assertDatabaseMissing('order_items', ['order_id' => $order->id]);
        $this->assertTrue(ActivityLog::where('aksi', 'pesanan.dihapus')
            ->where('data_json->kode', $order->kode)->exists());
    }

    public function test_pesanan_dibatalkan_tanpa_uang_boleh_dihapus(): void
    {
        $order = $this->pesanan(OrderStatus::DIBATALKAN);

        $this->actingAs($this->owner())->delete(route('panel.pesanan.hapus', $order))->assertSessionHas('sukses');

        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
    }

    public function test_pesanan_yang_masih_berjalan_tidak_bisa_dihapus(): void
    {
        $order = $this->pesanan(OrderStatus::DIPROSES);

        $this->actingAs($this->owner())
            ->delete(route('panel.pesanan.hapus', $order))
            ->assertSessionHasErrors();

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }

    public function test_pesanan_mati_yang_pernah_membawa_uang_tidak_bisa_dihapus(): void
    {
        $order = $this->pesanan(OrderStatus::DIBATALKAN, lunas: 100_000);

        $this->actingAs($this->owner())
            ->delete(route('panel.pesanan.hapus', $order))
            ->assertSessionHasErrors();

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id]);
    }

    public function test_admin_tanpa_izin_hapus_ditolak(): void
    {
        $order = $this->pesanan(OrderStatus::KADALUARSA);

        $this->actingAs($this->admin())
            ->delete(route('panel.pesanan.hapus', $order))
            ->assertForbidden();

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }

    public function test_hapus_massal_hanya_menghapus_yang_boleh(): void
    {
        $mati = $this->pesanan(OrderStatus::KADALUARSA);
        $batal = $this->pesanan(OrderStatus::DIBATALKAN);
        $berjalan = $this->pesanan(OrderStatus::MENUNGGU_PEMBAYARAN);
        $beruang = $this->pesanan(OrderStatus::KADALUARSA, lunas: 50_000);

        $this->actingAs($this->owner())
            ->post(route('panel.pesanan.hapus-massal'), ['pesanan' => [$mati->id, $batal->id, $berjalan->id, $beruang->id]])
            ->assertSessionHas('sukses', fn (string $pesan) => str_contains($pesan, '2 pesanan dihapus')
                && str_contains($pesan, '2 dilewati'));

        $this->assertDatabaseMissing('orders', ['id' => $mati->id]);
        $this->assertDatabaseMissing('orders', ['id' => $batal->id]);
        $this->assertDatabaseHas('orders', ['id' => $berjalan->id]);
        $this->assertDatabaseHas('orders', ['id' => $beruang->id]);
    }

    public function test_hapus_massal_menolak_pilihan_kosong(): void
    {
        $this->actingAs($this->owner())
            ->post(route('panel.pesanan.hapus-massal'), [])
            ->assertSessionHasErrors('pesanan');
    }

    public function test_berkas_bukti_bayar_ikut_dihapus(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('bukti/x.jpg', 'x');
        $order = $this->pesanan(OrderStatus::KADALUARSA);
        $order->payments()->create([
            'jenis' => 'FULL', 'metode' => 'TRANSFER', 'status' => PaymentStatus::GAGAL,
            'jumlah' => 100_000, 'bukti_path' => 'bukti/x.jpg', 'alasan_tolak' => 'Nominal tidak ada di mutasi',
        ]);

        $this->actingAs($this->owner())->delete(route('panel.pesanan.hapus', $order))->assertSessionHas('sukses');

        Storage::disk('local')->assertMissing('bukti/x.jpg');
    }

    public function test_tempat_sampah_hanya_muncul_di_baris_yang_boleh_dihapus(): void
    {
        $mati = $this->pesanan(OrderStatus::KADALUARSA);
        $berjalan = $this->pesanan(OrderStatus::DIPROSES);

        // Alamat hapus sama dengan alamat detail (hanya metodenya DELETE), jadi
        // yang diperiksa label tombolnya.
        $this->actingAs($this->owner())->get(route('panel.pesanan.index'))
            ->assertOk()
            ->assertSee('Hapus pesanan '.$mati->kode)
            ->assertDontSee('Hapus pesanan '.$berjalan->kode);

        $this->actingAs($this->admin())->get(route('panel.pesanan.index'))
            ->assertOk()
            ->assertDontSee('Hapus pesanan '.$mati->kode);
    }

    public function test_jumlah_baris_per_halaman_bisa_dipilih(): void
    {
        foreach (range(1, 12) as $_) {
            $this->pesanan(OrderStatus::KADALUARSA);
        }

        $this->actingAs($this->owner())->get(route('panel.pesanan.index', ['tampil' => 10]))
            ->assertOk()
            ->assertViewHas('pesanan', fn ($p) => $p->count() === 10 && $p->total() === 12);

        // Angka di luar pilihan kembali ke bawaan, bukan memuat seribu baris.
        $this->actingAs($this->owner())->get(route('panel.pesanan.index', ['tampil' => 1000]))
            ->assertViewHas('pesanan', fn ($p) => $p->perPage() === 20);
    }
}
