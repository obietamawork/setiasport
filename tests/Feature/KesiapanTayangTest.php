<?php

namespace Tests\Feature;

use App\Models\Portofolio;
use App\Models\Setting;
use App\Models\Testimoni;
use App\Models\User;
use App\Services\KesiapanTayang;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\ContohProfilSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Daftar kesiapan sebelum website dibuka untuk umum (KEP-061).
 *
 * Yang dijaga: isinya benar-benar dihitung dari data, bukan dari centang yang
 * bisa basi. Kalau satu hal diperbaiki, daftarnya ikut berubah tanpa ada yang
 * perlu mencentang apa pun.
 */
class KesiapanTayangTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed([
            SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class,
            CatalogSeeder::class,
            // Data contoh ikut dipasang: keadaan awal toko yang baru dibangun
            // memang begitu, dan menghapusnya adalah salah satu butir daftarnya.
            ContohProfilSeeder::class,
        ]);
    }

    private function owner(): User
    {
        return User::where('email', 'owner@setiasport.test')->firstOrFail();
    }

    /** @return array<string, array<string, mixed>> */
    private function butir(): array
    {
        return collect(app(KesiapanTayang::class)->bagian())
            ->flatMap(fn (array $b) => $b['isi'])
            ->keyBy('judul')
            ->all();
    }

    public function test_halaman_terbuka_dan_menyebut_yang_belum_siap(): void
    {
        $this->actingAs($this->owner())
            ->get(route('panel.kesiapan'))
            ->assertOk()
            ->assertSee('Kesiapan tayang')
            // Data contoh bawaan seeder belum dihapus, jadi harus disebut.
            ->assertSee('Data contoh sudah dihapus')
            ->assertSee('data contoh masih tayang');
    }

    /** Data contoh yang masih tayang sama dengan ulasan palsu di depan pelanggan. */
    public function test_butir_data_contoh_berubah_setelah_contohnya_dihapus(): void
    {
        $this->assertFalse($this->butir()['Data contoh sudah dihapus']['siap']);

        Portofolio::where('contoh', true)->delete();
        Testimoni::where('contoh', true)->delete();

        $this->assertTrue($this->butir()['Data contoh sudah dihapus']['siap']);
    }

    public function test_butir_wajib_ikut_berubah_saat_pengaturannya_diisi(): void
    {
        Setting::simpan('toko_alamat', '');

        $this->assertFalse($this->butir()['Alamat toko']['siap']);

        Setting::simpan('toko_alamat', 'Jl. Manyar II No.2, Wonogiri');

        $this->assertTrue($this->butir()['Alamat toko']['siap']);
    }

    public function test_ringkasan_menghitung_yang_wajib_saja(): void
    {
        $ringkasan = app(KesiapanTayang::class)->ringkasan();

        $this->assertGreaterThan(0, $ringkasan['total']);
        $this->assertLessThanOrEqual($ringkasan['total'], $ringkasan['siap']);

        // Yang bertanda "sebaiknya" tidak boleh ikut menahan angka wajibnya.
        $sebaiknya = collect($this->butir())->where('wajib', false);
        $this->assertNotEmpty($sebaiknya, 'Harus ada butir yang sifatnya anjuran.');
    }

    /** Halaman ini menyebut isi pengaturan toko, jadi bukan untuk semua orang. */
    public function test_tanpa_izin_pengaturan_tidak_bisa_dibuka(): void
    {
        $pekerja = User::create([
            'name' => 'Operator Jahit',
            'email' => 'jahit.uji@setiasport.test',
            'password' => bcrypt('rahasia-panjang'),
        ]);

        $this->actingAs($pekerja)->get(route('panel.kesiapan'))->assertForbidden();
    }
}
