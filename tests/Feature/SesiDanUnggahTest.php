<?php

namespace Tests\Feature;

use App\Models\Setting;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Halaman yang lama terbuka dan berkas yang terlalu besar (KEP-159).
 *
 * Token CSRF di halaman basi setelah sesi kedaluwarsa dan "ingat saya"
 * memasukkan staf kembali dengan sesi baru; setiap kiriman dari halaman itu
 * dulu berakhir di layar "Page Expired". Kiriman di atas batas server ditolak
 * server sebelum aplikasi sempat menjelaskan apa pun.
 */
class SesiDanUnggahTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class]);
    }

    public function test_token_sesi_mengembalikan_token_yang_berlaku_tanpa_disimpan_cache(): void
    {
        $respons = $this->getJson(route('token-sesi'))->assertOk();

        $this->assertSame(session()->token(), $respons->json('token'));
        $this->assertStringContainsString('no-store', (string) $respons->headers->get('Cache-Control'));
    }

    public function test_kiriman_dengan_token_basi_kembali_ke_halaman_asal_dengan_pesan(): void
    {
        Route::post('/uji-token-basi', fn () => throw new TokenMismatchException('CSRF token mismatch.'))->middleware('web');

        $this->from('/panel/banner/buat')->post('/uji-token-basi', ['judul' => 'Promo lebaran'])
            ->assertRedirect('/panel/banner/buat')
            ->assertSessionHasErrors(['sesi' => 'Halaman ini sudah terlalu lama terbuka, jadi kami muat ulang. Silakan ulangi tindakan tadi.'])
            ->assertSessionHasInput('judul', 'Promo lebaran');
    }

    public function test_permintaan_fetch_dengan_token_basi_tetap_dijawab_419(): void
    {
        Route::post('/uji-token-basi', fn () => throw new TokenMismatchException('CSRF token mismatch.'))->middleware('web');

        $this->postJson('/uji-token-basi')->assertStatus(419);
    }

    public function test_batas_unggah_mengikuti_pengaturan_bila_di_bawah_batas_server(): void
    {
        Setting::simpan('upload_maks_mb', 1);

        $this->assertSame(1, batas_unggah_mb());
    }

    public function test_batas_unggah_tidak_melebihi_batas_kiriman_server(): void
    {
        Setting::simpan('upload_maks_mb', 100_000);

        // Satu megabita disisakan untuk isian lain dalam kiriman yang sama.
        $kirimanMb = (int) floor($this->keByte((string) ini_get('post_max_size')) / 1_048_576);
        $berkasMb = (int) floor($this->keByte((string) ini_get('upload_max_filesize')) / 1_048_576);
        $this->assertSame(max(1, min($kirimanMb - 1, $berkasMb)), batas_unggah_mb());
    }

    private function keByte(string $nilai): int
    {
        $angka = (int) $nilai;

        return match (strtoupper(substr(trim($nilai), -1))) {
            'G' => $angka * 1_073_741_824,
            'M' => $angka * 1_048_576,
            'K' => $angka * 1024,
            default => $angka,
        };
    }
}
