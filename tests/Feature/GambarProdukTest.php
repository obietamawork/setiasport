<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Gambar produk boleh datang dari dua sumber: diunggah ke hosting atau ditunjuk
 * ke CDN. Yang diunggah wajib dikecilkan, karena foto mentah dari HP lazimnya
 * 3-8 MB dan akan memberatkan katalog sekaligus menghabiskan kuota disk.
 */
class GambarProdukTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);
        $this->actingAs(User::where('email', 'admin@setiasport.test')->firstOrFail());
    }

    private function produk(): Product
    {
        return Product::where('sku', 'BOLA-MITRE-01')->firstOrFail();
    }

    public function test_unggahan_dikecilkan_dan_dikonversi_ke_webp(): void
    {
        $produk = $this->produk();

        $this->post(route('panel.produk.gambar.simpan', $produk), [
            'berkas' => UploadedFile::fake()->image('foto-hp.jpg', 3000, 2000),
        ])->assertSessionHas('sukses');

        $gambar = $produk->images()->firstOrFail();

        // WebP bila GD mendukungnya, JPEG bila tidak. Yang penting unggahannya
        // tidak pernah gagal hanya karena kompilasi GD di server berbeda.
        $ext = function_exists('imagewebp') ? '.webp' : '.jpg';
        $this->assertStringEndsWith($ext, $gambar->path);
        $this->assertStringEndsWith($ext, $gambar->thumb_path);
        $this->assertSame(1400, $gambar->lebar);   // dari 3000px
        $this->assertNull($gambar->external_url);
        $this->assertFalse($gambar->dariCdn());

        Storage::disk('public')->assertExists($gambar->path);
        Storage::disk('public')->assertExists($gambar->thumb_path);
    }

    public function test_gambar_kecil_tidak_diperbesar(): void
    {
        $produk = $this->produk();

        $this->post(route('panel.produk.gambar.simpan', $produk), [
            'berkas' => UploadedFile::fake()->image('kecil.png', 600, 600),
        ])->assertSessionHas('sukses');

        $this->assertSame(600, $produk->images()->firstOrFail()->lebar);
    }

    public function test_tautan_cdn_dipakai_apa_adanya_tanpa_disalin(): void
    {
        $produk = $this->produk();

        $this->post(route('panel.produk.gambar.simpan', $produk), [
            'external_url' => 'https://cdn.contoh.com/jersey.webp',
            'alt' => 'Jersey merah',
        ])->assertSessionHas('sukses');

        $gambar = $produk->images()->firstOrFail();

        $this->assertTrue($gambar->dariCdn());
        $this->assertNull($gambar->path);
        $this->assertSame('https://cdn.contoh.com/jersey.webp', $gambar->url);
        $this->assertSame('https://cdn.contoh.com/jersey.webp', $gambar->thumb_url);
    }

    public function test_harus_memilih_salah_satu_sumber(): void
    {
        $this->post(route('panel.produk.gambar.simpan', $this->produk()), ['alt' => 'Tanpa apa-apa'])
            ->assertSessionHasErrors();

        $this->assertSame(0, $this->produk()->images()->count());
    }

    public function test_berkas_bukan_gambar_ditolak(): void
    {
        $this->post(route('panel.produk.gambar.simpan', $this->produk()), [
            'berkas' => UploadedFile::fake()->create('daftar-harga.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('berkas');
    }

    public function test_gambar_utama_bisa_diganti(): void
    {
        $produk = $this->produk();

        foreach (['satu.jpg', 'dua.jpg'] as $nama) {
            $this->post(route('panel.produk.gambar.simpan', $produk), [
                'berkas' => UploadedFile::fake()->image($nama, 800, 800),
            ]);
        }

        $kedua = $produk->images()->orderByDesc('urutan')->firstOrFail();

        $this->post(route('panel.produk.gambar.utama', [$produk, $kedua]))->assertSessionHas('sukses');

        $this->assertSame($kedua->id, $produk->fresh()->images->first()->id);
    }

    public function test_gambar_bisa_ditandai_mewakili_satu_warna(): void
    {
        $sepatu = Product::where('sku', 'SEPATU-ORT-CTL')->firstOrFail();

        $this->post(route('panel.produk.gambar.simpan', $sepatu), [
            'external_url' => 'https://cdn.contoh.com/sepatu-merah.webp',
            'warna' => 'Merah',
        ])->assertSessionHas('sukses');

        $this->assertSame('Merah', $sepatu->images()->firstOrFail()->warna);
    }

    public function test_warna_yang_bukan_varian_produk_ditolak(): void
    {
        $sepatu = Product::where('sku', 'SEPATU-ORT-CTL')->firstOrFail();

        // "Kuning" bukan salah satu varian sepatu ini (hanya Hitam dan Merah).
        // Tanpa penjagaan ini, salah ketik di kotak warna membuat gambar diam-diam
        // tidak pernah cocok dengan tombol warna di halaman produk.
        $this->post(route('panel.produk.gambar.simpan', $sepatu), [
            'external_url' => 'https://cdn.contoh.com/sepatu-kuning.webp',
            'warna' => 'Kuning',
        ])->assertSessionHasErrors('warna');

        $this->assertSame(0, $sepatu->images()->count());
    }

    public function test_halaman_produk_mengirim_peta_warna_ke_galeri(): void
    {
        $sepatu = Product::where('sku', 'SEPATU-ORT-CTL')->firstOrFail();

        $this->post(route('panel.produk.gambar.simpan', $sepatu), [
            'external_url' => 'https://cdn.contoh.com/sepatu-hitam.webp',
        ]);
        $this->post(route('panel.produk.gambar.simpan', $sepatu), [
            'external_url' => 'https://cdn.contoh.com/sepatu-merah.webp',
            'warna' => 'Merah',
        ]);

        // Gambar kedua (indeks 1) yang ditandai Merah; JS galeri membaca
        // peta ini untuk tahu gambar mana yang harus tampil saat warna itu
        // dipilih (KEP-066).
        // @js() menyandikan lewat JSON.parse('...') dengan kutip di-escape
        // jadi urutan enam karakter backslash-u-0-0-2-2, bukan JSON mentah.
        $kutip = '\\u0022';
        $this->get(route('produk', $sepatu))
            ->assertOk()
            ->assertSee($kutip.'Merah'.$kutip.':1', false);
    }

    public function test_menghapus_gambar_ikut_membuang_berkasnya(): void
    {
        $produk = $this->produk();

        $this->post(route('panel.produk.gambar.simpan', $produk), [
            'berkas' => UploadedFile::fake()->image('hapus.jpg', 900, 900),
        ]);

        $gambar = $produk->images()->firstOrFail();
        [$path, $thumb] = [$gambar->path, $gambar->thumb_path];

        $this->delete(route('panel.produk.gambar.hapus', [$produk, $gambar]))->assertSessionHas('sukses');

        // Berkas yatim adalah cara paling sunyi menghabiskan kuota disk.
        Storage::disk('public')->assertMissing($path);
        Storage::disk('public')->assertMissing($thumb);
        $this->assertSame(0, $produk->images()->count());
    }
}
