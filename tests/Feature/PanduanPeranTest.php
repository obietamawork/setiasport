<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\User;
use App\Support\IsiPanduan;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Panduan internal A sampai Z (KEP-162): tiap orang membaca bagian yang sesuai
 * izinnya, pemilik bisa melihatnya sebagai peran lain, dan panduan konveksi
 * bisa dicetak untuk mengajari karyawan.
 */
class PanduanPeranTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class]);
    }

    private function staf(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function tanpaPeran(): User
    {
        return User::create(['name' => 'Kasir', 'email' => 'kasir@contoh.test', 'password' => 'rahasia-123', 'aktif' => true]);
    }

    public function test_pemilik_melihat_alur_lengkap_bagian_pemilik_dan_konveksi(): void
    {
        $this->actingAs($this->staf('owner@setiasport.test'))->get(route('panel.bantuan'))
            ->assertOk()
            ->assertSee('id="alur-kustom"', false)
            ->assertSee('id="pengaturan"', false)
            ->assertSee('id="pengguna"', false)
            ->assertSee('id="konveksi"', false);
    }

    public function test_admin_tidak_melihat_bagian_khusus_pemilik(): void
    {
        $this->actingAs($this->staf('admin@setiasport.test'))->get(route('panel.bantuan'))
            ->assertOk()
            ->assertSee('id="alur-kustom"', false)
            ->assertSee('id="konveksi"', false)
            ->assertDontSee('id="pengaturan"', false)
            ->assertDontSee('id="pengguna"', false);
    }

    public function test_staf_tanpa_izin_hanya_melihat_bagian_umum(): void
    {
        $this->actingAs($this->tanpaPeran())->get(route('panel.bantuan'))
            ->assertOk()
            ->assertSee('Baru pegang panel ini?')
            ->assertDontSee('id="alur-kustom"', false)
            ->assertDontSee('id="konveksi"', false);
    }

    public function test_pemilik_bisa_melihat_panduan_sebagai_admin(): void
    {
        $this->actingAs($this->staf('owner@setiasport.test'))->get(route('panel.bantuan', ['sebagai' => 'admin']))
            ->assertOk()
            ->assertSee('Anda melihat panduan sebagai Admin')
            ->assertSee('id="alur-kustom"', false)
            ->assertDontSee('id="pengaturan"', false);
    }

    public function test_admin_tidak_bisa_meminjam_tampilan_pemilik(): void
    {
        $this->actingAs($this->staf('admin@setiasport.test'))->get(route('panel.bantuan', ['sebagai' => 'owner']))
            ->assertOk()
            ->assertDontSee('id="pengaturan"', false)
            ->assertDontSee('Anda melihat panduan sebagai');
    }

    public function test_panduan_konveksi_bisa_dicetak_admin(): void
    {
        $this->actingAs($this->staf('admin@setiasport.test'))->get(route('panel.bantuan.konveksi'))
            ->assertOk()
            ->assertSee('Panduan Kerja Konveksi')
            ->assertSee('Press / Sublime')
            ->assertSee('PIN Divisi');
    }

    public function test_panduan_konveksi_ditolak_tanpa_izin_produksi(): void
    {
        $this->actingAs($this->tanpaPeran())->get(route('panel.bantuan.konveksi'))->assertForbidden();
    }

    /** Salah ketik nama izin membuat satu bagian hilang dari semua orang kecuali pemilik. */
    public function test_setiap_izin_di_panduan_benar_benar_ada(): void
    {
        $dikenal = Permission::pluck('slug');

        foreach (IsiPanduan::semua() as $bagian) {
            foreach ($bagian['izin'] ?? [] as $izin) {
                $this->assertTrue($dikenal->contains($izin), "Izin {$izin} di bagian {$bagian['kunci']} tidak ada.");
            }
        }
    }
}
