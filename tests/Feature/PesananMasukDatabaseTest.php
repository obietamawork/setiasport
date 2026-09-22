<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\OptionValue;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Satu pesanan diikuti dari checkout sampai diserahkan, dan setiap langkahnya
 * diperiksa LANGSUNG DI TABELNYA, bukan lewat tampilan.
 *
 * Tes lain memeriksa aturan dan halaman; yang ini menjawab satu pertanyaan yang
 * sering ditanyakan pemilik toko dengan wajar: "apa betul semuanya tersimpan?"
 * Karena itu isinya sengaja memakai assertDatabaseHas, dengan nama kolom
 * sebenarnya, supaya kalau suatu hari ada yang lupa disimpan, yang gagal adalah
 * tes ini, bukan pemilik toko yang kehilangan data pesanan.
 */
class PesananMasukDatabaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);
    }

    private function owner(): User
    {
        return User::where('email', 'owner@setiasport.test')->firstOrFail();
    }

    private function produkKustom(): Product
    {
        return Product::where('sku', 'CUST-JRS-ATASAN')->firstOrFail();
    }

    /** @return array<int, int> */
    private function opsi(string ...$label): array
    {
        $produk = $this->produkKustom();

        return OptionValue::whereIn('label', $label)
            ->whereHas('option', fn ($q) => $q->where('product_id', $produk->id))
            ->get()
            ->mapWithKeys(fn (OptionValue $v) => [$v->product_option_id => $v->id])
            ->all();
    }

    /**
     * Jalur lengkap pesanan kustom dari web: keranjang, checkout, lalu seluruh
     * jejaknya di database.
     */
    public function test_pesanan_kustom_dari_web_tersimpan_lengkap(): void
    {
        $produk = $this->produkKustom();

        $this->post(route('kustom.simpan', $produk), [
            'opsi' => $this->opsi('Serena', 'Oblong', 'Pendek', 'Tanpa Saku'),
            'baris' => [
                ['kategori' => 'DEWASA', 'ukuran' => 'L', 'jumlah' => 8, 'nama_punggung' => 'ANDI', 'nomor' => '10'],
                ['kategori' => 'DEWASA', 'ukuran' => 'XL', 'jumlah' => 4],
            ],
            'desain_sumber' => 'katalog',
        ])->assertRedirect(route('keranjang'));

        $this->post('/checkout', [
            'nama' => 'Pak Budi', 'email' => 'budi@contoh.test', 'no_hp' => '081234567890',
            'catatan' => 'Ditunggu sebelum turnamen.', 'tipe_pembayaran' => 'DP', 'setuju' => '1',
        ])->assertRedirect();

        $order = Order::latest('id')->firstOrFail();

        // 1. Pelanggan: dibuat dari data checkout, tanpa akun.
        $this->assertDatabaseHas('customers', [
            'email' => 'budi@contoh.test', 'nama' => 'Pak Budi', 'no_hp' => '081234567890',
        ]);

        // 2. Pesanan: nomor, token halaman lacak, tipe pembayaran, catatan.
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'customer_id' => $order->customer_id,
            'status' => OrderStatus::MENUNGGU_PEMBAYARAN->value,
            'tipe_pembayaran' => 'DP',
            'catatan' => 'Ditunggu sebelum turnamen.',
        ]);
        $this->assertNotNull($order->access_token);
        $this->assertNotNull($order->kadaluarsa_pada, 'Pesanan dari web punya batas waktu bayar.');

        // 3. Barangnya, berikut jumlah yang dijumlahkan dari daftar ukuran.
        $item = $order->items()->firstOrFail();
        $this->assertDatabaseHas('order_items', [
            'id' => $item->id,
            'order_id' => $order->id,
            'product_id' => $produk->id,
            'qty' => 12,
            'fulfillment_type' => 'PREORDER',
            // Catatan barang menyimpan asal desainnya, bukan catatan pemesan;
            // catatan pemesan menempel di pesanannya (diperiksa di atas).
            'catatan' => 'Desain dipilih dari katalog.',
        ]);

        // 4. Daftar pemain: nama punggung, nomor, ukuran, dan harga yang dibekukan.
        $this->assertDatabaseHas('order_item_rosters', [
            'order_item_id' => $item->id, 'ukuran' => 'L', 'jumlah' => 8,
            'nama_punggung' => 'ANDI', 'nomor' => '10',
        ]);
        $this->assertDatabaseHas('order_item_rosters', [
            'order_item_id' => $item->id, 'ukuran' => 'XL', 'jumlah' => 4,
        ]);
        $this->assertSame(12, (int) $item->rosters()->sum('jumlah'));
        $this->assertGreaterThan(0, (int) $item->rosters()->first()->harga_satuan);

        // 5. Opsi yang dipilih ikut dibekukan beserta tambahan harganya, supaya
        //    rinciannya tetap bisa dijelaskan setelah daftar harga berubah.
        $this->assertDatabaseHas('order_item_options', [
            'order_item_id' => $item->id, 'option_value' => 'Serena',
        ]);

        // 6. Tagihan pertama dibuat menunggu, sebesar DP minimum.
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'jenis' => 'DP',
            'status' => PaymentStatus::MENUNGGU->value,
            'jumlah' => $order->dpMinimum(),
        ]);

        // 7. Antrian produksi dibuatkan sejak pesanan masuk, bukan nanti.
        $this->assertDatabaseHas('production_jobs', [
            'order_item_id' => $item->id, 'tahap' => 'DESAIN',
        ]);
        $this->assertGreaterThanOrEqual(5, $item->productionJobs()->count());

        // 8. Riwayat status: baris pertama menjelaskan asal pesanan.
        $this->assertDatabaseHas('order_status_logs', [
            'order_id' => $order->id,
            'ke' => OrderStatus::MENUNGGU_PEMBAYARAN->value,
            'catatan' => 'Pesanan dibuat pelanggan.',
        ]);
    }

    /** Pesanan barang stok menahan stoknya di gudang sejak pesanan dibuat. */
    public function test_pesanan_stok_menahan_stok_di_database(): void
    {
        $varian = ProductVariant::where('sku', 'BOLA-MITRE-01')->firstOrFail();
        $stokAwal = $varian->stok;
        $dipesanAwal = $varian->stok_dipesan;

        $this->post(route('keranjang.tambah'), ['variant_id' => $varian->id, 'qty' => 2]);
        $this->post('/checkout', [
            'nama' => 'Rian', 'email' => 'rian@contoh.test', 'no_hp' => '081298765432', 'setuju' => '1',
        ])->assertRedirect();

        // Stok fisik belum berkurang, tetapi sudah dikunci untuk pesanan ini.
        $this->assertDatabaseHas('product_variants', [
            'id' => $varian->id,
            'stok' => $stokAwal,
            'stok_dipesan' => $dipesanAwal + 2,
        ]);
    }

    /**
     * Pembayaran yang diverifikasi mengubah tiga tabel sekaligus: pembayaran,
     * pesanan, dan riwayatnya. Kalau salah satu tertinggal, pelanggan melihat
     * status yang tidak cocok dengan uang yang sudah masuk.
     */
    public function test_verifikasi_pembayaran_mengubah_pesanan_dan_riwayatnya(): void
    {
        $varian = ProductVariant::where('sku', 'BOLA-MITRE-01')->firstOrFail();
        $this->post(route('keranjang.tambah'), ['variant_id' => $varian->id, 'qty' => 1]);
        $this->post('/checkout', [
            'nama' => 'Rian', 'email' => 'rian@contoh.test', 'no_hp' => '081298765432', 'setuju' => '1',
        ]);

        $order = Order::latest('id')->firstOrFail();
        $bayar = $order->payments()->firstOrFail();

        $this->actingAs($this->owner())
            ->post(route('panel.pesanan.pembayaran.verifikasi', [$order, $bayar]), [
                'metode' => 'TRANSFER',
                // Centang pernyataan mutasi: bukti bukan verifikasi (KEP-029).
                'cek_mutasi' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('payments', [
            'id' => $bayar->id,
            'status' => PaymentStatus::LUNAS->value,
            'diverifikasi_oleh' => $this->owner()->id,
        ]);

        $this->assertDatabaseMissing('orders', [
            'id' => $order->id,
            'status' => OrderStatus::MENUNGGU_PEMBAYARAN->value,
        ]);

        $this->assertDatabaseHas('order_status_logs', [
            'order_id' => $order->id,
            'dari' => OrderStatus::MENUNGGU_PEMBAYARAN->value,
        ]);
    }

    /** Pesanan yang diketik admin di toko menempuh jalan yang sama (KEP-054). */
    public function test_pesanan_manual_admin_tersimpan_seperti_pesanan_web(): void
    {
        $produk = $this->produkKustom();

        $this->actingAs($this->owner())->post(route('panel.pesanan.baru.simpan'), [
            'nama' => 'SSB Garuda',
            'email' => 'garuda@contoh.test',
            'no_hp' => '081299990000',
            'product_id' => $produk->id,
            'opsi' => $this->opsi('Serena', 'Oblong', 'Pendek', 'Tanpa Saku'),
            'baris' => [['kategori' => 'DEWASA', 'ukuran' => 'M', 'jumlah' => 10]],
            'metode_ambil' => 'AMBIL',
            'tipe_pembayaran' => 'LUNAS',
            'uang_diterima' => 0,
        ])->assertRedirect();

        $order = Order::latest('id')->firstOrFail();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            // Pesanan yang diketik admin tidak ikut dibatalkan penjadwal tengah malam.
            'kadaluarsa_pada' => null,
        ]);

        $this->assertDatabaseHas('order_status_logs', [
            'order_id' => $order->id,
            'catatan' => 'Pesanan diketik admin di panel.',
        ]);

        $this->assertDatabaseHas('order_item_rosters', [
            'order_item_id' => $order->items()->value('id'),
            'ukuran' => 'M',
            'jumlah' => 10,
        ]);
    }

    /** Penyesuaian biaya masuk tabelnya sendiri dan ikut mengubah total. */
    public function test_penyesuaian_biaya_tercatat_dan_mengubah_total(): void
    {
        $varian = ProductVariant::where('sku', 'BOLA-MITRE-01')->firstOrFail();
        $this->post(route('keranjang.tambah'), ['variant_id' => $varian->id, 'qty' => 1]);
        $this->post('/checkout', [
            'nama' => 'Rian', 'email' => 'rian@contoh.test', 'no_hp' => '081298765432', 'setuju' => '1',
        ]);

        $order = Order::latest('id')->firstOrFail();
        $sebelum = $order->total;

        $this->actingAs($this->owner())
            ->post(route('panel.pesanan.penyesuaian.simpan', $order), [
                'deskripsi' => 'Ongkos kirim JNE', 'jumlah' => 25_000,
            ])->assertRedirect();

        $this->assertDatabaseHas('order_adjustments', [
            'order_id' => $order->id,
            'deskripsi' => 'Ongkos kirim JNE',
            'jumlah' => 25_000,
            'dibuat_oleh' => $this->owner()->id,
        ]);

        $this->assertSame($sebelum + 25_000, $order->fresh(['items', 'adjustments', 'payments'])->total);
    }
}
