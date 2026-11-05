<?php

namespace Tests\Feature;

use App\Models\Setting;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Batas jumlah kueri halaman publik (KEP-108). Di hosting, cache memakai
 * database, jadi setiap pembacaan cache juga satu kueri; halaman depan pernah
 * memakai 78 kueri, 59 di antaranya membaca pengaturan satu per satu.
 */
class AnggaranKueriTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);
    }

    public function test_pengaturan_dibaca_sekali_per_permintaan(): void
    {
        setting('dp_minimum_persen');

        DB::enableQueryLog();
        foreach (range(1, 20) as $_) {
            setting('dp_minimum_persen');
            setting('kunci_yang_tidak_pernah_ada', 'bawaan');
        }
        $this->assertCount(0, DB::getQueryLog(), 'Pengaturan yang sudah dimuat tidak boleh dibaca ulang.');

        // Kunci yang tidak ada memberi nilai bawaan, bukan galat.
        $this->assertSame('bawaan', setting('kunci_yang_tidak_pernah_ada', 'bawaan'));
    }

    public function test_perubahan_pengaturan_langsung_terbaca(): void
    {
        $this->assertSame(50, setting('dp_minimum_persen'));

        Setting::simpan('dp_minimum_persen', 70);
        $this->assertSame(70, setting('dp_minimum_persen'));

        // Cache bersama, yang dibaca permintaan dan proses lain, ikut berisi nilai baru.
        $this->assertSame('70', Cache::get(Setting::CACHE)['dp_minimum_persen'][0]);

        Setting::where('key', 'dp_minimum_persen')->first()->delete();
        $this->assertSame(30, setting('dp_minimum_persen', 30));
    }

    /** Batasnya longgar; yang dijaga adalah kembalinya kueri per kategori atau per pengaturan. */
    public function test_halaman_publik_dalam_anggaran_kueri(): void
    {
        foreach (['/' => 20, '/katalog' => 15, '/produk/jersey-polos' => 15, '/pesan/custom-setelan-jersey' => 15] as $alamat => $batas) {
            $this->get($alamat)->assertOk();

            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get($alamat)->assertOk();
            $jumlah = count(DB::getQueryLog());
            DB::disableQueryLog();

            $this->assertLessThanOrEqual($batas, $jumlah, "$alamat memakai $jumlah kueri.");
        }
    }
}
