<?php

namespace Tests\Feature;

use App\Enums\MetodeAmbil;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
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
 * Pengiriman terbatas untuk barang stok toko (KEP-026).
 *
 * Yang paling penting diuji bukan jalan mulusnya, melainkan penjaganya: barang
 * kustom tidak boleh dikirim, paket tidak boleh berangkat sebelum lunas, dan
 * pesanan yang dikirim tidak boleh bisa diserahkan lewat meja kasir.
 */
class PengirimanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);
    }

    private function admin(): User
    {
        return User::where('email', 'admin@setiasport.test')->firstOrFail();
    }

    /** Pesanan stok toko yang sudah siap dan lunas. */
    private function pesananSiap(): Order
    {
        $bola = ProductVariant::where('sku', 'BOLA-MITRE-01')->firstOrFail();

        $this->post('/keranjang', ['variant_id' => $bola->id, 'qty' => 2]);
        $this->post('/checkout', [
            'nama' => 'Budi Santoso',
            'email' => 'budi@contoh.test',
            'no_hp' => '081234567890',
            'setuju' => '1',
        ]);

        $order = Order::latest('id')->firstOrFail();
        $alur = app(AlurPesanan::class);

        $alur->terimaPembayaran($order->payments->first(), $this->admin()->id);

        foreach ($order->fresh('items')->items as $item) {
            while ($item->fresh()->status_item !== OrderItemStatus::SIAP) {
                $alur->majukanItem($item->fresh(), $this->admin()->id);
            }
        }

        return $order->fresh(['items', 'payments', 'adjustments']);
    }

    public function test_pesanan_stok_bisa_disetel_dikirim_lalu_diberi_resi(): void
    {
        $order = $this->pesananSiap();

        $this->actingAs($this->admin())
            ->post(route('panel.pesanan.pengiriman.atur', $order), [
                'metode_ambil' => 'KIRIM',
                'alamat_kirim' => 'Jalan Mawar 5, Baturetno, Wonogiri, 57673',
            ])
            ->assertSessionHas('sukses');

        $this->actingAs($this->admin())
            ->post(route('panel.pesanan.pengiriman.kirim', $order), [
                'jasa_kirim' => 'JNE',
                'no_resi' => 'JNE0012345678',
            ])
            ->assertSessionHas('sukses');

        $order->refresh();

        $this->assertSame('JNE0012345678', $order->no_resi);
        $this->assertNotNull($order->dikirim_pada);

        // Paket sudah berangkat tapi belum sampai: pesanan tetap terhitung aktif.
        $this->assertSame(OrderStatus::DIKIRIM, $order->status);
        $this->assertFalse($order->status->final());
        $this->assertTrue(Order::aktif()->whereKey($order->id)->exists());
    }

    public function test_pesanan_selesai_setelah_dikonfirmasi_diterima(): void
    {
        $order = $this->pesananSiap();
        $order->update(['metode_ambil' => MetodeAmbil::KIRIM, 'alamat_kirim' => 'Jalan Mawar 5']);

        app(AlurPesanan::class)->kirimkan($order->fresh(), 'JNE', 'JNE001', $this->admin()->id);

        $this->actingAs($this->admin())
            ->post(route('panel.pesanan.pengiriman.diterima', $order))
            ->assertSessionHas('sukses');

        $this->assertSame(OrderStatus::SELESAI, $order->fresh()->status);
    }

    public function test_barang_kustom_tidak_boleh_dikirim(): void
    {
        $jersey = Product::where('sku', 'JRS-FUTSAL-FP')->firstOrFail();

        $this->post(route('keranjang.kustom', $jersey), [
            'qty' => $jersey->moq,
            'opsi' => [],
        ]);
        $this->post('/checkout', [
            'nama' => 'Tim Garuda',
            'email' => 'garuda@contoh.test',
            'no_hp' => '081200000000',
            'tipe_pembayaran' => 'DP',
            'setuju' => '1',
        ]);

        $order = Order::latest('id')->firstOrFail();

        $this->actingAs($this->admin())
            ->post(route('panel.pesanan.pengiriman.atur', $order), [
                'metode_ambil' => 'KIRIM',
                'alamat_kirim' => 'Jalan Mawar 5, Wonogiri',
            ])
            ->assertSessionHasErrors();

        $this->assertSame(MetodeAmbil::AMBIL_TOKO, $order->fresh()->metode_ambil);
    }

    public function test_alamat_wajib_diisi_saat_memilih_kirim(): void
    {
        $order = $this->pesananSiap();

        $this->actingAs($this->admin())
            ->post(route('panel.pesanan.pengiriman.atur', $order), [
                'metode_ambil' => 'KIRIM',
                'alamat_kirim' => '   ',
            ])
            ->assertSessionHasErrors();

        $this->assertSame(MetodeAmbil::AMBIL_TOKO, $order->fresh()->metode_ambil);
    }

    public function test_paket_tidak_berangkat_sebelum_lunas(): void
    {
        $bola = ProductVariant::where('sku', 'BOLA-MITRE-01')->firstOrFail();

        $this->post('/keranjang', ['variant_id' => $bola->id, 'qty' => 1]);
        $this->post('/checkout', [
            'nama' => 'Budi', 'email' => 'budi@contoh.test',
            'no_hp' => '081234567890', 'setuju' => '1',
        ]);

        // Belum ada pembayaran yang diverifikasi sama sekali.
        $order = Order::latest('id')->firstOrFail();
        $order->update(['metode_ambil' => MetodeAmbil::KIRIM, 'alamat_kirim' => 'Jalan Mawar 5']);

        $this->actingAs($this->admin())
            ->post(route('panel.pesanan.pengiriman.kirim', $order), [
                'jasa_kirim' => 'JNE',
                'no_resi' => 'JNE001',
            ])
            ->assertSessionHasErrors();

        $this->assertNull($order->fresh()->dikirim_pada);
    }

    public function test_resi_wajib_diisi(): void
    {
        $order = $this->pesananSiap();
        $order->update(['metode_ambil' => MetodeAmbil::KIRIM, 'alamat_kirim' => 'Jalan Mawar 5']);

        $this->actingAs($this->admin())
            ->post(route('panel.pesanan.pengiriman.kirim', $order), ['jasa_kirim' => 'JNE'])
            ->assertSessionHasErrors('no_resi');

        $this->assertNull($order->fresh()->dikirim_pada);
    }

    public function test_pesanan_kirim_tidak_bisa_diserahkan_lewat_kasir(): void
    {
        $order = $this->pesananSiap();
        $order->update(['metode_ambil' => MetodeAmbil::KIRIM, 'alamat_kirim' => 'Jalan Mawar 5']);

        $this->assertFalse($order->fresh(['items', 'payments', 'adjustments'])->bolehDiserahkan());

        $this->actingAs($this->admin())
            ->post(route('panel.pesanan.serah-terima', $order), ['diambil_oleh' => 'Budi'])
            ->assertSessionHasErrors();

        $this->assertNull($order->fresh()->diambil_pada);
    }

    public function test_halaman_pesanan_menampilkan_resi_dan_panduannya(): void
    {
        Setting::simpan('panduan_resi', 'Lacak di cekresi.com.');

        $order = $this->pesananSiap();
        $order->update(['metode_ambil' => MetodeAmbil::KIRIM, 'alamat_kirim' => 'Jalan Mawar 5, Wonogiri']);

        app(AlurPesanan::class)->kirimkan($order->fresh(), 'J&T Express', 'JT9988776655', $this->admin()->id);

        $this->get(route('lacak.token', $order->access_token))
            ->assertOk()
            ->assertSee('JT9988776655')
            ->assertSee('J&amp;T Express', false)
            ->assertSee('Lacak di cekresi.com.')
            ->assertSee('Jalan Mawar 5, Wonogiri')
            ->assertDontSee('Ambil di toko');
    }

    public function test_daftar_jasa_kirim_diatur_dari_panel(): void
    {
        Setting::simpan('jasa_kirim_daftar', "JNE\nSiCepat\n\n  Ninja Xpress  ");

        $this->assertSame(['JNE', 'SiCepat', 'Ninja Xpress'], daftar_setting('jasa_kirim_daftar'));
    }
}
