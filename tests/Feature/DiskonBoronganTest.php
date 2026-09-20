<?php

namespace Tests\Feature;

use App\Models\OptionValue;
use App\Models\Order;
use App\Models\Product;
use App\Services\PriceCalculator;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Potongan borongan di atas satu lusin (KEP-050).
 *
 * Contoh yang diberikan klien dipakai apa adanya sebagai tes: 12 pcs seharga
 * Rp60.000 berjumlah Rp720.000, dipotong Rp5.000 per potong, jadi Rp660.000.
 * Uang mukanya tetap dihitung dari Rp720.000, karena potongannya diberikan
 * saat pelunasan.
 */
class DiskonBoronganTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);
    }

    private function produk(): Product
    {
        return Product::where('sku', 'CUST-JRS-ATASAN')
            ->with(['prices', 'sizeSurcharges', 'sizeCharts', 'options.values'])
            ->firstOrFail();
    }

    /** @param list<string> $label */
    private function opsi(array $label): array
    {
        return OptionValue::whereIn('label', $label)
            ->whereHas('option', fn ($q) => $q->where('product_id', $this->produk()->id))
            ->get()
            ->mapWithKeys(fn (OptionValue $v) => [$v->product_option_id => $v->id])
            ->all();
    }

    private function rincian(int $jumlah)
    {
        return app(PriceCalculator::class)->kustomPerUkuran(
            $this->produk(),
            [['kategori' => 'DEWASA', 'ukuran' => 'L', 'jumlah' => $jumlah]],
            array_values($this->opsi(['Serena', 'Oblong', 'Pendek', 'Tanpa Saku'])),
        );
    }

    /** Contoh dari klien: 12 pcs x Rp60.000 = Rp720.000, potongan Rp60.000. */
    public function test_dua_belas_pcs_dipotong_lima_ribu_per_potong(): void
    {
        $rincian = $this->rincian(12);

        $this->assertSame(720_000, $rincian->subtotal);
        $this->assertSame(60_000, $rincian->potongan);
        $this->assertSame(660_000, $rincian->totalBersih());
    }

    public function test_di_bawah_satu_lusin_tidak_dipotong(): void
    {
        $rincian = $this->rincian(11);

        $this->assertSame(0, $rincian->potongan);
        $this->assertSame(660_000, $rincian->subtotal);
    }

    /** Tarifnya tetap: 24 pcs dipotong Rp5.000 per potong, bukan Rp10.000. */
    public function test_potongan_tidak_berlaku_kelipatan(): void
    {
        $this->assertSame(120_000, $this->rincian(24)->potongan);
    }

    /**
     * Uang muka dihitung dari harga penuh, dan potongannya baru terasa saat
     * pelunasan. Inilah cara toko selama ini memberi potongan.
     */
    public function test_uang_muka_dihitung_sebelum_potongan(): void
    {
        $this->post(route('kustom.simpan', $this->produk()), [
            'opsi' => $this->opsi(['Serena', 'Oblong', 'Pendek', 'Tanpa Saku']),
            'baris' => [['kategori' => 'DEWASA', 'ukuran' => 'L', 'jumlah' => 12]],
            'desain_sumber' => 'katalog',
        ]);

        $this->post('/checkout', [
            'nama' => 'SSB Garuda', 'email' => 'ssb@contoh.test',
            'no_hp' => '081299990000', 'tipe_pembayaran' => 'DP', 'setuju' => '1',
        ]);

        $order = Order::latest('id')->firstOrFail()->load(['items.options', 'payments', 'adjustments']);

        $this->assertSame(60_000, $order->diskon);
        $this->assertSame(660_000, $order->total);

        // 50% dari Rp720.000, bukan dari Rp660.000.
        $this->assertSame(360_000, $order->dpMinimum());
        $this->assertSame(360_000, $order->payments->first()->jumlah);

        // Sisa pelunasan: 660.000 - 360.000.
        app(\App\Services\AlurPesanan::class)->terimaPembayaran($order->payments->first());
        $this->assertSame(300_000, $order->fresh(['items.options', 'payments', 'adjustments'])->sisa_tagihan);
    }

    public function test_potongan_terbaca_pelanggan_di_keranjang(): void
    {
        $this->post(route('kustom.simpan', $this->produk()), [
            'opsi' => $this->opsi(['Serena', 'Oblong', 'Pendek', 'Tanpa Saku']),
            'baris' => [['kategori' => 'DEWASA', 'ukuran' => 'L', 'jumlah' => 12]],
            'desain_sumber' => 'katalog',
        ]);

        $this->get(route('keranjang'))
            ->assertOk()
            ->assertSee('Potongan 12 pcs ke atas')
            ->assertSee(rupiah(660_000));
    }

    public function test_berat_produk_tersimpan_untuk_hitung_ongkir(): void
    {
        foreach ([
            'CUST-JRS-ATASAN' => 250,
            'CUST-SETELAN' => 280,
            'CUST-CELANA-PENDEK' => 250,
            'CUST-JAKET' => 280,
            'CUST-TRAINING' => 280,
        ] as $sku => $gram) {
            $this->assertSame($gram, Product::where('sku', $sku)->firstOrFail()->berat_gram, $sku);
        }
    }

    /** Ukuran dewasa ditulis 2XL, bukan XXL (permintaan klien). */
    public function test_ukuran_dewasa_ditulis_dua_xl(): void
    {
        $this->assertContains('2XL', \App\Enums\KategoriUkuran::DEWASA->ukuran());
        $this->assertNotContains('XXL', \App\Enums\KategoriUkuran::DEWASA->ukuran());
    }
}
