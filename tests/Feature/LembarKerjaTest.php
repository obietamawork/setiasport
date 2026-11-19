<?php

namespace Tests\Feature;

use App\Enums\OrderItemStatus;
use App\Models\OptionValue;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\AlurPesanan;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Lembar kerja seperti gambar klien (KEP-136): kepala dengan nomor pesanan,
 * status desain, spesifikasi, jumlah pesanan, daftar pemain dengan rekap
 * ukuran, lalu kotak QR besar, catatan, dan pemeriksaan akhir. Petunjuk
 * pindai di bawah kotak-kotak itu, bukan di samping QR.
 */
class LembarKerjaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);
    }

    private function itemProduksi(): OrderItem
    {
        $produk = Product::where('sku', 'CUST-JRS-ATASAN')->firstOrFail();
        $opsi = OptionValue::whereIn('label', ['Serena', 'Oblong', 'Pendek', 'Tanpa Saku'])
            ->whereHas('option', fn ($q) => $q->where('product_id', $produk->id))
            ->get()->mapWithKeys(fn (OptionValue $v) => [$v->product_option_id => $v->id])->all();

        $this->post(route('kustom.simpan', $produk), [
            'opsi' => $opsi, 'desain_sumber' => 'katalog',
            'baris' => [
                ['kategori' => 'SD', 'ukuran' => 'L', 'jumlah' => 1, 'nama_punggung' => 'ANDI', 'nomor' => '10'],
                ['kategori' => 'SD', 'ukuran' => 'M', 'jumlah' => 5],
            ],
        ]);
        $this->post('/checkout', [
            'nama' => 'SSB Garuda', 'email' => 'ssb@contoh.test',
            'no_hp' => '081299990000', 'tipe_pembayaran' => 'DP', 'setuju' => '1',
        ]);

        $order = Order::latest('id')->firstOrFail();
        app(AlurPesanan::class)->terimaPembayaran($order->payments->first());

        $item = $order->items->first();
        $item->update([
            'status_item' => OrderItemStatus::PRINTING, 'approved_at' => now(),
            'approved_by_name' => 'Kapten SSB', 'catatan' => 'Logo di dada kiri',
        ]);

        return $item->fresh();
    }

    public function test_lembar_kerja_seperti_gambar_klien(): void
    {
        $item = $this->itemProduksi();

        $this->actingAs(User::where('email', 'owner@setiasport.test')->firstOrFail())
            ->get(route('panel.pesanan.worksheet', $item->order))
            ->assertOk()
            ->assertSeeInOrder([
                'No. Pesanan', $item->order->kode,
                'Pemesan', 'SSB Garuda',
                'Tanggal Pesan', 'Target Selesai', 'Jenis Pesanan',
                'Status Desain', 'Disetujui pelanggan',
                'Spesifikasi Produk', 'Bahan', 'Serena',
                'Jumlah Pesanan', '6 pcs',
                'Daftar Pemain / Detail Ukuran', 'ANDI',
                'Rekap Ukuran', 'SD M', 'SD L', 'Total',
                'QR Pesanan', 'Catatan', 'Logo di dada kiri', 'Pemeriksaan Akhir (QC)', 'Jumlah sesuai (6 pcs)',
                'Pindai setelah pekerjaan selesai',
            ]);
    }

    public function test_qr_tanpa_tulisan_di_sampingnya(): void
    {
        $item = $this->itemProduksi();

        $html = $this->actingAs(User::where('email', 'owner@setiasport.test')->firstOrFail())
            ->get(route('panel.pesanan.worksheet', $item->order))
            ->getContent();

        // Petunjuk pindai muncul sekali, di bawah deretan kotak, bukan di kotak QR.
        $kotakQr = substr($html, strpos($html, 'QR Pesanan'), strpos($html, 'Pemeriksaan Akhir (QC)') - strpos($html, 'QR Pesanan'));
        $this->assertStringContainsString('<svg', $kotakQr);
        $this->assertStringNotContainsString('Pindai setelah pekerjaan selesai', $kotakQr);
        $this->assertSame(1, substr_count($html, 'Pindai setelah pekerjaan selesai'));
    }

    public function test_nomor_hp_dicetak_tersamar(): void
    {
        $item = $this->itemProduksi();

        $this->actingAs(User::where('email', 'owner@setiasport.test')->firstOrFail())
            ->get(route('panel.pesanan.worksheet', $item->order))
            ->assertSee($item->order->customer->no_hp_samar)
            ->assertDontSee('081299990000');
    }
}
