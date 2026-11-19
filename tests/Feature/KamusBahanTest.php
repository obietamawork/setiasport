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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
        Storage::fake('public');
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, MaterialSeeder::class, CatalogSeeder::class]);
    }

    private function foto(string $nama = 'tekstur.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($nama, 1200, 900);
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
                'gambar' => $this->foto(),
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
            ->post(route('panel.bahan.store'), ['nama' => 'Bahan Tanpa Keterangan', 'gambar' => $this->foto()])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('materials', ['nama' => 'Bahan Tanpa Keterangan', 'deskripsi' => null]);
    }

    // ------------------------------------------------------- foto bahan

    /*
     * Foto bahan (KEP-110): tiap bahan punya emboss dan tekstur sendiri, dan
     * itu tidak bisa dijelaskan dengan kalimat. Fotonya wajib, dan ikut
     * tampil ke pelanggan di samping penjelasan bahan.
     */

    public function test_bahan_baru_wajib_berfoto(): void
    {
        $this->actingAs($this->owner())
            ->post(route('panel.bahan.store'), ['nama' => 'Tanpa Foto'])
            ->assertSessionHasErrors('gambar');

        $this->assertDatabaseMissing('materials', ['nama' => 'Tanpa Foto']);
    }

    public function test_foto_bahan_disimpan_beserta_versi_kecil(): void
    {
        $this->actingAs($this->owner())
            ->post(route('panel.bahan.store'), ['nama' => 'Supertex', 'gambar' => $this->foto()])
            ->assertSessionHasNoErrors();

        $bahan = Material::where('nama', 'Supertex')->firstOrFail();

        $this->assertStringStartsWith('bahan/', $bahan->gambar_path);
        Storage::disk('public')->assertExists([$bahan->gambar_path, $bahan->thumb_path]);
    }

    public function test_bahan_lama_tanpa_foto_wajib_diberi_foto_saat_diubah(): void
    {
        $milano = Material::where('nama', 'Milano')->firstOrFail();

        $this->actingAs($this->owner())
            ->put(route('panel.bahan.update', $milano), ['nama' => 'Milano', 'deskripsi' => 'Tebal.'])
            ->assertSessionHasErrors('gambar');
    }

    public function test_bahan_yang_sudah_berfoto_boleh_diubah_tanpa_unggah_ulang(): void
    {
        $milano = Material::where('nama', 'Milano')->firstOrFail();
        $milano->forceFill(['gambar_path' => 'bahan/lama.webp', 'thumb_path' => 'bahan/lama-kecil.webp'])->save();

        $this->actingAs($this->owner())
            ->put(route('panel.bahan.update', $milano), ['nama' => 'Milano', 'deskripsi' => 'Tebal.'])
            ->assertSessionHasNoErrors();

        $this->assertSame('bahan/lama.webp', $milano->fresh()->gambar_path);
    }

    public function test_mengganti_foto_menghapus_berkas_lama(): void
    {
        Storage::disk('public')->put('bahan/lama.webp', 'x');
        Storage::disk('public')->put('bahan/lama-kecil.webp', 'x');
        $milano = Material::where('nama', 'Milano')->firstOrFail();
        $milano->forceFill(['gambar_path' => 'bahan/lama.webp', 'thumb_path' => 'bahan/lama-kecil.webp'])->save();

        $this->actingAs($this->owner())
            ->put(route('panel.bahan.update', $milano), ['nama' => 'Milano', 'gambar' => $this->foto('baru.jpg')])
            ->assertSessionHasNoErrors();

        Storage::disk('public')->assertMissing(['bahan/lama.webp', 'bahan/lama-kecil.webp']);
        $this->assertNotSame('bahan/lama.webp', $milano->fresh()->gambar_path);
    }

    public function test_menghapus_bahan_ikut_menghapus_fotonya(): void
    {
        Storage::disk('public')->put('bahan/x.webp', 'x');
        $bahan = Material::create(['nama' => 'Belum Dipakai']);
        $bahan->forceFill(['gambar_path' => 'bahan/x.webp'])->save();

        $this->actingAs($this->owner())->delete(route('panel.bahan.destroy', $bahan));

        Storage::disk('public')->assertMissing('bahan/x.webp');
    }

    public function test_daftar_bahan_menandai_yang_belum_berfoto(): void
    {
        $this->actingAs($this->owner())
            ->get(route('panel.bahan.index'))
            ->assertOk()
            ->assertSee('Belum ada foto');
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

    public function test_foto_bahan_tampil_di_halaman_produk_walau_tanpa_deskripsi(): void
    {
        $produk = $this->produk();
        $milano = $this->bahan($produk, 'Milano');
        $milano->material->forceFill([
            'deskripsi' => null, 'gambar_path' => 'bahan/milano.webp', 'thumb_path' => 'bahan/milano-kecil.webp',
        ])->save();

        $this->get(route('produk', $produk))
            ->assertOk()
            ->assertSee(Storage::disk('public')->url('bahan/milano-kecil.webp'), false);
    }

    public function test_foto_bahan_dikirim_ke_konfigurator(): void
    {
        $produk = $this->produk();
        $milano = $this->bahan($produk, 'Milano');
        $milano->material->forceFill(['gambar_path' => 'bahan/milano.webp', 'thumb_path' => 'bahan/milano-kecil.webp'])->save();

        // Dikirim sebagai data JS lewat @js, yang meng-escape garis miringnya
        // dua kali; yang diperiksa cukup nama berkasnya.
        $this->get(route('kustom.buat', $produk))
            ->assertOk()
            ->assertSee('milano-kecil.webp', false);
    }

    public function test_foto_bahan_tampil_di_tombol_pilihan_konfigurator(): void
    {
        $produk = $this->produk();
        $milano = $this->bahan($produk, 'Milano');
        $milano->material->forceFill(['gambar_path' => 'bahan/milano.webp', 'thumb_path' => 'bahan/milano-kecil.webp'])->save();

        // KEP-140: fotonya tampil di tiap tombol, bukan baru muncul setelah
        // dipilih, supaya pelanggan membandingkan tekstur sebelum memilih.
        $this->get(route('kustom.buat', $produk))
            ->assertOk()
            ->assertSee('src="'.Storage::disk('public')->url('bahan/milano-kecil.webp').'"', false)
            ->assertSee('alt="Tekstur bahan Milano"', false);
    }
}
