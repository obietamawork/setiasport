<?php

namespace Tests\Feature;

use App\Enums\FulfillmentType;
use App\Enums\KategoriUkuran;
use App\Models\Category;
use App\Models\OptionValue;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductPrice;
use App\Models\ProductSizeSurcharge;
use App\Services\PriceCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Harga per potong menurut kategori ukuran (KEP-039).
 *
 * Angka di tes ini disalin apa adanya dari daftar harga klien. Kalau rumusnya
 * suatu hari disederhanakan dan angkanya meleset seribu rupiah, tes ini yang
 * memberi tahu, bukan pelanggan yang telanjur ditagih salah.
 */
class HargaPerUkuranTest extends TestCase
{
    use RefreshDatabase;

    private Product $produk;

    /** @var array<string, OptionValue> */
    private array $opsi = [];

    protected function setUp(): void
    {
        parent::setUp();

        $kategori = Category::create(['nama' => 'Jersey', 'slug' => 'jersey', 'urutan' => 0]);

        $this->produk = Product::create([
            'category_id' => $kategori->id,
            'sku' => 'CUST-JRS-ATASAN',
            'nama' => 'Custom Jersey Atasan',
            'slug' => 'custom-jersey-atasan',
            'fulfillment_type' => FulfillmentType::PREORDER,
            'harga_dasar' => 0,
            'moq' => 6,
            'lead_time_hari' => 7,
            'roster_wajib' => true,
            'aktif' => true,
        ]);

        // Bahan tidak menambah harga: ia memilih baris mana di matriks harga.
        $this->opsiProduk('Bahan', ['Serena' => 0, 'Jarum' => 0, 'Milano' => 0, 'Wafel' => 0, 'Embos' => 0]);
        $this->opsiProduk('Lengan', ['Pendek' => 0, 'Panjang' => 10_000]);
        $this->opsiProduk('Model Baju', ['Oblong' => 0, 'Pakai Kerah' => 10_000]);
        $this->opsiProduk('Saku', ['Tanpa Saku' => 0, 'Pakai Saku' => 5_000]);

        // Daftar harga klien: pendek oblong, per kategori dan bahan.
        $matriks = [
            'SD' => ['Serena' => 45_000, 'Jarum' => 55_000, 'Milano' => 65_000, 'Wafel' => 70_000, 'Embos' => 70_000],
            'SMP' => ['Serena' => 55_000, 'Jarum' => 65_000, 'Milano' => 75_000, 'Wafel' => 80_000, 'Embos' => 80_000],
            'DEWASA' => ['Serena' => 60_000, 'Jarum' => 70_000, 'Milano' => 80_000, 'Wafel' => 85_000, 'Embos' => 90_000],
        ];

        foreach ($matriks as $kat => $baris) {
            foreach ($baris as $bahan => $harga) {
                ProductPrice::create([
                    'product_id' => $this->produk->id,
                    'kategori' => $kat,
                    'option_value_id' => $this->opsi[$bahan]->id,
                    'harga_satuan' => $harga,
                ]);
            }
        }

        foreach (['3XL' => 5_000, '5XL' => 10_000] as $ukuran => $tambahan) {
            ProductSizeSurcharge::create([
                'product_id' => $this->produk->id,
                'ukuran' => $ukuran,
                'tambahan_harga' => $tambahan,
            ]);
        }

        $this->produk->load(['prices', 'sizeSurcharges', 'options.values']);
    }

    /** @param array<string, int> $values */
    private function opsiProduk(string $nama, array $values): void
    {
        $opsi = ProductOption::create([
            'product_id' => $this->produk->id,
            'nama' => $nama,
            'wajib' => true,
            'urutan' => ProductOption::where('product_id', $this->produk->id)->count(),
        ]);

        foreach (array_values($values) as $i => $tambahan) {
            $label = array_keys($values)[$i];

            $this->opsi[$label] = OptionValue::create([
                'product_option_id' => $opsi->id,
                'label' => $label,
                'tambahan_harga' => $tambahan,
                'basis' => 'PER_PCS',
                'urutan' => $i,
                'aktif' => true,
            ]);
        }
    }

