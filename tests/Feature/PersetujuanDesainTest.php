<?php

namespace Tests\Feature;

use App\Enums\DesignAssetType;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Kartu persetujuan desain di halaman pesanan pelanggan (KEP-125), seperti
 * gambar klien: mockup besar, detail pesanan, lipatan nama dan nomor
 * punggung yang bisa diubah, dua pilihan tindakan, dan satu tombol kirim.
 * Nama dan pernyataan tetap diminta saat menyetujui (docs/02).
 */
class PersetujuanDesainTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);
    }

    private function menungguPersetujuan(): OrderItem
    {
        $order = Order::create([
            'kode' => Order::kodeBaru(),
            'customer_id' => Customer::create([
                'nama' => 'Tim Voli', 'email' => Str::random(6).'@contoh.test', 'no_hp' => '081234567890',
            ])->id,
            'status' => OrderStatus::DIPROSES,
            'access_token' => Order::tokenBaru(),
            'tipe_pembayaran' => 'DP',
        ]);

        $item = $order->items()->create([
            'product_id' => Product::where('sku', 'CUST-JRS-ATASAN')->value('id'),
            'nama_produk' => 'Custom Jersey Atasan', 'fulfillment_type' => 'PREORDER',
            'qty' => 6, 'harga_satuan' => 70_000, 'status_item' => OrderItemStatus::MENUNGGU_PERSETUJUAN,
            'catatan' => 'Sesuai brief awal',
        ]);
        $item->options()->create(['option_nama' => 'Bahan', 'option_value' => 'Lotto', 'tambahan_harga' => 0, 'basis' => 'PER_PCS']);
        $item->designAssets()->create(['tipe' => DesignAssetType::MOCKUP, 'versi' => 1, 'path' => 'mockup/tim.jpg']);

        foreach (range(1, 6) as $i) {
            $item->rosters()->create([
                'urutan' => $i, 'nama_punggung' => 'PEMAIN '.$i, 'nomor' => (string) $i,
                'ukuran' => 'M', 'jumlah' => 1, 'harga_satuan' => 70_000,
            ]);
        }

        return $item->fresh();
    }

    public function test_kartu_persetujuan_tampil_seperti_gambar_klien(): void
    {
        $item = $this->menungguPersetujuan();

        $this->get(route('lacak.token', $item->order->access_token))
            ->assertOk()
            ->assertSee('Persetujuan desain')
            ->assertSee('Lihat ukuran penuh')
            ->assertSeeInOrder(['Detail pesanan', 'Produk', 'Custom Jersey Atasan', 'Jumlah', '6 pcs', 'Bahan', 'Lotto', 'Catatan', 'Sesuai brief awal'])
            ->assertSee('Nama &amp; nomor punggung', false)
            ->assertSee('(6 pemain)')
            ->assertSeeInOrder(['Pilih tindakan', 'Setujui desain', 'Ajukan revisi'])
            ->assertSeeText('Revisi ke: 1 dari 3')
            ->assertSee('Kirim keputusan')
            ->assertSee(route('lacak.item.revisi', [$item->order->access_token, $item]))
            ->assertSee('name="nama"', false)
            ->assertSee('name="paham"', false);
    }

    public function test_jatah_revisi_habis_kartu_revisi_nonaktif(): void
    {
        $item = $this->menungguPersetujuan();
        $item->update(['jumlah_revisi' => (int) setting('revisi_gratis_maks', 3)]);

        $this->get(route('lacak.token', $item->order->access_token))
            ->assertOk()
            ->assertSee('Setujui desain')
            ->assertSee('Jatah revisi 3 kali sudah terpakai')
            ->assertDontSee(route('lacak.item.revisi', [$item->order->access_token, $item]));
    }

    public function test_kartu_persetujuan_hilang_setelah_disetujui(): void
    {
        $item = $this->menungguPersetujuan();

        $this->post(route('lacak.item.setujui', [$item->order->access_token, $item]), [
            'nama' => 'Kapten Tim', 'paham' => '1', 'mockup_versi' => 1,
        ])->assertSessionHasNoErrors();

        $this->get(route('lacak.token', $item->order->access_token))
            ->assertOk()
            ->assertDontSee('Kirim keputusan')
            ->assertSee('Disetujui oleh');
    }
}
