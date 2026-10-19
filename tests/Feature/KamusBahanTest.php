<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\OptionValue;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\MaterialSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kamus bahan lintas produk (KEP-072): nama bahan seperti "Milano" adalah
 * istilah dagang konveksi yang tidak dipahami pelanggan awam. Deskripsinya
 * ditulis sekali di kamus, ditautkan ke opsi "Bahan" produk mana pun yang
 * memakainya, lalu tampil begitu pelanggan memilih bahan itu saat memesan.
 */
class KamusBahanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, MaterialSeeder::class, CatalogSeeder::class]);
    }

    private function owner(): User
    {
        return User::where('email', 'owner@setiasport.test')->firstOrFail();
    }

    private function produk(string $sku = 'CUST-JRS-ATASAN'): Product
    {
        return Product::where('sku', $sku)->with('options.values.material')->firstOrFail();
    }

    private function bahan(Product $produk, string $label): OptionValue
    {
        return OptionValue::whereIn('label', [$label])
            ->whereHas('option', fn ($q) => $q->where('product_id', $produk->id)->where('nama', 'Bahan'))
            ->firstOrFail();
    }

    // ---------------------------------------------------------- kamus (panel)

    public function test_pemilik_toko_menambah_bahan_baru(): void
    {
        $this->actingAs($this->owner())
            ->post(route('panel.bahan.store'), [
                'nama' => 'Supertex',
                'deskripsi' => 'Kain tipis dan adem, cocok untuk cuaca panas.',
            ])
            ->assertRedirect(route('panel.bahan.index'));

        $this->assertDatabaseHas('materials', [
            'nama' => 'Supertex',
            'deskripsi' => 'Kain tipis dan adem, cocok untuk cuaca panas.',
        ]);
    }

    public function test_nama_bahan_tidak_boleh_dobel(): void
    {
        // "Milano" sudah ada lewat MaterialSeeder.
        $this->actingAs($this->owner())
            ->post(route('panel.bahan.store'), ['nama' => 'Milano'])
            ->assertSessionHasErrors('nama');
    }

    public function test_deskripsi_boleh_dikosongkan(): void
    {
        $this->actingAs($this->owner())
            ->post(route('panel.bahan.store'), ['nama' => 'Bahan Tanpa Keterangan'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('materials', ['nama' => 'Bahan Tanpa Keterangan', 'deskripsi' => null]);
    }

    public function test_bahan_yang_masih_dipakai_tidak_bisa_dihapus(): void
    {
        $milano = Material::where('nama', 'Milano')->firstOrFail();

        $this->actingAs($this->owner())
            ->delete(route('panel.bahan.destroy', $milano))
            ->assertSessionHasErrors();

        $this->assertDatabaseHas('materials', ['id' => $milano->id]);
    }

    public function test_bahan_yang_tidak_dipakai_bisa_dihapus(): void
    {
        $bahan = Material::create(['nama' => 'Belum Dipakai']);

        $this->actingAs($this->owner())
            ->delete(route('panel.bahan.destroy', $bahan))
            ->assertSessionHas('sukses');

        $this->assertDatabaseMissing('materials', ['id' => $bahan->id]);
    }

    public function test_staf_tanpa_izin_katalog_tidak_bisa_mengelola_bahan(): void
    {
        $pekerja = User::create([
            'name' => 'Operator Jahit',
            'email' => 'jahit.uji@setiasport.test',
            'password' => bcrypt('rahasia-panjang'),
        ]);

        $this->actingAs($pekerja)
            ->get(route('panel.bahan.index'))
            ->assertForbidden();
    }

    // ---------------------------------------------------- menautkan ke opsi

    public function test_nilai_opsi_baru_bisa_ditautkan_ke_bahan(): void
    {
        $produk = $this->produk();
        $opsiBahan = $produk->options->firstWhere('nama', 'Bahan');
        $hyget = Material::where('nama', 'Hyget')->firstOrFail();

        $this->actingAs($this->owner())
            ->post(route('panel.produk.nilai.simpan', [$produk, $opsiBahan]), [
                'label' => 'Bahan Baru',
                'material_id' => $hyget->id,
                'tambahan_harga' => 0,
                'basis' => 'PER_PCS',
            ])
            ->assertSessionHas('sukses');

        $this->assertSame($hyget->id, OptionValue::where('product_option_id', $opsiBahan->id)
            ->where('label', 'Bahan Baru')->value('material_id'));
    }

    public function test_menautkan_bahan_tetap_opsional(): void
    {
        $produk = $this->produk();
        $opsiBahan = $produk->options->firstWhere('nama', 'Bahan');

        $this->actingAs($this->owner())
            ->post(route('panel.produk.nilai.simpan', [$produk, $opsiBahan]), [
                'label' => 'Bahan Tanpa Kamus',
                'tambahan_harga' => 0,
                'basis' => 'PER_PCS',
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull(OptionValue::where('product_option_id', $opsiBahan->id)
            ->where('label', 'Bahan Tanpa Kamus')->value('material_id'));
    }

    public function test_bahan_yang_tidak_ada_di_kamus_ditolak(): void
    {
        $produk = $this->produk();
        $opsiBahan = $produk->options->firstWhere('nama', 'Bahan');

        $this->actingAs($this->owner())
            ->post(route('panel.produk.nilai.simpan', [$produk, $opsiBahan]), [
                'label' => 'Bahan Ngawur',
                'material_id' => 999_999,
                'tambahan_harga' => 0,
                'basis' => 'PER_PCS',
            ])
            ->assertSessionHasErrors('material_id');
    }

    /** CatalogSeeder menautkan opsi "Bahan" yang labelnya cocok dengan kamus. */
    public function test_seeder_katalog_menautkan_opsi_bahan_otomatis(): void
    {
        $milano = $this->bahan($this->produk(), 'Milano');

        $this->assertNotNull($milano->material_id);
        $this->assertSame('Milano', $milano->material->nama);
    }

    /** Opsi selain "Bahan" (mis. Model Lengan) tidak ditautkan, walau labelnya kebetulan cocok. */
    public function test_opsi_selain_bahan_tidak_ikut_ditautkan(): void
    {
        $lengan = OptionValue::whereIn('label', ['Pendek', 'Panjang'])
            ->whereHas('option', fn ($q) => $q->where('product_id', $this->produk()->id)->where('nama', 'Model Lengan'))
            ->get();

        $this->assertTrue($lengan->every(fn (OptionValue $v) => $v->material_id === null));
    }

    // --------------------------------------------------- tampilan pelanggan

    public function test_deskripsi_bahan_tampil_di_konfigurator_setelah_dipilih(): void
    {
        $produk = $this->produk();
        $milano = $this->bahan($produk, 'Milano');
        $milano->material->update(['deskripsi' => 'Kain polyester yang lebih tebal dan tahan lama.']);

        $halaman = $this->get(route('kustom.buat', $produk))->assertOk();

        // Dikirim sebagai data JS, dibaca Alpine begitu nilainya dipilih --
        // bukan ditampilkan langsung di tiap tombol (akan ramai kalau semua bahan tampil sekaligus).
        $halaman->assertSee('Kain polyester yang lebih tebal dan tahan lama.', false);
    }

    public function test_bahan_tanpa_deskripsi_tidak_mengirim_teks_kosong_yang_membingungkan(): void
    {
        $produk = $this->produk();
        $jarum = $this->bahan($produk, 'Jarum');

        $this->assertNull($jarum->material?->deskripsi);

        // Halaman tetap termuat normal; deskripsi null diteruskan apa adanya ke JS (null, bukan string kosong).
        $this->get(route('kustom.buat', $produk))->assertOk();
    }

    public function test_deskripsi_bahan_tampil_di_halaman_produk(): void
    {
        $produk = $this->produk();
        $milano = $this->bahan($produk, 'Milano');
        $milano->material->update(['deskripsi' => 'Kain adem dan cepat kering.']);

        $this->get(route('produk', $produk))
            ->assertOk()
            ->assertSee('Kain adem dan cepat kering.');
    }
}
