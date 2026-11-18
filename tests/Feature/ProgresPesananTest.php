<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Halaman progres pesanan dari revisi klien (KEP-122): ringkas, bertahap,
 * dengan tanda pensil pada bagian yang masih bisa diubah pelanggan.
 */
class ProgresPesananTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);
    }

    private function pesanan(string $statusItem = 'JAHIT', bool $disetujui = true, int $pemain = 7): OrderItem
    {
        $order = Order::create([
            'kode' => Order::kodeBaru(),
            'customer_id' => Customer::create([
                'nama' => 'Tim Voli Putra', 'email' => Str::random(6).'@contoh.test', 'no_hp' => '081234567890',
            ])->id,
            'status' => OrderStatus::DIPROSES,
            'access_token' => Order::tokenBaru(),
            'tipe_pembayaran' => 'DP',
            'target_siap_pada' => '2026-11-20',
        ]);

        $item = $order->items()->create([
            'product_id' => Product::where('sku', 'CUST-JRS-ATASAN')->value('id'),
            'nama_produk' => 'Custom Jersey Voli Putra', 'fulfillment_type' => 'PREORDER',
            'qty' => $pemain, 'harga_satuan' => 30_000, 'status_item' => $statusItem,
            'catatan' => 'Warna navy biru, logo di dada kiri.',
            'approved_at' => $disetujui ? now() : null,
        ]);

        $item->options()->create(['option_nama' => 'Bahan', 'option_value' => 'Dryfit', 'tambahan_harga' => 0, 'basis' => 'PER_PCS']);

        foreach (range(1, $pemain) as $i) {
            $item->rosters()->create([
                'urutan' => $i, 'nama_punggung' => 'PEMAIN '.$i, 'nomor' => (string) $i,
                'ukuran' => 'L', 'jumlah' => 1, 'harga_satuan' => 30_000,
            ]);
        }

        return $item->fresh();
    }

    public function test_kartu_progres_menampilkan_tahap_dan_detail(): void
    {
        $item = $this->pesanan();

        $this->get(route('lacak.token', $item->order->access_token))
            ->assertOk()
            ->assertSee('Progres pesanan')
            ->assertSee('Custom Jersey Voli Putra')
            ->assertSee('Pesanan dibuat')
            ->assertSee('Detail pesanan')
            ->assertSeeInOrder(['Bahan', 'Dryfit'])
            ->assertSee('Estimasi selesai')
            ->assertSee('20 Nov 2026');
    }

    public function test_ringkasan_pembayaran_menyebut_uang_muka_dan_sisa(): void
    {
        $item = $this->pesanan();
        $item->order->payments()->create([
            'jenis' => 'DP', 'metode' => 'TRANSFER', 'status' => PaymentStatus::LUNAS,
            'jumlah' => 100_000, 'dibayar_pada' => now(),
        ]);

        $this->get(route('lacak.token', $item->order->access_token))
            ->assertOk()
            ->assertSeeInOrder(['Ringkasan pembayaran', 'Total pesanan', rupiah(210_000), 'Uang muka', rupiah(100_000), 'Lunas', 'Sisa pelunasan', rupiah(110_000)]);
    }

    public function test_daftar_pemain_ringkas_dengan_pensil_selama_bisa_diubah(): void
    {
        $item = $this->pesanan('PROSES_DESAIN', disetujui: false);

        $this->get(route('lacak.token', $item->order->access_token))
            ->assertOk()
            ->assertSee('Total 7 pemain')
            ->assertSee('Lihat seluruhnya')
            ->assertSee('Ubah data PEMAIN 1');
    }

    public function test_pensil_hilang_setelah_desain_disetujui(): void
    {
        $item = $this->pesanan('JAHIT', disetujui: true);

        $this->get(route('lacak.token', $item->order->access_token))
            ->assertOk()
            ->assertSee('Total 7 pemain')
            ->assertDontSee('Ubah data PEMAIN 1')
            ->assertDontSee('Ubah catatan');
    }

    public function test_catatan_bisa_diubah_sebelum_desain_disetujui(): void
    {
        $item = $this->pesanan('PROSES_DESAIN', disetujui: false);

        $this->post(route('lacak.item.catatan', [$item->order->access_token, $item]), [
            'catatan' => 'Nomor belakang dibuat besar.',
        ])->assertSessionHas('sukses');

        $this->assertSame('Nomor belakang dibuat besar.', $item->fresh()->catatan);
    }

    public function test_catatan_terkunci_setelah_desain_disetujui(): void
    {
        $item = $this->pesanan('JAHIT', disetujui: true);

        $this->post(route('lacak.item.catatan', [$item->order->access_token, $item]), [
            'catatan' => 'Ganti warna jadi merah.',
        ])->assertSessionHasErrors();

        $this->assertSame('Warna navy biru, logo di dada kiri.', $item->fresh()->catatan);
    }

    public function test_catatan_tidak_bisa_diubah_lewat_tautan_pesanan_lain(): void
    {
        $item = $this->pesanan('PROSES_DESAIN', disetujui: false);
        $lain = $this->pesanan('PROSES_DESAIN', disetujui: false);

        $this->post(route('lacak.item.catatan', [$lain->order->access_token, $item]), [
            'catatan' => 'Disusupkan dari pesanan lain.',
        ])->assertNotFound();

        $this->assertSame('Warna navy biru, logo di dada kiri.', $item->fresh()->catatan);
    }
}
