<?php

namespace Tests\Feature;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Models\Customer;
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
 * Pesanan yang diketik admin untuk pembeli offline (KEP-054).
 *
 * Yang dijaga: pesanannya melewati jalan yang sama dengan pesanan web, sehingga
 * dapat halaman lacak, antrian produksi, dan reservasi stok; pelanggan tanpa
 * email tetap bisa dilayani; dan pesanan seperti ini tidak ikut dibatalkan
 * penjadwal kadaluarsa.
 */
class PesananManualTest extends TestCase
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

    private function produk(string $sku = 'CUST-JRS-ATASAN'): Product
    {
        return Product::where('sku', $sku)->with('options.values')->firstOrFail();
    }

    /** @param list<string> $label */
    private function opsi(Product $produk, array $label): array
    {
        return OptionValue::whereIn('label', $label)
            ->whereHas('option', fn ($q) => $q->where('product_id', $produk->id))
            ->get()
            ->mapWithKeys(fn (OptionValue $v) => [$v->product_option_id => $v->id])
            ->all();
    }

    public function test_admin_membuat_pesanan_kustom_untuk_pembeli_di_toko(): void
    {
        $produk = $this->produk();

        $this->actingAs($this->owner())
            ->post(route('panel.pesanan.baru.simpan'), [
                'nama' => 'Alim Sport',
                'no_hp' => '081234567890',
                'product_id' => $produk->id,
                'opsi' => $this->opsi($produk, ['Serena', 'Oblong', 'Pendek', 'Tanpa Saku']),
                'baris' => [
                    ['kategori' => 'DEWASA', 'ukuran' => 'L', 'jumlah' => 12, 'nama_punggung' => 'ANDI', 'nomor' => '7'],
                ],
                'tipe_pembayaran' => 'DP',
                'metode_ambil' => 'AMBIL',
                'catatan' => 'Pesanan reseller, desain menyusul lewat WhatsApp.',
            ])
            ->assertRedirect();

        $order = Order::latest('id')->firstOrFail();
        $item = $order->items->first();

        $this->assertSame(12, $item->qty);
        $this->assertSame(OrderStatus::MENUNGGU_PEMBAYARAN, $order->status);

        // Potongan borongan tetap berlaku, sama seperti pesanan dari web.
        $this->assertSame(60_000, $order->diskon);

        // Dapat halaman lacak bertoken dan antrian produksi seperti pesanan web.
        $this->assertNotNull($order->access_token);
        $this->assertSame(6, $item->productionJobs()->count());
        $this->assertSame(1, $item->rosters()->count());

        $this->get(route('lacak.token', $order->access_token))
            ->assertOk()
            ->assertSee($order->kode);
    }

    /** Pembeli di toko sering hanya punya nomor WhatsApp. */
    public function test_pelanggan_tanpa_email_tetap_bisa_dilayani(): void
    {
        $produk = $this->produk();

        $this->actingAs($this->owner())
            ->post(route('panel.pesanan.baru.simpan'), [
                'nama' => 'Pak Slamet',
                'no_hp' => '081299998888',
                'product_id' => $produk->id,
                'opsi' => $this->opsi($produk, ['Serena', 'Oblong', 'Pendek', 'Tanpa Saku']),
                'baris' => [['kategori' => 'SD', 'ukuran' => 'L', 'jumlah' => 6]],
                'tipe_pembayaran' => 'DP',
                'metode_ambil' => 'AMBIL',
            ])
            ->assertRedirect();

        $customer = Customer::where('no_hp', '081299998888')->firstOrFail();

        $this->assertNull($customer->email);
        $this->assertSame('tanpa email', $customer->email_samar);

        // Halaman pesanannya tetap terbuka, dan tidak ada email yang dikirim.
        $order = Order::latest('id')->firstOrFail();

        $this->get(route('lacak.token', $order->access_token))->assertOk();
        $this->assertSame(0, $order->emailLogs()->count());
    }

    /**
     * Uang yang sudah diterima di kasir dicatat sekalian, dan pesanannya
     * langsung masuk antrian tanpa perlu dibuka lagi.
     */
    public function test_pembayaran_di_kasir_langsung_tercatat(): void
    {
        $produk = $this->produk();

        $this->actingAs($this->owner())
            ->post(route('panel.pesanan.baru.simpan'), [
                'nama' => 'Alim Sport',
                'no_hp' => '081234567890',
                'product_id' => $produk->id,
                'opsi' => $this->opsi($produk, ['Serena', 'Oblong', 'Pendek', 'Tanpa Saku']),
                'baris' => [['kategori' => 'SD', 'ukuran' => 'L', 'jumlah' => 6]],
                'tipe_pembayaran' => 'DP',
                'metode_ambil' => 'AMBIL',
                'dibayar' => 135_000,
                'metode_bayar' => 'TUNAI',
            ])
            ->assertRedirect();

        $order = Order::latest('id')->firstOrFail()->load(['items.options', 'payments', 'adjustments']);

        $this->assertSame(135_000, $order->sudah_dibayar);
        $this->assertSame(1, $order->payments()->count());
        $this->assertSame(OrderItemStatus::ANTRIAN_DESAIN, $order->items->first()->status_item);
    }

    /**
     * Pesanan yang diketik admin tidak punya batas waktu bayar: penjadwal yang
     * membatalkannya tengah malam akan menghapus pesanan yang sedang dikerjakan.
     */
    public function test_pesanan_manual_tidak_ikut_kadaluarsa(): void
    {
        $produk = $this->produk();

        $this->actingAs($this->owner())
            ->post(route('panel.pesanan.baru.simpan'), [
                'nama' => 'Alim Sport',
                'no_hp' => '081234567890',
                'product_id' => $produk->id,
                'opsi' => $this->opsi($produk, ['Serena', 'Oblong', 'Pendek', 'Tanpa Saku']),
                'baris' => [['kategori' => 'SD', 'ukuran' => 'L', 'jumlah' => 6]],
                'tipe_pembayaran' => 'DP',
                'metode_ambil' => 'AMBIL',
            ]);

        $this->assertNull(Order::latest('id')->firstOrFail()->kadaluarsa_pada);
    }

    public function test_barang_stok_ikut_mengunci_reservasinya(): void
    {
        $varian = ProductVariant::where('sku', 'BOLA-MITRE-01')->firstOrFail();
        $dipesanAwal = $varian->stok_dipesan;

        $this->actingAs($this->owner())
            ->post(route('panel.pesanan.baru.simpan'), [
                'nama' => 'Pak Slamet',
                'no_hp' => '081299998888',
                'product_id' => $varian->product_id,
                'variant_id' => $varian->id,
                'qty' => 3,
                'tipe_pembayaran' => 'LUNAS',
                'metode_ambil' => 'AMBIL',
            ])
            ->assertRedirect();

        $this->assertSame($dipesanAwal + 3, $varian->fresh()->stok_dipesan);
    }

    public function test_jumlah_di_bawah_minimum_ditolak_dengan_penjelasan(): void
    {
        $produk = $this->produk();

        $this->actingAs($this->owner())
            ->post(route('panel.pesanan.baru.simpan'), [
                'nama' => 'Alim Sport',
                'no_hp' => '081234567890',
                'product_id' => $produk->id,
                'opsi' => $this->opsi($produk, ['Serena', 'Oblong', 'Pendek', 'Tanpa Saku']),
                'baris' => [['kategori' => 'SD', 'ukuran' => 'L', 'jumlah' => 3]],
                'tipe_pembayaran' => 'DP',
                'metode_ambil' => 'AMBIL',
            ])
            ->assertSessionHasErrors('product_id');

        $this->assertSame(0, Order::count());
    }

    public function test_alamat_wajib_diisi_kalau_dikirim(): void
    {
        $produk = $this->produk();

        $this->actingAs($this->owner())
            ->post(route('panel.pesanan.baru.simpan'), [
                'nama' => 'Alim Sport',
                'no_hp' => '081234567890',
                'product_id' => $produk->id,
                'opsi' => $this->opsi($produk, ['Serena', 'Oblong', 'Pendek', 'Tanpa Saku']),
                'baris' => [['kategori' => 'SD', 'ukuran' => 'L', 'jumlah' => 6]],
                'tipe_pembayaran' => 'DP',
                'metode_ambil' => 'KIRIM',
            ])
            ->assertSessionHasErrors('alamat_kirim');
    }

    public function test_pelanggan_lama_dipakai_ulang_bukan_digandakan(): void
    {
        $produk = $this->produk();

        $kirim = fn () => $this->actingAs($this->owner())
            ->post(route('panel.pesanan.baru.simpan'), [
                'nama' => 'Alim Sport',
                'no_hp' => '081234567890',
                'email' => 'alim@contoh.test',
                'product_id' => $produk->id,
                'opsi' => $this->opsi($produk, ['Serena', 'Oblong', 'Pendek', 'Tanpa Saku']),
                'baris' => [['kategori' => 'SD', 'ukuran' => 'L', 'jumlah' => 6]],
                'tipe_pembayaran' => 'DP',
                'metode_ambil' => 'AMBIL',
            ]);

        $kirim();
        $kirim();

        $this->assertSame(1, Customer::where('email', 'alim@contoh.test')->count());
        $this->assertSame(2, Order::count());
    }

    public function test_staf_tanpa_izin_kelola_tidak_bisa_membuka_formulirnya(): void
    {
        $this->get(route('panel.pesanan.baru'))->assertRedirect(route('panel.login'));
    }
}
