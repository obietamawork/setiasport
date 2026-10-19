<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Riwayat aktivitas (KEP-079).
 *
 * Yang dijaga: `ActivityLog` yang sudah dicatat di seluruh panel benar-benar
 * bisa dibaca lagi lewat halaman ini, bisa disaring per jenis aktivitas dan
 * per pengguna, dan tetap tertutup untuk peran yang tidak berwenang melihat
 * Pengaturan.
 */
class AktivitasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class]);
    }

    private function owner(): User
    {
        return User::where('email', 'owner@setiasport.test')->firstOrFail();
    }

    public function test_aktivitas_yang_tercatat_tampil_di_halaman(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);

        ActivityLog::catat('stok.disesuaikan', null, [
            'alasan' => 'Opname 7 September',
            'perubahan' => [['sku' => 'BOLA-MITRE-01', 'dari' => 12, 'ke' => 10]],
        ]);

        $this->get(route('panel.aktivitas'))
            ->assertOk()
            ->assertSee('stok.disesuaikan')
            ->assertSee('Opname 7 September')
            ->assertSee($owner->name);
    }

    public function test_pencarian_menyaring_menurut_jenis_aktivitas(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);

        ActivityLog::catat('stok.disesuaikan', null, ['alasan' => 'x']);
        ActivityLog::catat('pengaturan.diubah', null, ['toko_nama' => ['dari' => 'A', 'ke' => 'B']]);

        $response = $this->get(route('panel.aktivitas', ['q' => 'stok']));

        $response->assertSee('stok.disesuaikan')->assertDontSee('pengaturan.diubah');
    }

    public function test_tanpa_izin_pengaturan_tidak_bisa_membuka_riwayat(): void
    {
        $pekerja = User::create([
            'name' => 'Operator Jahit',
            'email' => 'jahit.uji@setiasport.test',
            'password' => bcrypt('rahasia-panjang'),
        ]);

        $this->actingAs($pekerja)->get(route('panel.aktivitas'))->assertForbidden();
    }
}
