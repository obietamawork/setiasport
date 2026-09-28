<?php

namespace Tests\Feature;

use App\Models\Portofolio;
use App\Models\Setting;
use App\Models\Testimoni;
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
 * Halaman depan bercerita tentang usahanya lebih dulu, katalog belakangan
 * (KEP-024). Yang diuji: bagian yang belum diisi pemilik toko tidak boleh
 * meninggalkan kerangka kosong, dan isinya hanya boleh masuk lewat panel.
 */
class ProfilUsahaTest extends TestCase
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

    /**
     * Susunan halaman depan mengikuti rancangan klien (KEP-062): belanja dulu,
     * bukti belakangan. KEP-024 yang mendahulukan profil sudah direvisi.
     *
     * Yang tetap dijaga: jati diri toko dan alamatnya tetap terbaca di halaman
     * depan, dan langkah memesan kustom tetap dijelaskan, karena keberatan
     * terbesar pemesan pertama kali adalah tidak tahu uangnya dipegang siapa.
     */
    public function test_beranda_mendahulukan_belanja_lalu_bukti_pekerjaan(): void
    {
        Setting::simpan('tentang_judul', 'Konveksi jersey Wonogiri sejak 2011');
        Setting::simpan('toko_alamat', 'Jalan Jenderal Sudirman 10, Wonogiri');

        // Bagian portofolio memang dilewati kalau kosong, jadi urutannya baru
        // bisa diperiksa setelah ada satu pekerjaan yang dipajang.
        Portofolio::create([
            'judul' => 'Jersey futsal SMAN 1 Wonogiri',
            'slug' => 'jersey-futsal-sman-1',
            'instansi' => 'SMAN 1 Wonogiri',
            'tahun' => 2025,
            'aktif' => true,
        ]);

        $halaman = $this->get(route('beranda'))->assertOk();

        $halaman->assertSee('Konveksi jersey Wonogiri sejak 2011');
        $halaman->assertSee('Jalan Jenderal Sudirman 10, Wonogiri');
        $halaman->assertSee('Desain dikirim, Anda setujui');

        $isi = $halaman->getContent();

        $this->assertLessThan(
            strpos($isi, 'Pernah kami kerjakan'),
            strpos($isi, 'Kategori produk'),
            'Kategori produk harus mendahului portofolio.',
        );

        $this->assertLessThan(
            strpos($isi, 'Pernah kami kerjakan'),
            strpos($isi, 'Produk unggulan'),
            'Produk unggulan harus mendahului portofolio.',
        );
    }

    public function test_beranda_menampilkan_toko_dan_konveksi_dengan_jam_masing_masing(): void
    {
        $halaman = $this->get(route('beranda'))->assertOk();

        $halaman->assertSee('Jl. Manyar II No.2, Sukorejo, Giripurwo');
        $halaman->assertSee('Donoharjo, RT.02/RW.01');

        // Jam buka toko dua baris: Minggu tutup lebih awal.
        $this->assertMatchesRegularExpression(
            '/Senin-Sabtu 08\.00-16\.00<br>\s*Minggu 08\.00-14\.00/',
            $halaman->getContent()
        );

        // Tanpa tautan peta dari pemilik toko, alamatnya dijadikan pencarian Google Maps.
        $halaman->assertSee(tautan_peta((string) setting('konveksi_alamat')));
    }

    public function test_bagian_kosong_tidak_meninggalkan_kerangka(): void
    {
        $halaman = $this->get(route('beranda'))->assertOk();

        $halaman->assertDontSee('Pernah kami kerjakan');
        $halaman->assertDontSee('Kata mereka yang sudah pesan');
        $halaman->assertDontSee('Tentang kami');
    }

    /**
     * Media sosial (KEP-063). Kosong berarti ikonnya tidak digambar sama
     * sekali: ikon yang menuju halaman kosong lebih buruk daripada tidak
     * ada ikon, karena pengunjung mengiranya profil resmi yang lupa diisi.
     */
    public function test_media_sosial_kosong_tidak_menampilkan_ikon(): void
    {
        $halaman = $this->get(route('beranda'))->assertOk();

        $halaman->assertDontSee('instagram.com');
        $halaman->assertDontSee('tiktok.com');
    }

    public function test_media_sosial_yang_diisi_tampil_di_beranda(): void
    {
        Setting::simpan('sosial_instagram', 'https://instagram.com/setiasportwonogiri');
        Setting::simpan('sosial_tiktok', 'https://tiktok.com/@setiasportwonogiri');

        $halaman = $this->get(route('beranda'))->assertOk();

        $halaman->assertSee('https://instagram.com/setiasportwonogiri', false);
        $halaman->assertSee('https://tiktok.com/@setiasportwonogiri', false);
        // Facebook tidak diisi, jadi tautannya tidak boleh ikut muncul.
        $halaman->assertDontSee('facebook.com');
    }

    /** Pemilik toko mengisinya lewat panel, di tab Halaman depan. */
    public function test_owner_mengisi_media_sosial_lewat_pengaturan(): void
    {
        $this->actingAs($this->owner())->put(route('panel.pengaturan.update'), [
            'setting' => [
                'sosial_instagram' => 'https://instagram.com/setiasportwonogiri',
                'sosial_tiktok' => '',
                'sosial_facebook' => '',
            ],
        ])->assertRedirect();

        $this->assertSame('https://instagram.com/setiasportwonogiri', setting('sosial_instagram'));
    }

    public function test_portofolio_dan_testimoni_aktif_tampil_di_beranda(): void
    {
        Portofolio::create([
            'judul' => 'Jersey futsal SMAN 1 Wonogiri',
            'slug' => 'jersey-futsal-sman-1',
            'instansi' => 'SMAN 1 Wonogiri',
            'tahun' => 2025,
            'jumlah_pcs' => 24,
            'aktif' => true,
        ]);

        Portofolio::create([
            'judul' => 'Pekerjaan yang belum siap dipajang',
            'slug' => 'belum-siap',
            'aktif' => false,
        ]);

        Testimoni::create([
            'nama' => 'Pak Warno',
            'peran' => 'Pelatih SSB Garuda',
            'isi' => 'Jahitannya rapi dan tepat waktu.',
            'bintang' => 5,
            'aktif' => true,
        ]);

        $halaman = $this->get(route('beranda'))->assertOk();

        $halaman->assertSee('Jersey futsal SMAN 1 Wonogiri');
        $halaman->assertSee('SMAN 1 Wonogiri · 2025 · 24 pcs');
        $halaman->assertSee('Jahitannya rapi dan tepat waktu.');

        // Yang dimatikan harus benar-benar hilang, bukan sekadar diberi tanda.
        $halaman->assertDontSee('Pekerjaan yang belum siap dipajang');
    }

    /**
     * Blade memperlakukan @section('x', null) sebagai "buka blok" dan memulai
     * buffer keluaran yang tidak pernah ditutup. Pengaturan yang belum diisi
     * mengembalikan null, jadi toko yang baru dipasang justru yang kena.
     */
    public function test_pengaturan_profil_kosong_tidak_membocorkan_buffer(): void
    {
        Setting::simpan('tentang_judul', '');
        Setting::simpan('tentang_ringkas', '');

        $awal = ob_get_level();

        $this->get(route('beranda'))->assertOk();

        $this->assertSame($awal, ob_get_level(), 'Halaman depan meninggalkan buffer keluaran terbuka.');
    }

    /**
     * Perbedaan dua jalur belanja dijelaskan di katalog, bukan di halaman depan.
     * Bentuknya diringkas jadi satu baris (KEP-062), tetapi isinya tetap: yang
     * satu siap besok, yang satu dikerjakan sekian hari sejak desain disetujui.
     */
    public function test_pembeda_dua_jalur_pindah_ke_katalog(): void
    {
        $this->get(route('katalog'))
            ->assertOk()
            ->assertSee('Stok toko')
            ->assertSee('siap besok')
            ->assertSee('hari kerja');

        // Sudah menyaring berarti sudah tahu maunya; penjelasnya tidak perlu lagi.
        $this->get(route('katalog', ['jalur' => 'stok']))
            ->assertOk()
            ->assertDontSee('siap besok');
    }

    public function test_owner_menambah_portofolio_lengkap_dengan_foto(): void
    {
        Storage::fake('public');

        $this->actingAs($this->owner())
            ->post(route('panel.portofolio.store'), [
                'judul' => 'Kaos seragam Dinas Pendidikan',
                'instansi' => 'Dinas Pendidikan Wonogiri',
                'kategori' => 'Kaos seragam',
                'tahun' => 2025,
                'jumlah_pcs' => 120,
                'ringkasan' => 'Seratus dua puluh kaos berkerah dengan bordir logo dinas.',
                'aktif' => '1',
                'gambar' => UploadedFile::fake()->image('seragam.jpg', 1600, 1200),
            ])
            ->assertRedirect(route('panel.portofolio.index'));

        $portofolio = Portofolio::firstOrFail();

        $this->assertSame('Dinas Pendidikan Wonogiri', $portofolio->instansi);
        Storage::disk('public')->assertExists($portofolio->gambar_path);
        Storage::disk('public')->assertExists($portofolio->thumb_path);
    }

    public function test_portofolio_wajib_punya_foto(): void
    {
        $this->actingAs($this->owner())
            ->post(route('panel.portofolio.store'), ['judul' => 'Tanpa bukti apa pun'])
            ->assertSessionHasErrors('gambar');

        $this->assertSame(0, Portofolio::count());
    }

    public function test_owner_menambah_testimoni(): void
    {
        $this->actingAs($this->owner())
            ->post(route('panel.testimoni.store'), [
                'nama' => 'Bu Sri',
                'peran' => 'Guru olahraga',
                'isi' => 'Anak-anak senang, ukurannya pas semua.',
                'bintang' => 5,
                'aktif' => '1',
            ])
            ->assertRedirect(route('panel.testimoni.index'));

        $this->assertSame('Bu Sri', Testimoni::firstOrFail()->nama);
    }

    public function test_pengunjung_tidak_bisa_menulis_testimoni_sendiri(): void
    {
        $this->post(route('panel.testimoni.store'), [
            'nama' => 'Akun palsu',
            'isi' => 'Testimoni yang tidak pernah diucapkan siapa pun.',
        ])->assertRedirect(route('panel.login'));

        $this->assertSame(0, Testimoni::count());
    }
}
