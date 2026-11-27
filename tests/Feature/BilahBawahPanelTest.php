<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Bilah bawah panel di layar sentuh mengikuti peran (KEP-175, papan Peta
 * halaman Kit Tim): pemilik dan admin, kasir, dan bagian produksi masing-masing
 * mendapat tujuan kerjanya di depan.
 */
class BilahBawahPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class]);
        config(['fitur.cs' => true]);
    }

    public function test_pemilik_mendapat_dashboard_pesanan_customer_service_dan_serah_terima(): void
    {
        $pemilik = User::where('email', 'owner@setiasport.test')->firstOrFail();

        $respons = $this->actingAs($pemilik)->get(route('panel.dashboard'));

        $respons->assertOk();
        $this->assertSame([
            route('panel.dashboard'),
            route('panel.pesanan.index'),
            route('panel.cs.index'),
            route('panel.serah-terima'),
        ], $this->tautanBilahBawah($respons));
    }

    /**
     * @return array<string, array{list<string>, list<string>}>
     */
    public static function peranKhusus(): array
    {
        return [
            'kasir mendahulukan serah terima' => [
                ['order.lihat', 'pembayaran.catat', 'serah_terima.proses'],
                ['panel.serah-terima', 'panel.dashboard', 'panel.pesanan.index'],
            ],
            'bagian produksi mendahulukan antrian kerja' => [
                ['produksi.jahit'],
                ['panel.antrian.index', 'panel.dashboard'],
            ],
        ];
    }

    /**
     * @param  list<string>  $izin
     * @param  list<string>  $ruteDiharapkan
     */
    #[DataProvider('peranKhusus')]
    public function test_peran_khusus_mendapat_tujuan_kerjanya_di_depan(array $izin, array $ruteDiharapkan): void
    {
        $peran = Role::create(['nama' => 'Peran uji', 'slug' => 'peran-uji']);
        $peran->permissions()->sync(Permission::whereIn('slug', $izin)->pluck('id'));
        $staf = User::create(['name' => 'Staf uji', 'email' => 'staf@setiasport.test', 'password' => bcrypt('rahasia-panjang')]);
        $staf->roles()->attach($peran);

        $respons = $this->actingAs($staf)->get(route('panel.dashboard'));

        $respons->assertOk();
        $this->assertSame(array_map(fn (string $rute) => route($rute), $ruteDiharapkan), $this->tautanBilahBawah($respons));
    }

    /** @return list<string> Tujuan di bilah bawah, berurutan dari kiri. */
    private function tautanBilahBawah(TestResponse $respons): array
    {
        preg_match('#<nav class="nav-bawah"[^>]*>(.*?)</nav>#s', $respons->getContent(), $bilah);
        preg_match_all('#<a href="([^"]+)"#', $bilah[1] ?? '', $tautan);

        return $tautan[1];
    }
}
