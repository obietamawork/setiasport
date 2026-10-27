<?php

namespace Tests\Feature;

use App\Models\Banner;
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
 * Banner promo halaman depan (KEP-092).
 */
class BannerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);
    }

    private function admin(): User
    {
        return User::where('email', 'admin@setiasport.test')->firstOrFail();
    }

    /** @param  array<string, mixed>  $isi */
    private function banner(array $isi = []): Banner
    {
        return Banner::create($isi + [
            'judul' => 'Diskon jersey tim', 'gambar_path' => 'banner/a.webp',
            'teks_di_atas' => true, 'aktif' => true, 'urutan' => 0,
        ]);
    }

    public function test_admin_menambah_banner_dengan_gambar_desktop_dan_hp(): void
    {
        $this->actingAs($this->admin())
            ->post(route('panel.banner.store'), [
                'judul' => 'Diskon 10% jersey tim',
                'subjudul' => 'Minimal 12 pcs.',
                'teks_tombol' => 'Pesan sekarang',
                'tautan' => '/katalog?jalur=kustom',
                'gambar' => UploadedFile::fake()->image('lebar.jpg', 2400, 900),
                'gambar_hp' => UploadedFile::fake()->image('hp.jpg', 1200, 1200),
                'teks_di_atas' => '1', 'aktif' => '1', 'urutan' => 1,
            ])
            ->assertRedirect(route('panel.banner.index'))
            ->assertSessionHas('sukses');

        $banner = Banner::firstOrFail();
        Storage::disk('public')->assertExists([$banner->gambar_path, $banner->gambar_hp_path]);

        // Desktop dikecilkan ke 1920, bukan 1400 seperti foto produk.
        [$lebar] = getimagesizefromstring(Storage::disk('public')->get($banner->gambar_path));
        $this->assertSame(1920, $lebar);
    }

    /** Tautannya diklik pengunjung mana pun: hanya alamat di situs ini atau https. */
    public function test_tautan_berbahaya_ditolak(): void
    {
        foreach (['javascript:alert(1)', '//situs-lain.test', 'http://situs-lain.test', 'data:text/html,x'] as $buruk) {
            $this->actingAs($this->admin())
                ->post(route('panel.banner.store'), [
                    'judul' => 'Uji', 'tautan' => $buruk, 'gambar' => UploadedFile::fake()->image('b.jpg', 800, 300),
                ])
                ->assertSessionHasErrors('tautan');
        }

        $this->assertSame(0, Banner::count());
    }

    public function test_tanggal_selesai_tidak_boleh_sebelum_mulai(): void
    {
        $this->actingAs($this->admin())
            ->post(route('panel.banner.store'), [
                'judul' => 'Uji', 'gambar' => UploadedFile::fake()->image('b.jpg', 800, 300),
                'mulai_pada' => '2026-11-10', 'selesai_pada' => '2026-11-01',
            ])
            ->assertSessionHasErrors('selesai_pada');
    }

    public function test_tanpa_banner_halaman_depan_tetap_hero_teks(): void
    {
        $this->get(route('beranda'))
            ->assertOk()
            ->assertSee('Lihat produk')
            ->assertDontSee('aria-roledescription="carousel"', false);
    }

    /** Disembunyikan, belum waktunya, atau sudah lewat: tidak tayang. Hari terakhir masih tayang. */
    public function test_hanya_banner_yang_sedang_tayang_yang_tampil(): void
    {
        $this->banner(['judul' => 'Tayang hari terakhir', 'selesai_pada' => today()]);
        $this->banner(['judul' => 'Disembunyikan', 'aktif' => false]);
        $this->banner(['judul' => 'Belum mulai', 'mulai_pada' => today()->addDay()]);
        $this->banner(['judul' => 'Sudah lewat', 'selesai_pada' => today()->subDay()]);

        $this->get(route('beranda'))
            ->assertSee('Tayang hari terakhir')
            ->assertDontSee('Disembunyikan')
            ->assertDontSee('Belum mulai')
            ->assertDontSee('Sudah lewat')
            // Satu banner tampil diam, tanpa kontrol geser.
            ->assertDontSee('Banner berikutnya')
            // Judul halaman tetap ada walau hero teksnya digantikan banner.
            ->assertSee('<h1 class="sr-only">', false);
    }

    public function test_dua_banner_bisa_digeser_dan_urut(): void
    {
        $this->banner(['judul' => 'Kedua', 'urutan' => 2]);
        $this->banner(['judul' => 'Pertama', 'urutan' => 1]);

        $this->get(route('beranda'))
            ->assertSeeInOrder(['Pertama', 'Kedua'])
            ->assertSee('Banner berikutnya')
            ->assertSee('aria-roledescription="carousel"', false)
            // Hanya gambar pertama yang dimuat segera.
            ->assertSee('fetchpriority="high"', false)
            ->assertSee('loading="lazy"', false);
    }

    /** Gambar yang sudah bertulisan: judul tidak dobel, seluruh banner jadi tautan. */
    public function test_tanpa_teks_di_atas_seluruh_banner_jadi_tautan(): void
    {
        $this->banner(['judul' => 'Promo Canva', 'teks_di_atas' => false, 'tautan' => '/katalog']);

        $html = $this->get(route('beranda'))->getContent();

        $this->assertStringNotContainsString('<h2', substr($html, strpos($html, 'banner-jalur'), 3000));
        $this->assertStringContainsString('<span class="sr-only">Promo Canva</span>', $html);
    }

    public function test_menghapus_banner_ikut_menghapus_gambarnya(): void
    {
        Storage::disk('public')->put('banner/a.webp', 'x');
        Storage::disk('public')->put('banner/a-hp.webp', 'x');
        $banner = $this->banner(['gambar_hp_path' => 'banner/a-hp.webp']);

        $this->actingAs($this->admin())->delete(route('panel.banner.destroy', $banner))->assertSessionHas('sukses');

        $this->assertSame(0, Banner::count());
        Storage::disk('public')->assertMissing(['banner/a.webp', 'banner/a-hp.webp']);
    }

    public function test_halaman_panel_menampilkan_keadaan_tiap_banner(): void
    {
        $this->banner(['judul' => 'Sedang tayang']);
        $terjadwal = $this->banner(['judul' => 'Terjadwal', 'mulai_pada' => today()->addWeek()]);

        $this->actingAs($this->admin())
            ->get(route('panel.banner.index'))
            ->assertOk()
            ->assertSee('Tayang')
            ->assertSee('Mulai '.today()->addWeek()->translatedFormat('j M Y'));

        $this->actingAs($this->admin())->get(route('panel.banner.create'))->assertOk()->assertSee('1920');
        $this->actingAs($this->admin())->get(route('panel.banner.edit', $terjadwal))->assertOk()->assertSee('Terjadwal');
    }

    public function test_tanpa_izin_profil_tidak_bisa_mengelola_banner(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('panel.banner.index'))
            ->assertForbidden();
    }
}
