<?php

namespace Tests\Feature;

use App\Models\Portofolio;
use App\Models\Testimoni;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Halaman "Lihat semua" untuk portofolio dan testimoni (KEP-106). Beranda
 * hanya memajang sebagian; di sini semuanya, tanpa yang belum diaktifkan.
 */
class GaleriPublikTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    public function test_galeri_kosong_dijawab_tidak_ditemukan(): void
    {
        Portofolio::create(['judul' => 'Belum siap', 'slug' => 'belum-siap', 'aktif' => false]);

        $this->get(route('portofolio'))->assertNotFound();
        $this->get(route('testimoni'))->assertNotFound();
        $this->get(route('sitemap'))
            ->assertOk()
            ->assertDontSee(route('portofolio'))
            ->assertDontSee(route('testimoni'));
    }

    public function test_galeri_portofolio_memuat_semua_yang_aktif_beserta_ringkasannya(): void
    {
        foreach (range(1, 5) as $i) {
            Portofolio::create([
                'judul' => "Jersey tim $i",
                'slug' => "jersey-tim-$i",
                'ringkasan' => "Ringkasan pekerjaan $i",
                'aktif' => true,
                'urutan' => $i,
            ]);
        }
        Portofolio::create(['judul' => 'Masih disembunyikan', 'slug' => 'tersembunyi', 'aktif' => false]);

        // Beranda memajang empat dan mengarah ke galeri lengkap.
        $this->get(route('beranda'))
            ->assertOk()
            ->assertSee('Jersey tim 4')
            ->assertDontSee('Jersey tim 5')
            ->assertSee(route('portofolio'));

        $this->get(route('portofolio'))
            ->assertOk()
            ->assertSee('<title>Galeri portofolio |', false)
            ->assertSee('Jersey tim 5')
            ->assertSee('Ringkasan pekerjaan 5')
            ->assertDontSee('Masih disembunyikan');

        $this->get(route('sitemap'))->assertSee(route('portofolio'));
    }

    public function test_kartu_testimoni_membawa_inisial_dan_nilai_yang_terbaca(): void
    {
        Testimoni::create([
            'nama' => 'Budi  Santoso Wibowo',
            'peran' => 'Pelatih SSB',
            'isi' => 'Jahitannya rapi dan tepat waktu.',
            'bintang' => 4,
            'aktif' => true,
        ]);

        foreach ([route('beranda'), route('testimoni')] as $alamat) {
            $halaman = $this->get($alamat)
                ->assertOk()
                ->assertSee('role="img" aria-label="Nilai 4 dari 5"', false)
                ->assertSee('Jahitannya rapi dan tepat waktu.');
            $this->assertMatchesRegularExpression('/>\s*BS\s*</', $halaman->getContent());
        }

        $this->get(route('beranda'))->assertSee(route('testimoni'));
    }
}
