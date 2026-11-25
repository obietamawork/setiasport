<?php

namespace Tests\Feature;

use App\Models\PercakapanCs;
use App\Models\Setting;
use App\Models\User;
use App\Services\LayananCs;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Pelanggan bisa memulai percakapan CS baru (KEP-163): tombol "Mulai baru",
 * percakapan yang sudah lama ditutup tidak dimuat lagi, dan CS tetap melihat
 * percakapan sebelumnya dari pelanggan yang sama.
 */
class CsMulaiBaruTest extends TestCase
{
    use RefreshDatabase;

    private const COOKIE = 'cs_percakapan';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);

        // CS mati sebagai bawaan (KEP-034); yang diuji jalur CS manusia, bukan asisten.
        config(['fitur.cs' => true]);
        Setting::simpan('cs_aktif', '1');
        Setting::simpan('bot_aktif', '0');

        // Widget mengirim cookie (credentials: 'same-origin'); permintaan JSON di tes tidak, tanpa ini.
        $this->withCredentials();
    }

    /** @return array{0: PercakapanCs, 1: string} */
    private function mulai(string $kontak = 'warno@contoh.test', string $topik = 'KUSTOM'): array
    {
        $res = $this->postJson(route('cs.mulai'), [
            'nama' => 'Pak Warno', 'kontak' => $kontak, 'topik' => $topik, 'isi' => 'Jersey futsal 15 pcs berapa?',
        ])->assertCreated();

        $cookie = collect($res->headers->getCookies())->first(fn ($c) => $c->getName() === self::COOKIE);

        return [PercakapanCs::latest('id')->firstOrFail(), CookieValuePrefix::remove(app('encrypter')->decrypt($cookie->getValue(), false))];
    }

    private function admin(): User
    {
        return User::where('email', 'admin@setiasport.test')->firstOrFail();
    }

    private function cookieDilupakan(TestResponse $res): bool
    {
        $cookie = collect($res->headers->getCookies())->first(fn ($c) => $c->getName() === self::COOKIE);

        return $cookie !== null && $cookie->getExpiresTime() < time();
    }

    public function test_mulai_baru_menutup_percakapan_lama_dengan_catatan_dan_melupakan_cookie(): void
    {
        [$lama, $token] = $this->mulai();

        $res = $this->withCookie(self::COOKIE, $token)->postJson(route('cs.akhiri'))->assertOk();

        $this->assertTrue($this->cookieDilupakan($res));
        $lama->refresh();
        $this->assertSame('SELESAI', $lama->status);
        $this->assertNotNull($lama->ditutup_pada);
        $this->assertSame('Pelanggan memulai percakapan baru. Percakapan ini ditutup.', $lama->pesan()->latest('id')->value('isi'));

        [$baru] = $this->mulai(topik: 'KOMPLAIN');
        $this->assertNotSame($lama->id, $baru->id);
    }

    public function test_mulai_baru_setelah_ditutup_cs_tidak_menambah_catatan_ganda(): void
    {
        [$percakapan, $token] = $this->mulai();
        app(LayananCs::class)->tutup($percakapan, $this->admin());

        $this->withCookie(self::COOKIE, $token)->postJson(route('cs.akhiri'))->assertOk();

        $this->assertSame(1, $percakapan->pesan()->where('pengirim', 'SISTEM')->count());
    }

    public function test_percakapan_yang_ditutup_lebih_dari_tujuh_hari_tidak_dimuat_lagi(): void
    {
        [$percakapan, $token] = $this->mulai();
        app(LayananCs::class)->tutup($percakapan, $this->admin());

        $this->travel(6)->days();
        $this->withCookie(self::COOKIE, $token)->getJson(route('cs.status'))
            ->assertJsonPath('percakapan.status', 'SELESAI');

        $this->travel(2)->days();
        $res = $this->withCookie(self::COOKIE, $token)->getJson(route('cs.status'))
            ->assertJsonPath('percakapan', null)
            ->assertJsonPath('pesan', []);
        $this->assertTrue($this->cookieDilupakan($res));
    }

    public function test_menulis_lagi_membuka_kembali_dan_menghapus_waktu_tutup(): void
    {
        [$percakapan, $token] = $this->mulai();
        app(LayananCs::class)->tutup($percakapan, $this->admin());

        $this->withCookie(self::COOKIE, $token)->postJson(route('cs.kirim'), ['isi' => 'Masih ada pertanyaan'])->assertCreated();

        $percakapan->refresh();
        $this->assertSame('TERBUKA', $percakapan->status);
        $this->assertNull($percakapan->ditutup_pada);
    }

    public function test_status_widget_mengembalikan_kontak_untuk_mengisi_ulang_formulir(): void
    {
        [, $token] = $this->mulai();

        $this->withCookie(self::COOKIE, $token)->getJson(route('cs.status'))
            ->assertJsonPath('percakapan.kontak', 'warno@contoh.test');
    }

    public function test_panel_menampilkan_percakapan_sebelumnya_dari_kontak_yang_sama(): void
    {
        [$pertama] = $this->mulai('warno@contoh.test', 'KUSTOM');
        [$lain] = $this->mulai('siti@contoh.test', 'STOK');
        [$kedua] = $this->mulai('Warno@Contoh.test ', 'KOMPLAIN');

        $sebelumnya = $this->actingAs($this->admin())->getJson(route('panel.cs.detail', $kedua))
            ->assertOk()
            ->json('percakapan.percakapan_sebelumnya');

        $this->assertSame([$pertama->id], array_column($sebelumnya, 'id'));
        $this->assertNotContains($lain->id, array_column($sebelumnya, 'id'));
    }
}
