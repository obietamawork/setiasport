<?php

namespace Tests\Feature;

use App\Enums\KategoriUkuran;
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
 * Lima produk kustom dengan harga asli klien (KEP-039, KEP-049).
 *
 * Angka di tes ini disalin dari daftar harga yang dikirim klien. Kalau suatu
 * hari ada yang menyederhanakan rumusnya dan satu angka meleset, tes ini yang
 * memberi tahu, bukan pelanggan yang telanjur ditagih salah.
 */
class KatalogKustomTest extends TestCase
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
    private function opsi(Product $p, array $label): array
    {
        return OptionValue::whereIn('label', $label)
            ->whereHas('option', fn ($q) => $q->where('product_id', $p->id))
            ->pluck('id')
            ->all();
    }

    /** @param array<int, array<string, mixed>> $baris */
    private function hitung(Product $p, array $baris, array $opsi)
    {
        return app(PriceCalculator::class)->kustomPerUkuran($p, $baris, $this->opsi($p, $opsi));
    }

    public function test_lima_produk_kustom_tersedia_dengan_harganya(): void
    {
        foreach ([
            'CUST-JRS-ATASAN' => 45_000,
            'CUST-SETELAN' => 75_000,
            'CUST-CELANA-PENDEK' => 35_000,
            'CUST-JAKET' => 115_000,
            'CUST-TRAINING' => 75_000,
        ] as $sku => $termurah) {
            $p = $this->produk($sku);

            $this->assertSame($termurah, $p->prices->min('harga_satuan'), $sku);
            $this->assertSame(6, $p->moq, $sku);
        }
    }

    /** Setelan SMA/Dewasa bahan Embos tertulis Rp135.000 di daftar harga klien. */
    public function test_setelan_mengikuti_daftar_harga(): void
    {
        $p = $this->produk('CUST-SETELAN');

        $rincian = $this->hitung(
            $p,
            [['kategori' => 'DEWASA', 'ukuran' => 'L', 'jumlah' => 6]],
            ['Embos', 'Oblong', 'Pendek'],
        );

        $this->assertSame(135_000, $rincian->baris[0]['harga_satuan']);
    }

    /** Kerah berlaku untuk semua kategori, tambahannya Rp10.000 (jawaban klien). */
    public function test_kerah_menambah_sepuluh_ribu_di_semua_kategori(): void
    {
        $p = $this->produk('CUST-JRS-ATASAN');

        foreach (['SD' => 55_000, 'SMP' => 65_000, 'DEWASA' => 70_000] as $kategori => $harga) {
            $rincian = $this->hitung(
                $p,
                [['kategori' => $kategori, 'ukuran' => 'L', 'jumlah' => 6]],
                ['Serena', 'Pakai Kerah', 'Pendek', 'Tanpa Saku'],
            );

            $this->assertSame($harga, $rincian->baris[0]['harga_satuan'], $kategori);
        }
    }

    /** Lengan panjang bisa dipesan di atasan maupun setelan (jawaban klien). */
    public function test_lengan_panjang_tersedia_di_atasan_dan_setelan(): void
    {
        $atasan = $this->hitung(
            $this->produk('CUST-JRS-ATASAN'),
            [['kategori' => 'SD', 'ukuran' => 'L', 'jumlah' => 6]],
            ['Serena', 'Oblong', 'Panjang', 'Tanpa Saku'],
        );

        $setelan = $this->hitung(
            $this->produk('CUST-SETELAN'),
            [['kategori' => 'SD', 'ukuran' => 'L', 'jumlah' => 6]],
            ['Serena', 'Oblong', 'Panjang'],
        );

        $this->assertSame(55_000, $atasan->baris[0]['harga_satuan']);   // 45.000 + 10.000
        $this->assertSame(85_000, $setelan->baris[0]['harga_satuan']);  // 75.000 + 10.000
    }

    /**
     * Celana pendek tidak punya kategori umur: satu harga dari ukuran terkecil
     * sampai terbesar, sesuai jawaban klien.
     */
    public function test_celana_pendek_satu_harga_untuk_semua_ukuran(): void
    {
        $p = $this->produk('CUST-CELANA-PENDEK');

        $this->assertFalse($p->pakaiKategoriUkuran());

        $rincian = $this->hitung(
            $p,
            [
                ['kategori' => '', 'ukuran' => 'S', 'jumlah' => 6],
                ['kategori' => '', 'ukuran' => '3XL', 'jumlah' => 6],
            ],
            ['Lotto'],
        );

        $this->assertSame(35_000, $rincian->baris[0]['harga_satuan']);
        $this->assertSame(35_000, $rincian->baris[1]['harga_satuan']);
    }

    /** Panduan ukuran yang membatasi, bukan daftar bawaan di kode (KEP-049). */
    public function test_ukuran_di_luar_panduan_ditolak(): void
    {
        $p = $this->produk('CUST-CELANA-PENDEK');

        $this->assertSame(['S', 'M', 'L', 'XL', '2XL', '3XL'], $p->ukuranTersedia(null));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('tidak tersedia');

        // 5XL ada di daftar bawaan enum, tapi tidak ada di panduan celana.
        $this->hitung($p, [['kategori' => '', 'ukuran' => '5XL', 'jumlah' => 6]], ['Lotto']);
    }

    public function test_jaket_dan_training_mengikuti_daftar_harga(): void
    {
        $jaket = $this->hitung(
            $this->produk('CUST-JAKET'),
            [['kategori' => 'DEWASA', 'ukuran' => 'L', 'jumlah' => 6]],
            ['Diadora Import'],
        );

        $training = $this->hitung(
            $this->produk('CUST-TRAINING'),
            [['kategori' => 'SMP', 'ukuran' => 'L', 'jumlah' => 6]],
            ['Diadora Lokal'],
        );

        $this->assertSame(160_000, $jaket->baris[0]['harga_satuan']);
        $this->assertSame(90_000, $training->baris[0]['harga_satuan']);
    }

    /** Produk tanpa kategori umur tidak boleh menampilkan pemilih kategori. */
    public function test_halaman_pesan_celana_tidak_menampilkan_kategori(): void
    {
        $p = $this->produk('CUST-CELANA-PENDEK');

        $this->get(route('kustom.buat', $p))
            ->assertOk()
            ->assertDontSee('>Kategori<', false)
            // Panduan ukuran dari klien ikut tampil, lengkap dengan sentimeternya.
            ->assertSee('Lebar (cm)')
            ->assertSee('2XL');

        $this->get(route('kustom.buat', $this->produk('CUST-JRS-ATASAN')))
            ->assertOk()
            ->assertSee('>Kategori<', false);
    }
}
