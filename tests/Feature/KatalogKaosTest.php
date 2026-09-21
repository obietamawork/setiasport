<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\OptionValue;
use App\Models\Product;
use App\Services\PriceCalculator;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Seragam olahraga dan tiga jenis kaos, dari daftar harga klien (KEP-053).
 *
 * Angkanya disalin apa adanya. Yang diuji di sini kombinasi yang paling mudah
 * meleset: lengan panjang menambah Rp10.000 di hampir semua produk, tetapi
 * hanya Rp5.000 di kaos katun.
 */
class KatalogKaosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);
    }

    private function produk(string $sku): Product
    {
        return Product::where('sku', $sku)
            ->with(['prices', 'sizeSurcharges', 'sizeCharts', 'options.values'])
            ->firstOrFail();
    }

    /** @param list<string> $label */
    private function harga(
        string $sku,
        array $label,
        string $kategori,
        int $jumlah = 24,
        string $ukuran = 'L',
    ): int {
        $produk = $this->produk($sku);

        $opsi = OptionValue::whereIn('label', $label)
            ->whereHas('option', fn ($q) => $q->where('product_id', $produk->id))
            ->pluck('id')
            ->all();

        return app(PriceCalculator::class)->kustomPerUkuran(
            $produk,
            [['kategori' => $kategori, 'ukuran' => $ukuran, 'jumlah' => $jumlah]],
            $opsi,
        )->baris[0]['harga_satuan'];
    }

    /** Daftar harga klien: seragam PE Lotto SD kaos pendek = Rp65.000. */
    public function test_seragam_mengikuti_daftar_harga(): void
    {
        $this->assertSame(65_000, $this->harga('CUST-SERAGAM', ['PE Printing Lotto', 'Pendek'], 'SD'));
        $this->assertSame(115_000, $this->harga('CUST-SERAGAM', ['Katun Diadora', 'Pendek'], 'DEWASA'));

        // Kaos panjang + training panjang, SMA, Katun Diadora = Rp125.000.
        $this->assertSame(125_000, $this->harga('CUST-SERAGAM', ['Katun Diadora', 'Panjang'], 'DEWASA'));
    }

    public function test_seragam_minimal_dua_puluh_empat_set(): void
    {
        $this->assertSame(24, $this->produk('CUST-SERAGAM')->moq);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Minimum pemesanan');

        $this->harga('CUST-SERAGAM', ['PE Printing Lotto', 'Pendek'], 'SD', 12);
    }

    /** Kaos berkrah tidak dipisah jenjang: satu harga untuk semua umur. */
    public function test_kaos_berkrah_satu_harga_semua_umur(): void
    {
        $this->assertFalse($this->produk('CUST-KAOS-KERAH')->pakaiKategoriUkuran());

        $this->assertSame(35_000, $this->harga('CUST-KAOS-KERAH', ['PE', 'Pendek'], '', 6));
        $this->assertSame(80_000, $this->harga('CUST-KAOS-KERAH', ['Lakos CVC', 'Panjang'], '', 6));
    }

    public function test_kaos_oblong_mengikuti_daftar_harga(): void
    {
        $this->assertSame(25_000, $this->harga('CUST-KAOS-OBLONG', ['PE', 'Pendek'], '', 6));
        $this->assertSame(40_000, $this->harga('CUST-KAOS-OBLONG', ['PE 20 (Semi Katun)', 'Panjang'], '', 6));
    }

    /**
     * Inilah yang paling mudah meleset: lengan panjang di kaos katun hanya
     * menambah Rp5.000, sedangkan produk lain Rp10.000.
     */
    public function test_lengan_panjang_kaos_katun_hanya_lima_ribu(): void
    {
        $this->assertSame(55_000, $this->harga('CUST-KAOS-KATUN', ['Katun 24s', 'Pendek'], 'DEWASA', 6));
        $this->assertSame(60_000, $this->harga('CUST-KAOS-KATUN', ['Katun 24s', 'Panjang'], 'DEWASA', 6));

        $this->assertSame(30_000, $this->harga('CUST-KAOS-KATUN', ['Katun 30s', 'Pendek'], 'SD', 6));
        $this->assertSame(35_000, $this->harga('CUST-KAOS-KATUN', ['Katun 30s', 'Panjang'], 'SD', 6));
    }

    /** Kaos hanya dijual M, L, XL. */
    public function test_kaos_hanya_tersedia_tiga_ukuran(): void
    {
        foreach (['CUST-KAOS-KERAH', 'CUST-KAOS-OBLONG'] as $sku) {
            $this->assertSame(['M', 'L', 'XL'], $this->produk($sku)->ukuranTersedia(null), $sku);
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('tidak tersedia');

        // 5XL ada di daftar bawaan sistem, tapi kaos tidak dijual sebesar itu.
        $this->harga('CUST-KAOS-KERAH', ['PE', 'Pendek'], '', 6, '5XL');
    }

    public function test_kategori_barang_stok_dari_klien_tersedia(): void
    {
        foreach (['Volly', 'Badminton', 'Taekwondo', 'Tolak Peluru', 'Karambol'] as $nama) {
            $this->assertTrue(
                Category::where('nama', $nama)->exists(),
                "Kategori {$nama} belum ada."
            );
        }
    }
}