    /** @param list<string> $opsi */
    private function hitung(array $daftarUkuran, array $opsi = ['Serena', 'Pendek', 'Oblong', 'Tanpa Saku'])
    {
        return app(PriceCalculator::class)->kustomPerUkuran(
            $this->produk->fresh(['prices', 'sizeSurcharges', 'options.values']),
            $daftarUkuran,
            array_map(fn (string $l) => $this->opsi[$l]->id, $opsi),
        );
    }

    public function test_harga_mengikuti_kategori_ukuran(): void
    {
        $rincian = $this->hitung([['kategori' => 'SD', 'ukuran' => 'L', 'jumlah' => 6]]);

        $this->assertSame(6, $rincian->qty);
        $this->assertSame(270_000, $rincian->subtotal);
        $this->assertSame(45_000, $rincian->baris[0]['harga_satuan']);
    }

    /**
     * Inilah yang tidak bisa dilakukan rumus lama: satu tim memesan seragam
     * anak SD dan pelatihnya sekaligus, dan keduanya berharga berbeda.
     */
    public function test_satu_pesanan_boleh_memuat_dua_kategori(): void
    {
        $rincian = $this->hitung([
            ['kategori' => 'SD', 'ukuran' => 'L', 'jumlah' => 6],
            ['kategori' => 'DEWASA', 'ukuran' => 'XL', 'jumlah' => 4],
        ]);

        $this->assertSame(10, $rincian->qty);
        $this->assertSame(270_000 + 240_000, $rincian->subtotal);
    }

    /**
     * Angka pembanding diambil langsung dari daftar harga klien: Embos panjang
     * berkrah untuk SMA/Dewasa tertulis Rp110.000.
     */
    public function test_tambahan_lengan_dan_kerah_menghasilkan_angka_daftar_harga(): void
    {
        $rincian = $this->hitung(
            [['kategori' => 'DEWASA', 'ukuran' => 'L', 'jumlah' => 6]],
            ['Embos', 'Panjang', 'Pakai Kerah', 'Tanpa Saku'],
        );

        $this->assertSame(110_000, $rincian->baris[0]['harga_satuan']);
    }

    public function test_ukuran_besar_kena_tambahan_per_potong(): void
    {
        $rincian = $this->hitung([
            ['kategori' => 'DEWASA', 'ukuran' => 'L', 'jumlah' => 5],
            ['kategori' => 'DEWASA', 'ukuran' => '3XL', 'jumlah' => 1],
        ]);

        $this->assertSame(60_000, $rincian->baris[0]['harga_satuan']);
        $this->assertSame(65_000, $rincian->baris[1]['harga_satuan']);
        $this->assertSame(365_000, $rincian->subtotal);
    }

    /** Bahan memilih baris matriks, jadi tidak boleh ikut dijumlahkan lagi. */
    public function test_bahan_tampil_di_rincian_tanpa_menambah_harga(): void
    {
        $rincian = $this->hitung(
            [['kategori' => 'SD', 'ukuran' => 'M', 'jumlah' => 6]],
            ['Milano', 'Pendek', 'Oblong', 'Tanpa Saku'],
        );

        $bahan = collect($rincian->komponenPerPcs)->firstWhere('label', 'Bahan');

        $this->assertSame('Milano', $bahan['nilai']);
        $this->assertSame(0, $bahan['jumlah']);
        $this->assertSame(65_000, $rincian->baris[0]['harga_satuan']);
    }

    public function test_jumlah_di_bawah_minimum_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Minimum pemesanan');

        $this->hitung([['kategori' => 'SD', 'ukuran' => 'L', 'jumlah' => 5]]);
    }

    /** Ukuran dewasa tidak boleh dipesan atas nama kategori SD. */
    public function test_ukuran_di_luar_kategori_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('tidak tersedia');

        $this->hitung([['kategori' => 'SD', 'ukuran' => '5XL', 'jumlah' => 6]]);
    }

    public function test_kategori_tanpa_harga_ditolak_alih_alih_dihitung_nol(): void
    {
        ProductPrice::where('product_id', $this->produk->id)
            ->where('kategori', KategoriUkuran::SMP->value)
            ->delete();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('belum diisi');

        $this->hitung([['kategori' => 'SMP', 'ukuran' => 'M', 'jumlah' => 6]]);
    }
}
