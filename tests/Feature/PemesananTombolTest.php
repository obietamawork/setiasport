<?php

namespace Tests\Feature;

use App\Enums\FulfillmentType;
use App\Models\OptionValue;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Services\Keranjang;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dua jalan pemesanan, tombol − +, hapus dari keranjang, estimasi hari dari
 * data, dan kaki halaman (KEP-091).
 */
class PemesananTombolTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class, CatalogSeeder::class]);
    }

    private function bola(): ProductVariant
    {
        return ProductVariant::where('sku', 'BOLA-MITRE-01')->firstOrFail();
    }

    private function jerseyTier(): Product
    {
        return Product::where('sku', 'JRS-FUTSAL-FP')->firstOrFail();
    }

    public function test_beli_sekarang_langsung_ke_data_pemesan(): void
    {
        $this->post(route('keranjang.tambah'), ['variant_id' => $this->bola()->id, 'qty' => 2, 'lanjut' => 'checkout'])
            ->assertRedirect(route('checkout'));

        $this->assertSame(2, app(Keranjang::class)->isi()->first()['qty']);
    }

    public function test_masukkan_keranjang_kembali_ke_keranjang(): void
    {
        $this->post(route('keranjang.tambah'), ['variant_id' => $this->bola()->id, 'qty' => 1, 'lanjut' => 'keranjang'])
            ->assertRedirect(route('keranjang'));

        // Tanpa pilihan sama sekali (formulir lama di tab yang belum dimuat ulang) tetap ke keranjang.
        $this->post(route('keranjang.tambah'), ['variant_id' => $this->bola()->id, 'qty' => 1])
            ->assertRedirect(route('keranjang'));
    }

    public function test_pesan_sekarang_untuk_kustom_langsung_ke_data_pemesan(): void
    {
        $jersey = $this->jerseyTier();

        $this->post(route('keranjang.kustom', $jersey), ['qty' => $jersey->moq, 'opsi' => [], 'lanjut' => 'checkout'])
            ->assertRedirect(route('checkout'));

        $produk = Product::where('sku', 'CUST-JRS-ATASAN')->firstOrFail();
        $opsi = OptionValue::whereIn('label', ['Serena', 'Oblong', 'Pendek', 'Tanpa Saku'])
            ->whereHas('option', fn ($q) => $q->where('product_id', $produk->id))
            ->get()
            ->mapWithKeys(fn (OptionValue $v) => [$v->product_option_id => $v->id])
            ->all();

        $this->post(route('kustom.simpan', $produk), [
            'opsi' => $opsi,
            'baris' => [['kategori' => 'SD', 'ukuran' => 'L', 'jumlah' => 6]],
            'desain_sumber' => 'katalog',
            'lanjut' => 'checkout',
        ])->assertRedirect(route('checkout'));
    }

    public function test_halaman_produk_menawarkan_kedua_pilihan_dan_tombol_jumlah(): void
    {
        $this->get(route('produk', 'bola-futsal-mitre-ultimatch'))
            ->assertOk()
            ->assertSeeInOrder(['Beli sekarang', 'Masukkan ke keranjang'])
            ->assertSee('data-langkah-jumlah="1"', false);

        $this->get(route('produk', $this->jerseyTier()))
            ->assertOk()
            ->assertSeeInOrder(['Pesan sekarang', 'Masukkan ke keranjang']);
    }

    /** Dulu ada, tapi berupa tulisan kecil abu-abu yang tidak terbaca sebagai tombol. */
    public function test_barang_bisa_dihapus_dari_keranjang(): void
    {
        $this->post(route('keranjang.tambah'), ['variant_id' => $this->bola()->id, 'qty' => 1]);
        $kunci = app(Keranjang::class)->isi()->first()['kunci'];

        $this->get(route('keranjang'))->assertSee('Hapus');

        $this->delete(route('keranjang.hapus'), ['kunci' => $kunci])->assertSessionHas('sukses');
        $this->assertTrue(app(Keranjang::class)->isi()->isEmpty());
    }

    /** Tombol − berhenti di minimum pesan; server menolak permintaan yang tidak lewat tombol itu. */
    public function test_jumlah_kustom_di_keranjang_tidak_boleh_di_bawah_minimum(): void
    {
        $jersey = $this->jerseyTier();
        $this->post(route('keranjang.kustom', $jersey), ['qty' => $jersey->moq, 'opsi' => []]);
        $kunci = app(Keranjang::class)->isi()->first()['kunci'];

        $this->get(route('keranjang'))->assertSee('min="'.$jersey->moq.'"', false);

        $this->put(route('keranjang.ubah'), ['kunci' => $kunci, 'qty' => $jersey->moq - 1])
            ->assertSessionHasErrors();
        $this->assertSame($jersey->moq, app(Keranjang::class)->isi()->first()['qty']);

        // Dulu dibatasi 999, padahal pesanan kustom boleh sampai 100.000.
        $this->put(route('keranjang.ubah'), ['kunci' => $kunci, 'qty' => 1500])->assertSessionHasNoErrors();
        $this->assertSame(1500, app(Keranjang::class)->isi()->first()['qty']);
    }

    /** "Siap besok" dulu tertulis langsung di lima tampilan. */
    public function test_waktu_siap_barang_stok_diambil_dari_pengaturan(): void
    {
        $this->get(route('produk', 'bola-futsal-mitre-ultimatch'))->assertSee('Siap diambil besok pada jam buka.');

        Setting::simpan('stok_siap_hari', 0);
        $this->get(route('produk', 'bola-futsal-mitre-ultimatch'))->assertSee('Siap diambil hari ini pada jam buka.');
        $this->get(route('katalog'))->assertSee('siap hari ini')->assertSee('bayar dan ambil hari itu juga');

        Setting::simpan('stok_siap_hari', 3);
        $this->get(route('katalog', ['jalur' => 'stok']))->assertSee('Siap 3 hari lagi');
    }

    /** Waktu pengerjaan dari produknya, dan rentang kalau produknya berbeda-beda. */
    public function test_waktu_pengerjaan_kustom_dari_data_produk(): void
    {
        $kustom = Product::whereIn('fulfillment_type', [FulfillmentType::PREORDER->value, FulfillmentType::BOTH->value]);
        (clone $kustom)->update(['lead_time_hari' => 10]);
        $this->jerseyTier()->update(['lead_time_hari' => 21]);

        $this->get(route('beranda'))->assertSee('Dikerjakan 10–21 hari kerja, lalu dilunasi');
        $this->get(route('katalog'))->assertSee('Kustom · 10–21 hari kerja');

        // Tanpa produk kustom yang tayang: tidak ada angka karangan.
        (clone $kustom)->update(['aktif' => false]);
        $this->get(route('katalog'))->assertDontSee('7 hari kerja')->assertDontSee('Kustom · ');
    }

    public function test_kaki_halaman_punya_tombol_whatsapp_dan_petunjuk_arah(): void
    {
        $this->get(route('beranda'))
            ->assertSee(tautan_wa(), false)
            ->assertSee('Chat lewat WhatsApp')
            ->assertSee('Petunjuk arah');
    }
}
