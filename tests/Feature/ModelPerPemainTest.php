<?php

namespace Tests\Feature;

use App\Enums\KategoriUkuran;
use App\Models\OptionValue;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\User;
use App\Services\PriceCalculator;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Model baju per pemain (KEP-123): satu pesanan jersey boleh sebagian
 * berkerah, sebagian oblong. Tambahan kerah hanya untuk baris berkerah;
 * minimum pesanan dan potongan borongan tetap dihitung dari total pesanan.
 */
class ModelPerPemainTest extends TestCase
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
        return Product::where('sku', 'CUST-JRS-ATASAN')->with(['options.values', 'prices'])->firstOrFail();
    }

    private function model(string $label, ?Product $produk = null): OptionValue
    {
        $opsi = ($produk ?? $this->produk())->options->firstWhere('nama', 'Model Baju');

        return $opsi->values->firstWhere('label', $label);
    }

    /** @return list<int> pilihan wajib lain (bahan, lengan, ...) tanpa Model Baju */
    private function opsiLain(): array
    {
        return $this->produk()->options
            ->where('wajib', true)
            ->reject(fn (ProductOption $o) => $o->per_pemain)
            ->map(fn (ProductOption $o) => $o->values->where('aktif', true)->first()?->id)
            ->filter()->values()->all();
    }

    private function kategori(): string
    {
        return (string) ($this->produk()->prices->first()?->kategori?->value ?? '');
    }

    private function ukuran(): string
    {
        $kat = KategoriUkuran::tryFrom($this->kategori());

        return $this->produk()->ukuranTersedia($kat)[1] ?? $this->produk()->ukuranTersedia($kat)[0];
    }

    public function test_opsi_model_baju_ditandai_per_pemain(): void
    {
        $this->assertTrue($this->produk()->options->firstWhere('nama', 'Model Baju')->per_pemain);
    }

    public function test_tambahan_kerah_hanya_untuk_baris_berkerah_dan_minimum_dihitung_total(): void
    {
        $rincian = app(PriceCalculator::class)->kustomPerUkuran($this->produk(), [
            ['kategori' => $this->kategori(), 'ukuran' => $this->ukuran(), 'jumlah' => 8, 'model' => $this->model('Pakai Kerah')->id],
            ['kategori' => $this->kategori(), 'ukuran' => $this->ukuran(), 'jumlah' => 6, 'model' => $this->model('Oblong')->id],
        ], $this->opsiLain());

        [$kerah, $oblong] = $rincian->baris;

        $this->assertSame(10_000, $kerah['harga_satuan'] - $oblong['harga_satuan']);
        $this->assertSame('Pakai Kerah', $kerah['model']);
        $this->assertSame(14, $rincian->qty);
    }

    public function test_pilihan_di_tingkat_barang_jadi_bawaan_semua_baris(): void
    {
        $rincian = app(PriceCalculator::class)->kustomPerUkuran($this->produk(), [
            ['kategori' => $this->kategori(), 'ukuran' => $this->ukuran(), 'jumlah' => 12],
        ], [...$this->opsiLain(), $this->model('Pakai Kerah')->id]);

        $this->assertSame($this->model('Pakai Kerah')->id, $rincian->baris[0]['option_value_id']);
    }

    public function test_model_dari_produk_lain_ditolak(): void
    {
        $lain = Product::where('sku', 'CUST-SETELAN')->with('options.values')->firstOrFail();

        $this->expectException(InvalidArgumentException::class);

        app(PriceCalculator::class)->kustomPerUkuran($this->produk(), [
            ['kategori' => $this->kategori(), 'ukuran' => $this->ukuran(), 'jumlah' => 12, 'model' => $this->model('Pakai Kerah', $lain)->id],
        ], $this->opsiLain());
    }

    public function test_pesanan_campuran_tersimpan_per_pemain_sampai_lembar_kerja(): void
    {
        $produk = $this->produk();
        $opsi = collect($this->opsiLain())->mapWithKeys(fn ($id) => [OptionValue::find($id)->product_option_id => $id])->all();

        $this->post(route('kustom.simpan', $produk), [
            'opsi' => $opsi,
            'desain_sumber' => 'katalog',
            'baris' => [
                ['kategori' => $this->kategori(), 'ukuran' => $this->ukuran(), 'jumlah' => 1, 'nama_punggung' => 'ANDI', 'nomor' => '10', 'model' => $this->model('Pakai Kerah')->id],
                ['kategori' => $this->kategori(), 'ukuran' => $this->ukuran(), 'jumlah' => 11, 'model' => $this->model('Oblong')->id],
            ],
        ])->assertSessionHasNoErrors();

        $this->post('/checkout', [
            'nama' => 'Tim Voli', 'email' => 'voli@contoh.test', 'no_hp' => '081234567890', 'setuju' => '1',
        ])->assertSessionHasNoErrors();

        $item = Order::latest('id')->firstOrFail()->items()->with('rosters')->firstOrFail();
        $andi = $item->rosters->firstWhere('nama_punggung', 'ANDI');

        $this->assertSame('Pakai Kerah', $andi->model);
        $this->assertSame($this->model('Pakai Kerah')->id, $andi->option_value_id);
        $this->assertStringContainsString('Pakai Kerah', $item->ringkasanUkuran());
        $this->assertStringContainsString('Oblong', $item->ringkasanUkuran());

        $owner = User::where('email', 'owner@setiasport.test')->firstOrFail();
        $this->actingAs($owner)
            ->get(route('panel.pesanan.worksheet', $item->order))
            ->assertOk()
            ->assertSee('Pakai Kerah');
    }

    public function test_pelanggan_tidak_bisa_mengganti_susunan_model_lewat_daftar_pemain(): void
    {
        $produk = $this->produk();
        $opsi = collect($this->opsiLain())->mapWithKeys(fn ($id) => [OptionValue::find($id)->product_option_id => $id])->all();

        $this->post(route('kustom.simpan', $produk), [
            'opsi' => $opsi, 'desain_sumber' => 'katalog',
            'baris' => [
                ['kategori' => $this->kategori(), 'ukuran' => $this->ukuran(), 'jumlah' => 2, 'model' => $this->model('Pakai Kerah')->id],
                ['kategori' => $this->kategori(), 'ukuran' => $this->ukuran(), 'jumlah' => 10, 'model' => $this->model('Oblong')->id],
            ],
        ]);
        $this->post('/checkout', ['nama' => 'Tim', 'email' => 'tim@contoh.test', 'no_hp' => '081234567890', 'setuju' => '1']);

        $order = Order::latest('id')->firstOrFail();
        $item = $order->items()->firstOrFail();

        // Semua baris dijadikan oblong: tagihannya akan turun diam-diam.
        $this->post(route('lacak.item.roster', [$order->access_token, $item]), [
            'roster' => [
                ['kategori' => $this->kategori(), 'ukuran' => $this->ukuran(), 'jumlah' => 12, 'model' => $this->model('Oblong')->id],
            ],
        ])->assertSessionHasErrors('roster');

        $this->assertSame(2, (int) $item->rosters()->where('option_value_id', $this->model('Pakai Kerah')->id)->sum('jumlah'));
    }

    public function test_panel_bisa_menandai_opsi_per_pemain(): void
    {
        $produk = $this->produk();
        $bahan = $produk->options->firstWhere('nama', 'Bahan');
        $owner = User::where('email', 'owner@setiasport.test')->firstOrFail();

        $this->actingAs($owner)
            ->post(route('panel.produk.opsi.per-pemain', [$produk, $bahan]))
            ->assertSessionHas('sukses');

        // Paling banyak satu opsi per produk: Model Baju dilepas.
        $this->assertTrue($bahan->fresh()->per_pemain);
        $this->assertFalse($produk->options->firstWhere('nama', 'Model Baju')->fresh()->per_pemain);
    }
}
