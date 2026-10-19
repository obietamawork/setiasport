<?php

namespace Tests\Feature;

use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Opname stok (KEP-075): dikerjakan sambil berdiri di depan rak, satu formulir
 * untuk banyak varian sekaligus.
 *
 * Yang dijaga: angka yang sudah diketik untuk SELURUH rak tidak boleh hilang
 * hanya karena kolom "alasan" ditolak validasi -- itu berarti opname harus
 * diulang dari rak pertama.
 */
class StokTest extends TestCase
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

    public function test_penyesuaian_valid_tersimpan_dan_tercatat(): void
    {
        $varian = ProductVariant::where('sku', 'BOLA-MITRE-01')->firstOrFail();

        $this->actingAs($this->owner())
            ->put(route('panel.stok.update'), [
                'stok' => [$varian->id => 20],
                'alasan' => 'Opname 7 September',
            ])
            ->assertSessionHas('sukses');

        $this->assertSame(20, $varian->fresh()->stok);
        $this->assertDatabaseHas('activity_logs', ['aksi' => 'stok.disesuaikan']);
    }

    /**
     * Dulu input stok dan alasan dibaca murni dari database/kosong, tidak
     * pernah dari old(): saat "alasan" ditolak karena sebab LAIN selain
     * kosong (di sini kepanjangan, melebihi max:120), angka yang sudah
     * diketik untuk banyak varian hilang diam-diam dan halaman tampil
     * seolah belum ada yang diubah.
     */
    public function test_angka_yang_sudah_diketik_tidak_hilang_saat_alasan_ditolak(): void
    {
        $bola = ProductVariant::where('sku', 'BOLA-MITRE-01')->firstOrFail();

        $alasanTerlaluPanjang = str_repeat('x', 130);

        $response = $this->actingAs($this->owner())
            ->from(route('panel.stok.index'))
            ->followingRedirects()
            ->put(route('panel.stok.update'), [
                'stok' => [$bola->id => 20],
                'alasan' => $alasanTerlaluPanjang,
            ]);

        $response->assertOk();

        // Database tidak boleh tersentuh sama sekali: validasi gagal sebelum disimpan.
        $this->assertSame(12, $bola->fresh()->stok);

        // Angka yang sudah diketik harus tampil lagi di kotaknya, bukan kembali ke 12.
        $response->assertSee('value="20"', false);
        $this->assertStringContainsString('name="stok['.$bola->id.']"', $response->getContent());
    }
}
