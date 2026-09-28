<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\ProductVariant;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Permintaan tampilan dari klien (KEP-042): keranjang jadi ikon di sebelah
 * tombol masuk, judul halaman ikut menempel di HP, tautan pesanan bisa disalin,
 * dan bantuan WhatsApp ada tepat di samping tombol terakhir checkout.
 */
class TampilanPublikTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);
    }

    public function test_keranjang_berupa_ikon_dan_mendahului_tombol_masuk(): void
    {
        $this->get(route('katalog'))
            ->assertOk()
            // Namanya tetap terbaca pembaca layar walau yang tampil ikon.
            ->assertSee('<span class="sr-only">Keranjang</span>', false)
            ->assertSeeInOrder(['<span class="sr-only">Keranjang</span>', 'Masuk'], false);
    }

    public function test_judul_halaman_menempel_di_tampilan_hp(): void
    {
        $this->get(route('katalog'))
            ->assertOk()
            ->assertSee('Pilih produk, lihat harganya');

        $this->get(route('beranda'))
            ->assertOk()
            // Halaman depan tidak punya judul kedua; bilahnya tidak boleh muncul kosong.
            ->assertDontSee('sticky top-16', false);
    }

    public function test_tautan_pesanan_bisa_disalin(): void
    {
        $varian = ProductVariant::where('sku', 'BOLA-MITRE-01')->firstOrFail();

        $this->post(route('keranjang.tambah'), ['variant_id' => $varian->id, 'qty' => 1]);
        $this->post('/checkout', [
            'nama' => 'Budi Santoso',
            'email' => 'budi@contoh.test',
            'no_hp' => '081234567890',
            'setuju' => '1',
        ]);

        $order = Order::latest('id')->firstOrFail();

        $this->get(route('lacak.token', $order->access_token))
            ->assertOk()
            ->assertSee('Salin tautan pesanan');
    }

    /**
     * Ukuran dipilih lewat kotak, bukan dropdown (KEP-045). Ukuran yang habis
     * tetap tampil dalam keadaan mati, supaya pembeli tahu ukurannya memang ada
     * tapi sedang kosong.
     */
    public function test_ukuran_dipilih_lewat_kotak_bukan_dropdown(): void
    {
        $produk = \App\Models\Product::where('sku', 'JRS-POLOS-01')->firstOrFail();

        $habis = $produk->variants()->where('ukuran', 'S')->first();
        $habis->update(['stok' => 0]);

        $html = $this->get(route('produk', $produk))->assertOk()->getContent();

        $this->assertStringNotContainsString('<select id="variant_id"', $html);
        $this->assertStringContainsString('pilihVarian(', $html);

        // Ukuran yang habis tetap digambar, tetapi kotaknya mati: sisa stok tiap
        // ukuran ikut dikirim ke halaman, dan nol berarti tidak bisa diklik.
        $this->assertStringContainsString('disabled="stokUntuk(u) < 1"', $html);
        $this->assertSame(0, $habis->fresh()->stok_tersedia);
    }

    /** Produk berharga per ukuran tidak punya tier, dan dulu tampil Rp 0. */
    public function test_produk_harga_per_ukuran_menampilkan_harga_terendah(): void
    {
        $produk = \App\Models\Product::where('sku', 'CUST-JRS-ATASAN')->firstOrFail();

        $this->get(route('produk', $produk))
            ->assertOk()
            ->assertSee(rupiah(45_000))
            ->assertDontSee('Harga per jumlah')
            // Keterangan produk kustom ikut tampil (permintaan klien).
            ->assertSee('Harga mengikuti bahan dan ukuran');
    }

    /**
     * Produk yang harganya diisi lewat matriks per ukuran (KEP-039) sempat
     * tampil "Rp 0" di katalog, karena kartunya hanya membaca tier menurut
     * jumlah. Itu sebagian besar produk klien, dan harga nol di etalase adalah
     * kesalahan yang paling cepat merusak kepercayaan.
     */
    public function test_katalog_kustom_menampilkan_harga_matriks_bukan_nol(): void
    {
        $halaman = $this->get(route('katalog', ['jalur' => 'kustom']))->assertOk();

        $halaman->assertSee(rupiah(45_000));
        $halaman->assertDontSee('Rp 0');
    }

    /** Pelanggan yang ragu di langkah terakhir menutup halaman kalau tidak ada jalan bertanya. */
    public function test_checkout_menawarkan_bantuan_whatsapp(): void
    {
        $varian = ProductVariant::where('sku', 'BOLA-MITRE-01')->firstOrFail();
        $this->post(route('keranjang.tambah'), ['variant_id' => $varian->id, 'qty' => 1]);

        $this->get(route('checkout'))
            ->assertOk()
            ->assertSee('Tanya lewat WhatsApp')
            ->assertSee('wa.me', false);
    }
}
