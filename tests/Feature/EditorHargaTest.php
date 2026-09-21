<?php

namespace Tests\Feature;

use App\Enums\KategoriUkuran;
use App\Models\OptionValue;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Matriks harga, panduan ukuran, dan tambahan ukuran diisi dari panel (KEP-052).
 *
 * Sebelum ini ketiganya hanya bisa diisi lewat seeder, artinya tiap perubahan
 * harga harus melewati pengembang. Yang diuji di sini: pemilik toko benar-benar
 * bisa mengubahnya sendiri, dan hasilnya langsung dipakai kalkulator harga.
 */
class EditorHargaTest extends TestCase
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

    private function produk(string $sku = 'CUST-JRS-ATASAN'): Product
    {
        return Product::where('sku', $sku)
            ->with(['prices', 'sizeCharts', 'sizeSurcharges', 'options.values'])
            ->firstOrFail();
    }

    private function bahan(Product $produk, string $label): OptionValue
    {
        return OptionValue::whereIn('label', [$label])
            ->whereHas('option', fn ($q) => $q->where('product_id', $produk->id)->where('nama', 'Bahan'))
            ->firstOrFail();
    }

    public function test_pemilik_toko_mengubah_harga_dari_panel(): void
    {
        $produk = $this->produk();
        $serena = $this->bahan($produk, 'Serena');
        $opsiBahan = $produk->options->firstWhere('nama', 'Bahan');

        $this->actingAs($this->owner())
            ->post(route('panel.produk.matriks.simpan', $produk), [
                'opsi_id' => $opsiBahan->id,
                'harga' => ['SD' => [$serena->id => 47_000]],
            ])
            ->assertSessionHas('sukses');

        $this->assertSame(47_000, ProductPrice::where('product_id', $produk->id)
            ->where('kategori', KategoriUkuran::SD->value)
            ->where('option_value_id', $serena->id)
            ->value('harga_satuan'));

        // Harga baru langsung dipakai menghitung pesanan.
        $rincian = app(\App\Services\PriceCalculator::class)->kustomPerUkuran(
            $this->produk(),
            [['kategori' => 'SD', 'ukuran' => 'L', 'jumlah' => 6]],
            [$serena->id],
        );

        $this->assertSame(47_000, $rincian->baris[0]['harga_satuan']);
    }

    /** Sel kosong berarti kombinasi itu tidak dijual. */
    public function test_mengosongkan_sel_menghapus_harganya(): void
    {
        $produk = $this->produk();
        $serena = $this->bahan($produk, 'Serena');

        $this->actingAs($this->owner())
            ->post(route('panel.produk.matriks.simpan', $produk), [
                'opsi_id' => $produk->options->firstWhere('nama', 'Bahan')->id,
                'harga' => ['SD' => [$serena->id => '']],
            ])
            ->assertSessionHas('sukses');

        $this->assertDatabaseMissing('product_prices', [
            'product_id' => $produk->id,
            'kategori' => KategoriUkuran::SD->value,
            'option_value_id' => $serena->id,
        ]);
    }

    /**
     * MySQL menganggap dua NULL berbeda di indeks unik, jadi baris "berlaku
     * semua kategori" gampang tergandakan diam-diam kalau disimpan ceroboh.
     */
    public function test_menyimpan_dua_kali_tidak_menggandakan_baris_semua_kategori(): void
    {
        $produk = $this->produk('CUST-CELANA-PENDEK');
        $lotto = $this->bahan($produk, 'Lotto');
        $opsi = $produk->options->firstWhere('nama', 'Bahan')->id;

        foreach ([36_000, 37_000] as $harga) {
            $this->actingAs($this->owner())
                ->post(route('panel.produk.matriks.simpan', $produk), [
                    'opsi_id' => $opsi,
                    'harga' => ['SEMUA' => [$lotto->id => $harga]],
                ])
                ->assertSessionHas('sukses');
        }

        $baris = ProductPrice::where('product_id', $produk->id)
            ->whereNull('kategori')
            ->where('option_value_id', $lotto->id)
            ->get();

        $this->assertCount(1, $baris);
        $this->assertSame(37_000, $baris->first()->harga_satuan);
    }

    public function test_panduan_ukuran_diisi_dan_langsung_membatasi_pesanan(): void
    {
        $produk = $this->produk('CUST-JAKET');

        $this->actingAs($this->owner())
            ->post(route('panel.produk.panduan.simpan', $produk), [
                'kategori' => KategoriUkuran::DEWASA->value,
                'kolom' => ['Lingkar dada', 'Panjang'],
                'baris' => [
                    ['ukuran' => 'M', 'nilai' => [96, 68]],
                    ['ukuran' => 'L', 'nilai' => [100, 70]],
                ],
            ])
            ->assertSessionHas('sukses');

        $produk = $this->produk('CUST-JAKET');

        $this->assertSame(['M', 'L'], $produk->ukuranTersedia(KategoriUkuran::DEWASA));
        // Urutan kolom harus persis seperti yang diketik pemilik toko.
        $panduan = $produk->sizeCharts->first();

        $this->assertSame(['Lingkar dada', 'Panjang'], $panduan->kolom());
        $this->assertSame(96, $panduan->nilai('Lingkar dada'));
        $this->assertSame(68, $panduan->nilai('Panjang'));

        // Ukuran di luar panduan langsung ditolak kalkulator harga.
        $this->expectException(\InvalidArgumentException::class);

        app(\App\Services\PriceCalculator::class)->kustomPerUkuran(
            $produk,
            [['kategori' => 'DEWASA', 'ukuran' => '5XL', 'jumlah' => 6]],
            [$this->bahan($produk, 'Lotto')->id],
        );
    }

    /** Mengirim daftar kosong mengembalikan produk ke daftar ukuran bawaan. */
    public function test_panduan_yang_dikosongkan_kembali_ke_bawaan(): void
    {
        $produk = $this->produk('CUST-CELANA-PENDEK');

        $this->assertSame(['S', 'M', 'L', 'XL', '2XL', '3XL'], $produk->ukuranTersedia(null));

        $this->actingAs($this->owner())
            ->post(route('panel.produk.panduan.simpan', $produk), [
                'kategori' => '',
                'kolom' => ['Lebar', 'Panjang'],
                'baris' => [],
            ])
            ->assertSessionHas('sukses');

        $this->assertSame(
            KategoriUkuran::DEWASA->ukuran(),
            $this->produk('CUST-CELANA-PENDEK')->ukuranTersedia(null)
        );
    }

    public function test_tambahan_ukuran_besar_bisa_ditambah_dan_dihapus(): void
    {
        $produk = $this->produk('CUST-JAKET');

        $this->actingAs($this->owner())
            ->post(route('panel.produk.tambahan.simpan', $produk), [
                'ukuran' => '3xl',
                'tambahan_harga' => 15_000,
            ])
            ->assertSessionHas('sukses');

        $tambahan = $this->produk('CUST-JAKET')->sizeSurcharges->firstWhere('ukuran', '3XL');

        $this->assertNotNull($tambahan);
        $this->assertSame(15_000, $tambahan->tambahan_harga);

        $this->actingAs($this->owner())
            ->delete(route('panel.produk.tambahan.hapus', [$produk, $tambahan]))
            ->assertSessionHas('sukses');

        $this->assertSame(0, $this->produk('CUST-JAKET')->sizeSurcharges->count());
    }

    public function test_opsi_milik_produk_lain_ditolak(): void
    {
        $produk = $this->produk();
        $lain = $this->produk('CUST-JAKET');

        $this->actingAs($this->owner())
            ->post(route('panel.produk.matriks.simpan', $produk), [
                'opsi_id' => $lain->options->first()->id,
                'harga' => ['SD' => [$this->bahan($lain, 'Lotto')->id => 99_000]],
            ])
            ->assertNotFound();
    }

    public function test_halaman_produk_memuat_ketiga_editornya(): void
    {
        $this->actingAs($this->owner())
            ->get(route('panel.produk.edit', $this->produk()))
            ->assertOk()
            ->assertSee('Matriks harga per ukuran')
            ->assertSee('Panduan ukuran')
            ->assertSee('Tambahan ukuran besar');
    }
}
