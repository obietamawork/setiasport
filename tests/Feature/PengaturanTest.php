<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Halaman pengaturan yang ditata per tab. */
class PengaturanTest extends TestCase
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

    public function test_pengaturan_ditata_dalam_tab(): void
    {
        $this->actingAs($this->owner())->get(route('panel.pengaturan.index'))
            ->assertOk()
            ->assertSeeInOrder(['Toko &amp; kontak', 'Pembayaran', 'Pesanan', 'Halaman depan', 'Chat CS'], false)
            ->assertSee('Rekening 2');
    }

    /** Dulu tampil sebagai kotak teks berisi 0 atau 1. */
    public function test_isian_ya_tidak_tampil_sebagai_saklar(): void
    {
        $this->actingAs($this->owner())->get(route('panel.pengaturan.index'))
            ->assertSee('type="checkbox" id="s-cs_aktif"', false);
    }

    public function test_saklar_bisa_dinyalakan_dan_dimatikan(): void
    {
        $this->actingAs($this->owner())->put(route('panel.pengaturan.update'), ['setting' => ['cs_aktif' => '1']]);
        $this->assertTrue(cs_aktif());

        $this->actingAs($this->owner())->put(route('panel.pengaturan.update'), ['setting' => ['cs_aktif' => '0']]);
        $this->assertFalse(cs_aktif());
    }

    /**
     * Dulu setiap isian divalidasi sambil disimpan: kesalahan di isian kesepuluh
     * meninggalkan sembilan isian sebelumnya telanjur tersimpan, sementara layar
     * mengatakan penyimpanan gagal.
     */
    public function test_satu_isian_salah_membatalkan_seluruh_penyimpanan(): void
    {
        $this->actingAs($this->owner())
            ->put(route('panel.pengaturan.update'), ['setting' => [
                'bank_rekening' => '999 999 999',
                'dp_minimum_persen' => 'lima puluh',
            ]])
            ->assertSessionHasErrors('setting.dp_minimum_persen');

        $this->assertSame('138 000 635 8613', Setting::ambil('bank_rekening'));
    }

    public function test_kesalahan_membuka_tab_tempatnya_berada(): void
    {
        $this->actingAs($this->owner())
            ->from(route('panel.pengaturan.index', ['tab' => 'toko']))
            ->followingRedirects()
            ->put(route('panel.pengaturan.update'), ['setting' => ['batas_bayar_jam' => '-5']])
            ->assertSee("tab: 'pembayaran'", false);
    }

    /** Pengaturan baru yang lupa ditata tidak boleh hilang dari layar. */
    public function test_pengaturan_yang_belum_ditata_masuk_tab_lainnya(): void
    {
        Setting::create(['key' => 'uji_baru', 'value' => 'x', 'tipe' => 'string', 'grup' => 'umum', 'label' => 'Pengaturan uji baru']);

        $this->actingAs($this->owner())->get(route('panel.pengaturan.index'))
            ->assertSee('Lainnya')
            ->assertSee('Pengaturan uji baru');
    }
}
