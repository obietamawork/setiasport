<?php

namespace Tests\Feature;

use App\Enums\FulfillmentType;
use App\Models\Category;
use App\Models\Product;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Urutan katalog (KEP-165): pelanggan bisa melihat produk terbaru lebih dulu.
 * Tanpa pilihan, urutannya tetap nama A sampai Z seperti sebelumnya.
 */
class KatalogUrutanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);

        $kategori = Category::create(['nama' => 'Bola', 'slug' => 'bola', 'urutan' => 0]);

        // Nama A-Z sengaja berkebalikan dengan urutan dibuatnya.
        foreach ([['Zebra Futsal', '2026-11-20'], ['Mitre Lama', '2026-10-01'], ['Adidas Baru', '2026-09-01']] as [$nama, $dibuat]) {
            $produk = Product::create([
                'category_id' => $kategori->id,
                'sku' => strtoupper(substr($nama, 0, 3)),
                'nama' => $nama,
                'slug' => str($nama)->slug()->toString(),
                'fulfillment_type' => FulfillmentType::READY_STOCK,
                'harga_dasar' => 100_000,
                'moq' => 1,
                'aktif' => true,
            ]);
            $produk->forceFill(['created_at' => $dibuat])->save();
        }
    }

    /** @return list<string> */
    private function urutanNama(array $parameter = []): array
    {
        return $this->get(route('katalog', $parameter))
            ->assertOk()
            ->viewData('produk')
            ->pluck('nama')
            ->all();
    }

    public function test_tanpa_pilihan_urutannya_nama_a_sampai_z(): void
    {
        $this->assertSame(['Adidas Baru', 'Mitre Lama', 'Zebra Futsal'], $this->urutanNama());
    }

    public function test_terbaru_menampilkan_produk_yang_terakhir_ditambahkan_lebih_dulu(): void
    {
        $this->assertSame(['Zebra Futsal', 'Mitre Lama', 'Adidas Baru'], $this->urutanNama(['urut' => 'terbaru']));
    }

    public function test_nilai_urut_yang_tidak_dikenal_kembali_ke_nama(): void
    {
        $this->assertSame(['Adidas Baru', 'Mitre Lama', 'Zebra Futsal'], $this->urutanNama(['urut' => 'harga; drop']));
    }

    public function test_pilihan_urutan_tampil_dan_ikut_terbawa_saat_pindah_cara_belanja(): void
    {
        $this->get(route('katalog', ['urut' => 'terbaru']))
            ->assertOk()
            ->assertSee('name="urut"', false)
            ->assertSee('<option value="terbaru" selected>', false)
            ->assertSee(e(route('katalog', ['jalur' => 'stok', 'urut' => 'terbaru'])), false);
    }
}
