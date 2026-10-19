<?php

namespace Tests\Feature;

use App\Enums\FulfillmentType;
use App\Models\Category;
use App\Models\OptionValue;
use App\Models\Order;
use App\Models\OrderItemRoster;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductPrice;
use App\Models\ProductSizeSurcharge;
use App\Services\Checkout;
use App\Services\Keranjang;
use App\Services\RosterPemain;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Pesanan kustom yang jumlahnya berasal dari daftar ukuran (KEP-039).
 *
 * Yang dijaga di sini: harga tiap potong ikut dibekukan di pesanan, total
 * dihitung dari harga per baris dan bukan dari rata-rata, dan pelanggan tidak
 * bisa mengubah tagihan lewat halaman daftar pemain.
 */
class PesananPerUkuranTest extends TestCase
{
    use RefreshDatabase;

    private Product $produk;

    /** @var array<string, OptionValue> */
    private array $opsi = [];

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class]);

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

        $opsi = ProductOption::create([
            'product_id' => $this->produk->id, 'nama' => 'Bahan', 'wajib' => true, 'urutan' => 0,
        ]);

        foreach (['Serena', 'Milano'] as $i => $label) {
            $this->opsi[$label] = OptionValue::create([
                'product_option_id' => $opsi->id, 'label' => $label,
                'tambahan_harga' => 0, 'basis' => 'PER_PCS', 'urutan' => $i, 'aktif' => true,
            ]);
        }

        $harga = [
            'SD' => ['Serena' => 45_000, 'Milano' => 65_000],
            'DEWASA' => ['Serena' => 60_000, 'Milano' => 80_000],
        ];

        foreach ($harga as $kat => $baris) {
            foreach ($baris as $bahan => $nilai) {
                ProductPrice::create([
                    'product_id' => $this->produk->id,
                    'kategori' => $kat,
                    'option_value_id' => $this->opsi[$bahan]->id,
                    'harga_satuan' => $nilai,
                ]);
            }
        }

        ProductSizeSurcharge::create([
            'product_id' => $this->produk->id, 'ukuran' => '3XL', 'tambahan_harga' => 5_000,
        ]);
    }

    /** @param array<int, array<string, mixed>> $daftar */
    private function pesan(array $daftar): Order
    {
        app(Keranjang::class)->tambahKustomPerUkuran(
            $this->produk->fresh(['prices', 'sizeSurcharges', 'options.values']),
            $daftar,
            [$this->opsi['Serena']->id],
        );

        app(Checkout::class)->proses([
            'nama' => 'Budi Santoso',
            'email' => 'budi@contoh.test',
            'no_hp' => '081234567890',
            'catatan' => null,
        ], 'DP');

        return Order::latest('id')->firstOrFail();
    }

    public function test_daftar_ukuran_jadi_jumlah_pesanan_dan_daftar_pemain(): void
    {
        $order = $this->pesan([
            ['kategori' => 'SD', 'ukuran' => 'L', 'jumlah' => 6, 'nama_punggung' => 'ANDI', 'nomor' => '10'],
            ['kategori' => 'DEWASA', 'ukuran' => 'XL', 'jumlah' => 4, 'nama_punggung' => null, 'nomor' => null],
        ]);

        $item = $order->items->first();

        $this->assertSame(10, $item->qty);
        $this->assertSame(2, $item->rosters()->count());
        $this->assertSame(270_000 + 240_000, $item->subtotal);
        $this->assertSame(510_000, $order->total);

        $baris = $item->rosters()->orderBy('urutan')->get();

        $this->assertSame(45_000, $baris[0]->harga_satuan);
        $this->assertSame('ANDI', $baris[0]->nama_punggung);
        $this->assertSame('SD L', $baris[0]->labelUkuran());
        $this->assertSame(60_000, $baris[1]->harga_satuan);
    }

    /**
     * Satu baris roster boleh mewakili banyak potong sekaligus (tempel dari
     * Excel, atau pesanan manual admin) -- ringkasannya harus menjumlahkan
     * `jumlah` tiap baris, bukan menghitung berapa barisnya. Dua baris
     * ukuran sama dengan jumlah berbeda sengaja dipakai di sini supaya
     * perbedaannya kentara: "hitung baris" akan salah melaporkan "L:2".
     */
    public function test_ringkasan_ukuran_menjumlahkan_potongan_bukan_menghitung_baris(): void
    {
        $order = $this->pesan([
            ['kategori' => 'SD', 'ukuran' => 'L', 'jumlah' => 6, 'nama_punggung' => null, 'nomor' => null],
            ['kategori' => 'DEWASA', 'ukuran' => 'L', 'jumlah' => 3, 'nama_punggung' => null, 'nomor' => null],
        ]);

        $this->assertSame('L:9', $order->items->first()->ringkasanUkuran());
    }

    /**
     * Rata-rata harga tidak pernah dipakai menghitung tagihan: pada jumlah yang
     * tidak habis dibagi, pembulatannya meleset ribuan rupiah.
     */
    public function test_total_dihitung_per_baris_bukan_dari_rata_rata(): void
    {
        $order = $this->pesan([
            ['kategori' => 'SD', 'ukuran' => 'L', 'jumlah' => 5],
            ['kategori' => 'DEWASA', 'ukuran' => 'XL', 'jumlah' => 4],
        ]);

        $item = $order->items->first();

        $this->assertSame(9, $item->qty);
        $this->assertSame(465_000, $item->subtotal);
        $this->assertNotSame($item->harga_satuan * $item->qty, $item->subtotal);
    }

    public function test_ukuran_besar_ikut_terbawa_ke_pesanan(): void
    {
        $order = $this->pesan([
            ['kategori' => 'DEWASA', 'ukuran' => 'L', 'jumlah' => 5],
            ['kategori' => 'DEWASA', 'ukuran' => '3XL', 'jumlah' => 1],
        ]);

        $this->assertSame(365_000, $order->items->first()->subtotal);
    }

    /** Satu baris "tanpa nama 2 pcs" boleh dipecah jadi dua baris bernama. */
    public function test_baris_boleh_dipecah_selama_ukurannya_tetap(): void
    {
        $order = $this->pesan([
            ['kategori' => 'SD', 'ukuran' => 'L', 'jumlah' => 4],
            ['kategori' => 'SD', 'ukuran' => 'M', 'jumlah' => 2],
        ]);

        $item = $order->items->first();

        app(RosterPemain::class)->simpan($item, [
            ['kategori' => 'SD', 'ukuran' => 'L', 'jumlah' => 1, 'nama_punggung' => 'ANDI', 'nomor' => '7'],
            ['kategori' => 'SD', 'ukuran' => 'L', 'jumlah' => 3, 'nama_punggung' => null, 'nomor' => null],
            ['kategori' => 'SD', 'ukuran' => 'M', 'jumlah' => 2, 'nama_punggung' => null, 'nomor' => null],
        ]);

        $item->refresh()->load('rosters');

        $this->assertSame(3, $item->rosters->count());
        $this->assertSame(6, (int) $item->rosters->sum('jumlah'));
        $this->assertSame(270_000, $item->subtotal);
        $this->assertSame('ANDI', $item->rosters->firstWhere('nomor', '7')->nama_punggung);
    }

    /** Menambah potong dari halaman pelanggan berarti menambah tagihan diam-diam. */
    public function test_menambah_jumlah_lewat_daftar_pemain_ditolak(): void
    {
        $order = $this->pesan([['kategori' => 'SD', 'ukuran' => 'L', 'jumlah' => 6]]);
        $item = $order->items->first();

        try {
            app(RosterPemain::class)->simpan($item, [
                ['kategori' => 'SD', 'ukuran' => 'L', 'jumlah' => 8],
            ]);
            $this->fail('Penambahan jumlah seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('tetap sama', $e->getMessage());
        }

        $this->assertSame(6, (int) $item->fresh()->rosters()->sum('jumlah'));
        $this->assertSame(270_000, $item->fresh()->load('rosters', 'options')->subtotal);
    }

    public function test_mengganti_ukuran_ke_yang_lebih_mahal_ditolak(): void
    {
        $order = $this->pesan([['kategori' => 'SD', 'ukuran' => 'L', 'jumlah' => 6]]);
        $item = $order->items->first();

        $this->expectException(ValidationException::class);

        app(RosterPemain::class)->simpan($item, [
            ['kategori' => 'DEWASA', 'ukuran' => 'L', 'jumlah' => 6],
        ]);
    }

    /** Satu nomor punggung tidak bisa mewakili dua potong sekaligus. */
    public function test_nomor_punggung_pada_baris_berjumlah_banyak_ditolak(): void
    {
        $order = $this->pesan([['kategori' => 'SD', 'ukuran' => 'L', 'jumlah' => 6]]);
        $item = $order->items->first();

        $this->expectException(ValidationException::class);

        app(RosterPemain::class)->simpan($item, [
            ['kategori' => 'SD', 'ukuran' => 'L', 'jumlah' => 6, 'nomor' => '10'],
        ]);
    }

    public function test_daftar_pemain_dianggap_lengkap_dari_jumlah_potong(): void
    {
        $order = $this->pesan([
            ['kategori' => 'SD', 'ukuran' => 'L', 'jumlah' => 4],
            ['kategori' => 'SD', 'ukuran' => 'M', 'jumlah' => 2],
        ]);

        $item = $order->items->first();

        // Dua baris untuk enam potong: dulu dihitung dari jumlah baris, dan
        // pesanan seperti ini dianggap belum lengkap selamanya.
        $this->assertTrue(app(RosterPemain::class)->lengkap($item));
    }

    public function test_jumlah_kustom_tidak_bisa_diubah_dari_keranjang(): void
    {
        $keranjang = app(Keranjang::class);

        $keranjang->tambahKustomPerUkuran(
            $this->produk->fresh(['prices', 'sizeSurcharges', 'options.values']),
            [['kategori' => 'SD', 'ukuran' => 'L', 'jumlah' => 6]],
            [$this->opsi['Serena']->id],
        );

        $kunci = array_key_first($keranjang->baris());

        $this->expectException(\InvalidArgumentException::class);

        $keranjang->ubahJumlah($kunci, 20);
    }

    public function test_harga_pesanan_tidak_ikut_berubah_saat_daftar_harga_naik(): void
    {
        $order = $this->pesan([['kategori' => 'SD', 'ukuran' => 'L', 'jumlah' => 6]]);

        ProductPrice::where('product_id', $this->produk->id)
            ->update(['harga_satuan' => 99_000]);

        $this->assertSame(270_000, $order->fresh()->load('items.rosters', 'items.options')->total);
        $this->assertSame(45_000, OrderItemRoster::first()->harga_satuan);
    }
}
