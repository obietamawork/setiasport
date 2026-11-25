<?php

namespace Tests\Feature;

use App\Models\OptionValue;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Potongan jersey Pria / Wanita (KEP-161): dipasang ke jersey kustom lewat
 * migrasi data, tampil dengan gambar pembanding, dan harganya sama.
 */
class ModelJerseyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);
        $this->pasangModelJersey();
    }

    /** Katalog uji dibuat sesudah migrasi, jadi migrasi datanya dijalankan lagi di sini. */
    private function pasangModelJersey(): void
    {
        (require database_path('migrations/2026_11_25_070317_tambah_model_jersey_pria_wanita.php'))->up();
    }

    private function produk(): Product
    {
        return Product::where('sku', 'CUST-JRS-ATASAN')->firstOrFail();
    }

    /** @return array<int, int> */
    private function opsi(string ...$label): array
    {
        return OptionValue::whereIn('label', $label)
            ->whereHas('option', fn ($q) => $q->where('product_id', $this->produk()->id))
            ->get()
            ->mapWithKeys(fn (OptionValue $v) => [$v->product_option_id => $v->id])
            ->all();
    }

    public function test_model_jersey_dipasang_paling_atas_wajib_dan_harganya_sama(): void
    {
        $opsi = $this->produk()->options()->with('values')->get();

        $this->assertSame(['Model Jersey', 'Bahan', 'Model Baju', 'Model Lengan', 'Saku'], $opsi->pluck('nama')->all());
        $this->assertTrue($opsi->first()->wajib);
        $this->assertSame(['Pria' => 0, 'Wanita' => 0], $opsi->first()->values->mapWithKeys(fn ($v) => [$v->label => (int) $v->tambahan_harga])->all());
        $this->assertTrue(Product::where('sku', 'CUST-SETELAN')->firstOrFail()->options()->where('nama', 'Model Jersey')->exists());
    }

    public function test_migrasi_yang_dijalankan_lagi_tidak_menggandakan_opsi(): void
    {
        $this->pasangModelJersey();

        $this->assertSame(1, $this->produk()->options()->where('nama', 'Model Jersey')->count());
    }

    public function test_wizard_menampilkan_pilihan_dengan_gambar_pembanding(): void
    {
        $this->get(route('kustom.buat', $this->produk()))
            ->assertOk()
            ->assertSee('Model Jersey')
            ->assertSee('Beda potongan pria dan wanita. Harganya sama.')
            ->assertSee('polaWanita', false);
    }

    public function test_pesanan_wanita_tercatat_dengan_harga_sama_seperti_pria(): void
    {
        foreach (['Pria', 'Wanita'] as $model) {
            $this->post(route('kustom.simpan', $this->produk()), [
                'opsi' => $this->opsi($model, 'Serena', 'Oblong', 'Pendek', 'Tanpa Saku'),
                'baris' => [['kategori' => 'DEWASA', 'ukuran' => 'L', 'jumlah' => 6]],
                'desain_sumber' => 'katalog',
            ])->assertRedirect(route('keranjang'));
        }

        $this->post('/checkout', ['nama' => 'Tim Putri', 'email' => 'tim@contoh.test', 'no_hp' => '081234567890', 'setuju' => '1'])
            ->assertRedirect();

        $item = Order::firstOrFail()->items()->with(['options', 'rosters'])->get()
            ->keyBy(fn (OrderItem $i) => $i->options->firstWhere('option_nama', 'Model Jersey')?->option_value);

        $this->assertEqualsCanonicalizing(['Pria', 'Wanita'], $item->keys()->all());
        $this->assertSame($item['Pria']->subtotal, $item['Wanita']->subtotal);
    }

    public function test_pesanan_tanpa_model_jersey_ditolak(): void
    {
        $this->post(route('kustom.simpan', $this->produk()), [
            'opsi' => $this->opsi('Serena', 'Oblong', 'Pendek', 'Tanpa Saku'),
            'baris' => [['kategori' => 'DEWASA', 'ukuran' => 'L', 'jumlah' => 6]],
            'desain_sumber' => 'katalog',
        ])->assertSessionHasErrors();
    }
}
