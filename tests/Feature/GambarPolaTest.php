<?php

namespace Tests\Feature;

use App\Enums\PolaPakaian;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gambar pola hitam putih (KEP-106): pemesan awam melihat bentuk barangnya
 * sebelum ada desain. Pola diatur per produk di panel, tampil di langkah awal
 * pesan kustom, dan menggantikan monogram selama produk belum punya foto.
 */
class GambarPolaTest extends TestCase
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

    /** @return array<string, mixed> */
    private function isianProduk(Product $produk, mixed $pola): array
    {
        return [
            'category_id' => $produk->category_id,
            'sku' => $produk->sku,
            'nama' => $produk->nama,
            'slug' => $produk->slug,
            'fulfillment_type' => $produk->fulfillment_type->value,
            'pola' => $pola,
            'harga_dasar' => $produk->harga_dasar,
            'moq' => $produk->moq,
            'lead_time_hari' => $produk->lead_time_hari,
            'aktif' => 1,
        ];
    }

    public function test_pemilik_toko_mengatur_dan_mengosongkan_gambar_pola(): void
    {
        $produk = Product::where('sku', 'CUST-JRS-ATASAN')->firstOrFail();
        $this->actingAs($this->owner());

        $this->put(route('panel.produk.update', $produk), $this->isianProduk($produk, 'kaos_kerah'))
            ->assertSessionHasNoErrors();
        $this->assertSame(PolaPakaian::KAOS_KERAH, $produk->fresh()->pola);

        $this->put(route('panel.produk.update', $produk), $this->isianProduk($produk, ''))
            ->assertSessionHasNoErrors();
        $this->assertNull($produk->fresh()->pola);
    }

    public function test_pola_di_luar_daftar_ditolak(): void
    {
        $produk = Product::where('sku', 'CUST-JRS-ATASAN')->firstOrFail();

        $this->actingAs($this->owner())
            ->put(route('panel.produk.update', $produk), $this->isianProduk($produk, 'gaun'))
            ->assertSessionHasErrors('pola');

        $this->assertSame(PolaPakaian::KAOS, $produk->fresh()->pola);
    }

    /**
     * Setelan digambar atasan dan celana, depan dan belakang, dan bentuknya
     * mengikuti pilihan model lewat Alpine.
     */
    public function test_langkah_pertama_pesan_kustom_menggambar_polanya(): void
    {
        $setelan = Product::where('sku', 'CUST-SETELAN')->firstOrFail();

        $this->get(route('kustom.buat', $setelan))
            ->assertOk()
            ->assertSee('Gambaran bentuk')
            ->assertSee('Sketsa pola setelan (atasan + celana pendek), tampak depan dan belakang, tanpa desain', false)
            ->assertSee('Celana belakang')
            ->assertSee('x-show="polaLenganPanjang"', false)
            ->assertSee("x-show=\"polaLeher === 'kerah'\"", false);
    }

    public function test_produk_tanpa_pola_tidak_menampilkan_gambar_kosong(): void
    {
        $setelan = Product::where('sku', 'CUST-SETELAN')->firstOrFail();
        $setelan->update(['pola' => null]);

        $this->get(route('kustom.buat', $setelan))
            ->assertOk()
            ->assertDontSee('Gambaran bentuk');
    }

    /** Selama belum ada foto, kartu katalog memakai sketsa; produk bukan pakaian tidak. */
    public function test_kartu_tanpa_foto_memakai_sketsa_bukan_monogram(): void
    {
        $halaman = $this->get(route('katalog'))->assertOk();

        $this->assertSame(
            Product::aktif()->whereNotNull('pola')->doesntHave('images')->count(),
            substr_count($halaman->getContent(), 'pola-sketsa'),
        );
    }
}
