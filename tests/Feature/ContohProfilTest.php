<?php

namespace Tests\Feature;

use App\Models\Portofolio;
use App\Models\Testimoni;
use App\Models\User;
use App\Services\ContohProfil;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\ContohProfilSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Data contoh portofolio dan testimoni (KEP-035). Testimoni karangan yang
 * tayang di website asli sama dengan ulasan palsu, jadi yang diuji adalah
 * pagarnya: tidak ikut instalasi, tidak jalan di produksi, selalu terlihat
 * sebagai contoh di panel, dan terhapus bersih tanpa menyentuh isian asli.
 */
class ContohProfilTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);
    }

    private function owner(): User
    {
        return User::where('email', 'owner@setiasport.test')->firstOrFail();
    }

    public function test_contoh_tampil_di_halaman_depan_lengkap_dengan_gambar(): void
    {
        $this->seed(ContohProfilSeeder::class);

        $this->assertSame(6, Portofolio::where('contoh', true)->count());
        $this->assertSame(6, Testimoni::where('contoh', true)->count());

        foreach (Portofolio::all() as $p) {
            Storage::disk('public')->assertExists([$p->gambar_path, $p->thumb_path]);
        }

        $this->get(route('beranda'))
            ->assertOk()
            ->assertSee('Pernah kami kerjakan')
            ->assertSee('Jersey futsal full printing')
            ->assertSee('Kata mereka yang sudah pesan');
    }

    public function test_menjalankan_dua_kali_tidak_menggandakan_isi(): void
    {
        $this->seed(ContohProfilSeeder::class);
        $this->seed(ContohProfilSeeder::class);

        $this->assertSame(6, Portofolio::count());
        $this->assertSame(6, Testimoni::count());
    }

    public function test_instalasi_baru_tidak_memuat_data_contoh(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(0, Portofolio::count());
        $this->assertSame(0, Testimoni::count());
    }

    public function test_seeder_contoh_menolak_berjalan_di_produksi(): void
    {
        $this->app['env'] = 'production';

        // Persis perintah yang akan diketik orang di server: --force melewati
        // konfirmasi Laravel, jadi yang menghentikannya harus seeder itu sendiri.
        $this->artisan('db:seed', ['--class' => ContohProfilSeeder::class, '--force' => true])
            ->expectsOutputToContain('tidak dipasang di produksi');

        $this->assertSame(0, Portofolio::count());
        $this->assertSame(0, Testimoni::count());
    }

    public function test_dashboard_dan_panel_memperingatkan_selama_contoh_masih_ada(): void
    {
        $this->seed(ContohProfilSeeder::class);

        $this->actingAs($this->owner())->get(route('panel.dashboard'))
            ->assertSee('Halaman depan masih memuat data contoh');

        $this->actingAs($this->owner())->get(route('panel.portofolio.index'))
            ->assertSee('Hapus semua data contoh')
            ->assertSee('Contoh');
    }

    public function test_hapus_contoh_tidak_menyentuh_isian_asli(): void
    {
        $asli = Portofolio::create([
            'judul' => 'Pekerjaan asli klien', 'slug' => 'asli', 'aktif' => true,
            'gambar_path' => 'portofolio/asli.webp',
        ]);
        Testimoni::create(['nama' => 'Pelanggan asli', 'isi' => 'Ucapan sungguhan.', 'aktif' => true]);

        $this->seed(ContohProfilSeeder::class);
        $gambarContoh = Portofolio::where('contoh', true)->pluck('gambar_path');

        $this->actingAs($this->owner())
            ->delete(route('panel.profil.contoh.hapus'))
            ->assertSessionHas('sukses');

        $this->assertSame([$asli->id], Portofolio::pluck('id')->all());
        $this->assertSame(['Pelanggan asli'], Testimoni::pluck('nama')->all());
        foreach ($gambarContoh as $g) {
            Storage::disk('public')->assertMissing($g);
        }

        $this->actingAs($this->owner())->get(route('panel.dashboard'))
            ->assertDontSee('Halaman depan masih memuat data contoh');
    }

    /**
     * Isi karangan tidak boleh kehilangan tandanya diam-diam. Mengubah urutan
     * atau menyembunyikan tidak membuatnya jadi isian asli.
     */
    public function test_mengubah_urutan_saja_tidak_melepas_tanda_contoh(): void
    {
        $this->seed(ContohProfilSeeder::class);
        $t = Testimoni::where('contoh', true)->firstOrFail();

        $this->actingAs($this->owner())->put(route('panel.testimoni.update', $t), [
            'nama' => $t->nama, 'peran' => $t->peran, 'isi' => $t->isi, 'bintang' => $t->bintang,
            'urutan' => 9, 'aktif' => '1',
        ]);

        $this->assertTrue($t->fresh()->contoh);
    }

    /** Contoh yang isinya diganti pemilik toko sudah jadi miliknya. */
    public function test_contoh_yang_isinya_diganti_tidak_ikut_terhapus(): void
    {
        $this->seed(ContohProfilSeeder::class);
        $t = Testimoni::where('contoh', true)->firstOrFail();

        $this->actingAs($this->owner())->put(route('panel.testimoni.update', $t), [
            'nama' => 'Pak Slamet', 'peran' => 'Pelatih', 'isi' => 'Ucapan asli dari pelanggan.',
            'bintang' => 5, 'aktif' => '1',
        ]);

        app(ContohProfil::class)->hapus();

        $this->assertSame(['Pak Slamet'], Testimoni::pluck('nama')->all());
    }

    public function test_contoh_tidak_menyebut_lembaga_sungguhan(): void
    {
        $this->seed(ContohProfilSeeder::class);

        // Pola nama lembaga resmi yang mudah dikira pesanan sungguhan.
        foreach (Portofolio::pluck('instansi') as $instansi) {
            $this->assertDoesNotMatchRegularExpression('/\b(SMAN?|SMPN?|SDN?|MAN?)\s*\d|Dinas\s+\w|Pemkab|Kabupaten\s+Wonogiri/i', $instansi);
        }
    }
}
