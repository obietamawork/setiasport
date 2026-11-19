<?php

namespace Tests\Feature;

use App\Models\OptionValue;
use App\Models\Product;
use App\Models\ProductVariant;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Revisi teks dari klien untuk cek pesanan dan checkout (KEP-144).
 */
class TeksCheckoutKlienTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);
    }

    private function produk(): Product
    {
        return Product::where('sku', 'CUST-JRS-ATASAN')->firstOrFail();
    }

    private function pesanKustom(): void
    {
        $opsi = OptionValue::whereIn('label', ['Serena', 'Oblong', 'Pendek', 'Tanpa Saku'])
            ->whereHas('option', fn ($q) => $q->where('product_id', $this->produk()->id))
            ->get()->mapWithKeys(fn (OptionValue $v) => [$v->product_option_id => $v->id])->all();

        $this->post(route('kustom.simpan', $this->produk()), [
            'opsi' => $opsi, 'desain_sumber' => 'sendiri',
            'baris' => [['kategori' => 'SD', 'ukuran' => 'L', 'jumlah' => 6]],
        ])->assertSessionHasNoErrors();
    }

    public function test_cek_pesanan_memakai_teks_klien(): void
    {
        $this->get(route('kustom.buat', $this->produk()))
            ->assertOk()
            ->assertSee('Setelah pesanan dibuat, Anda dapat mengakses halaman khusus untuk melihat rincian tagihan, menyetujui desain (mockup), dan memantau progres pengerjaan pesanan.', false);
    }

    public function test_checkout_kustom_memakai_teks_klien(): void
    {
        $this->pesanKustom();

        $this->get(route('checkout'))
            ->assertOk()
            ->assertSee('Pesanan dapat diambil langsung di toko atau dikirim ke alamat tujuan. Pastikan nomor WhatsApp yang Anda cantumkan aktif agar kami dapat menghubungi Anda saat pesanan siap.')
            ->assertSee('Tautan pesanan dikirim melalui email untuk akses tanpa akun, dan dapat dibagikan lewat WhatsApp kepada anggota tim.')
            ->assertSee('Admin akan menghubungi Anda melalui WhatsApp untuk menerima file desain pesanan.')
            ->assertSeeInOrder(['Metode Penerimaan Pesanan', 'Ambil Langsung di Toko', 'Pesanan dapat diambil di Setia Sport Wonogiri tanpa biaya pengiriman.', 'Jl. Manyar', 'Kirim ke Alamat Tujuan', 'Pesanan akan dikirim ke alamat yang Anda cantumkan setelah proses produksi dan pembayaran selesai.'])
            ->assertSee('Cantumkan tautan lokasi Google Maps agar kurir dapat menemukan alamat pengiriman dengan tepat.')
            ->assertSeeInOrder(['Pembayaran awal sebesar 50% (', 'Rp ', ').', 'Sisa pembayaran', 'beserta ongkos kirim', 'dilunasi setelah pesanan selesai, melalui transfer atau langsung di kasir toko.'], false)
            ->assertSeeInOrder(['Buat Akun', 'Buat akun untuk mengelola seluruh pesanan dalam satu halaman. Tautan verifikasi akan dikirim melalui email tanpa menghambat proses pesanan.'])
            ->assertSee('Saya menyetujui batas pembayaran 24 jam sebelum pembatalan otomatis.')
            ->assertSee('Butuh bantuan?')
            ->assertDontSee('Barangnya mau diapakan?')
            ->assertDontSee('Tulis selengkap mungkin')
            ->assertDontSee('Ada yang ingin ditanyakan dulu?');
    }

    public function test_checkout_barang_stok_tidak_menyebut_proses_produksi(): void
    {
        $bola = ProductVariant::where('sku', 'BOLA-MITRE-01')->firstOrFail();
        $this->post('/keranjang', ['variant_id' => $bola->id, 'qty' => 1]);

        $this->get(route('checkout'))
            ->assertOk()
            ->assertSee('Pesanan akan dikirim ke alamat yang Anda cantumkan setelah pembayaran selesai.')
            ->assertDontSee('setelah proses produksi dan pembayaran selesai');
    }
}
