<?php

namespace Tests\Feature;

use App\Models\OptionValue;
use App\Models\Product;
use App\Services\PriceCalculator;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Menjaga rumus harga KEP-009. Yang diuji bukan sekadar "kodenya jalan", tapi
 * secara khusus jebakan PER_PCS vs PER_ITEM, kesalahan yang paling mahal dan
 * paling sulit terlihat di sistem konveksi.
 */
class PriceCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private PriceCalculator $kalkulator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
        $this->kalkulator = new PriceCalculator;
    }

    private function jersey(): Product
    {
        return Product::with(['priceTiers', 'options.values'])
            ->where('sku', 'JRS-FUTSAL-FP')
            ->firstOrFail();
    }

    private function nilaiOpsi(Product $p, string ...$label): array
    {
        return OptionValue::whereIn('label', $label)
            ->whereHas('option', fn ($q) => $q->where('product_id', $p->id))
            ->pluck('id')
            ->all();
    }

    public function test_contoh_resmi_kep_009_menghasilkan_2_235_000(): void
    {
        $p = $this->jersey();

        $rincian = $this->kalkulator->kustom($p, 18, $this->nilaiOpsi(
            $p, 'Milano', 'Panjang', 'Nama & Nomor', 'Setup Desain Kustom'
        ));

        // tier 12-23 = 95.000; + Milano 7.000 + panjang 8.000 + nameset 10.000
        $this->assertSame(95_000, $rincian->hargaTier);
        $this->assertSame(120_000, $rincian->hargaSatuan);

        // 120.000 x 18 = 2.160.000, ditambah setup 75.000 SEKALI
        $this->assertSame(2_235_000, $rincian->subtotal);
    }

    public function test_biaya_per_item_tidak_ikut_dikali_qty(): void
    {
        $p = $this->jersey();

        $rincian = $this->kalkulator->kustom($p, 18, $this->nilaiOpsi($p, 'Setup Desain Kustom'));

        $this->assertSame(75_000, $rincian->totalPerItem());

        // Kalau setup ikut dikali 18, selisihnya Rp 1.275.000 dan pelanggan
        // baru menyadarinya di kasir.
        $this->assertSame(95_000 * 18 + 75_000, $rincian->subtotal);
        $this->assertNotSame(95_000 * 18 + 75_000 * 18, $rincian->subtotal);
    }

    public function test_tier_berpindah_mengikuti_jumlah(): void
    {
        $p = $this->jersey();

        $this->assertSame(95_000, $this->kalkulator->kustom($p, 23)->hargaTier);
        $this->assertSame(88_000, $this->kalkulator->kustom($p, 24)->hargaTier);
        $this->assertSame(82_000, $this->kalkulator->kustom($p, 100)->hargaTier);
    }

    public function test_opsi_boleh_mengurangi_harga(): void
    {
        $p = $this->jersey();

        $rincian = $this->kalkulator->kustom($p, 12, $this->nilaiOpsi($p, 'Hyget'));

        $this->assertSame(87_000, $rincian->hargaSatuan);   // 95.000 - 8.000
    }

    public function test_moq_ditegakkan_di_server(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Minimum pemesanan');

        $this->kalkulator->kustom($this->jersey(), 11);
    }

    public function test_opsi_milik_produk_lain_ditolak(): void
    {
        $lain = Product::with('options.values')->where('sku', 'KAOS-SEKOLAH-SB')->firstOrFail();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('bukan milik produk');

        // Harga tidak boleh bisa dimanipulasi dengan mengirim id opsi produk lain.
        $this->kalkulator->kustom($this->jersey(), 12, $this->nilaiOpsi($lain, 'Lacoste CVC'));
    }

    public function test_produk_hybrid_punya_dua_jalur_harga_terpisah(): void
    {
        $p = Product::with(['priceTiers', 'variants'])->where('sku', 'JRS-POLOS-01')->firstOrFail();

        $readyStock = $this->kalkulator->readyStock($p->variants->first(), 1);
        $kustom = $this->kalkulator->kustom($p, 48);

        $this->assertSame(65_000, $readyStock->hargaSatuan);
        $this->assertSame(52_000, $kustom->hargaSatuan);

        // Kustom pada jumlah besar memang lebih murah daripada ready stock:
        // produksi massal vs stok satuan (KEP-011). Model data harus membolehkannya.
        $this->assertLessThan($readyStock->hargaSatuan, $kustom->hargaSatuan);
    }

    public function test_ready_stock_dibatasi_stok_tersedia(): void
    {
        $p = Product::with('variants')->where('sku', 'BOLA-MITRE-01')->firstOrFail();
        $varian = $p->variants->first();

        $varian->update(['stok' => 5, 'stok_dipesan' => 4]);

        $this->assertSame(1, $varian->fresh()->stok_tersedia);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Stok');

        $this->kalkulator->readyStock($varian->fresh(), 2);
    }
}
