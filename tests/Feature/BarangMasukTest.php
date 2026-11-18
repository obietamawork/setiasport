<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\BarangMasuk;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Halaman stok dan barang masuk dari supplier (KEP-120).
 *
 * Barang masuk dipisah dari penyesuaian: yang satu menambah stok karena ada
 * kiriman, yang lain membetulkan angka yang salah (opname, rusak, selisih).
 * Mencampurnya membuat riwayat stok tidak bisa menjawab "berapa yang kita
 * beli dari supplier bulan ini".
 */
class BarangMasukTest extends TestCase
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

    /** @return array{0: ProductVariant, 1: ProductVariant} */
    private function duaVarian(): array
    {
        $varian = ProductVariant::where('sku', 'like', 'JRS-POLOS-01-%')->orderBy('id')->limit(2)->get();

        return [$varian[0], $varian[1]];
    }

    public function test_barang_masuk_menambah_stok_dan_tercatat(): void
    {
        [$a, $b] = $this->duaVarian();
        $stokA = $a->stok;
        $stokB = $b->stok;

        $this->actingAs($this->owner())
            ->post(route('panel.stok.masuk'), [
                'supplier' => 'CV Sumber Kain',
                'tanggal' => now()->toDateString(),
                'harga_beli' => 45_000,
                'catatan' => 'Kiriman pertama bulan ini',
                'baris' => [
                    ['variant_id' => $a->id, 'jumlah' => 10],
                    ['variant_id' => $b->id, 'jumlah' => 4],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('sukses');

        $this->assertSame($stokA + 10, $a->fresh()->stok);
        $this->assertSame($stokB + 4, $b->fresh()->stok);
        $this->assertSame(2, BarangMasuk::where('supplier', 'CV Sumber Kain')->count());
        $this->assertDatabaseHas('barang_masuk', [
            'product_variant_id' => $a->id, 'jumlah' => 10, 'harga_beli' => 45_000,
            'user_id' => $this->owner()->id,
        ]);
        $this->assertTrue(ActivityLog::where('aksi', 'stok.masuk')->exists());
    }

    public function test_baris_berjumlah_kosong_dilewati(): void
    {
        [$a, $b] = $this->duaVarian();
        $stokB = $b->stok;

        $this->actingAs($this->owner())
            ->post(route('panel.stok.masuk'), [
                'supplier' => 'CV Sumber Kain',
                'tanggal' => now()->toDateString(),
                'baris' => [
                    ['variant_id' => $a->id, 'jumlah' => 3],
                    ['variant_id' => $b->id, 'jumlah' => null],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($stokB, $b->fresh()->stok);
        $this->assertSame(1, BarangMasuk::count());
    }

    public function test_barang_masuk_wajib_supplier_dan_minimal_satu_jumlah(): void
    {
        [$a] = $this->duaVarian();

        $this->actingAs($this->owner())
            ->post(route('panel.stok.masuk'), [
                'tanggal' => now()->toDateString(),
                'baris' => [['variant_id' => $a->id, 'jumlah' => null]],
            ])
            ->assertSessionHasErrors(['supplier', 'baris']);

        $this->assertSame(0, BarangMasuk::count());
    }

    public function test_jumlah_negatif_varian_asing_dan_tanggal_depan_ditolak(): void
    {
        [$a] = $this->duaVarian();
        $stok = $a->stok;

        $this->actingAs($this->owner())
            ->post(route('panel.stok.masuk'), [
                'supplier' => 'CV Sumber Kain',
                'tanggal' => now()->addDay()->toDateString(),
                'baris' => [
                    ['variant_id' => $a->id, 'jumlah' => -5],
                    ['variant_id' => 999_999, 'jumlah' => 2],
                ],
            ])
            ->assertSessionHasErrors(['tanggal', 'baris.0.jumlah', 'baris.1.variant_id']);

        $this->assertSame($stok, $a->fresh()->stok);
    }

    public function test_staf_tanpa_izin_stok_tidak_bisa_mencatat_barang_masuk(): void
    {
        [$a] = $this->duaVarian();
        $staf = User::create(['name' => 'Staf', 'email' => 'staf.stok@setiasport.test', 'password' => bcrypt('rahasia-panjang')]);

        $this->actingAs($staf)
            ->post(route('panel.stok.masuk'), [
                'supplier' => 'X', 'tanggal' => now()->toDateString(),
                'baris' => [['variant_id' => $a->id, 'jumlah' => 1]],
            ])
            ->assertForbidden();
    }

    public function test_halaman_stok_dikelompokkan_per_produk_dengan_aksi_barang_masuk(): void
    {
        $this->actingAs($this->owner())
            ->get(route('panel.stok.index'))
            ->assertOk()
            ->assertSee('Jersey Polos')
            ->assertSee('JRS-POLOS-01-')
            ->assertSee('Barang masuk')
            ->assertSee('Penyesuaian stok')
            ->assertSee(route('panel.stok.penyesuaian'));
    }

    public function test_penyaring_status_habis_hanya_menampilkan_produk_yang_habis(): void
    {
        $bola = ProductVariant::where('sku', 'BOLA-MITRE-01')->firstOrFail();
        $bola->update(['stok' => 2, 'stok_dipesan' => 2]);

        $daftar = $this->actingAs($this->owner())
            ->get(route('panel.stok.index', ['status' => 'habis']))
            ->assertOk()
            ->viewData('varian');

        $this->assertTrue($daftar->flatten()->every(fn (ProductVariant $v) => $v->stok_tersedia <= 0));
        $this->assertTrue($daftar->flatten()->contains('id', $bola->id));
    }

    public function test_riwayat_barang_masuk_tampil_di_laporan_stok(): void
    {
        [$a] = $this->duaVarian();

        $this->actingAs($this->owner())->post(route('panel.stok.masuk'), [
            'supplier' => 'Toko Grosir Solo', 'tanggal' => now()->toDateString(),
            'baris' => [['variant_id' => $a->id, 'jumlah' => 7]],
        ]);

        $this->actingAs($this->owner())
            ->get(route('panel.laporan.stok'))
            ->assertOk()
            ->assertSee('Riwayat barang masuk')
            ->assertSee('Toko Grosir Solo');
    }

    public function test_supplier_yang_pernah_diketik_ditawarkan_lagi(): void
    {
        [$a] = $this->duaVarian();

        $this->actingAs($this->owner())->post(route('panel.stok.masuk'), [
            'supplier' => 'CV Sumber Kain', 'tanggal' => now()->toDateString(),
            'baris' => [['variant_id' => $a->id, 'jumlah' => 1]],
        ]);

        $this->actingAs($this->owner())
            ->get(route('panel.stok.index'))
            ->assertSee('<option value="CV Sumber Kain">', false);
    }
}
