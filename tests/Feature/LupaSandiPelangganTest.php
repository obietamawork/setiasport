<?php

namespace Tests\Feature;

use App\Mail\AturUlangSandi;
use App\Models\Customer;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Lupa kata sandi pelanggan (KEP-157): tautan atur ulang lewat email, berlaku
 * 60 menit, dan jawaban halaman selalu sama supaya tidak bisa dipakai menebak
 * email yang terdaftar.
 */
class LupaSandiPelangganTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed(SettingSeeder::class);
    }

    private function pelanggan(array $tambahan = []): Customer
    {
        $c = Customer::create(['nama' => 'Budi', 'email' => 'budi@contoh.test', 'no_hp' => '081234567890']);
        $c->forceFill(['password' => 'sandi-lama-123', 'email_terverifikasi_pada' => now(), ...$tambahan])->save();

        return $c;
    }

    private function mintaTautan(string $email = 'budi@contoh.test'): ?string
    {
        $this->post(route('lupa-sandi.kirim'), ['email' => $email])
            ->assertRedirect(route('lupa-sandi'))
            ->assertSessionHas('terkirim');

        $tautan = null;
        Mail::assertQueued(AturUlangSandi::class, function (AturUlangSandi $m) use (&$tautan, $email) {
            $tautan = $m->tautan;

            return $m->hasTo($email);
        });

        return $tautan;
    }

    public function test_halaman_masuk_menautkan_lupa_sandi(): void
    {
        $this->get(route('masuk'))->assertOk()->assertSee(route('lupa-sandi'), false);
    }

    public function test_sandi_baru_disimpan_lewat_tautan_dan_pelanggan_langsung_masuk(): void
    {
        $c = $this->pelanggan();
        $ingatLama = $c->remember_token;
        $tautan = $this->mintaTautan();

        $this->get($tautan)->assertOk()->assertSee('Kata sandi baru');
        $this->post($tautan, ['password' => 'sandi-baru-456', 'password_confirmation' => 'sandi-baru-456'])
            ->assertRedirect(route('pesanan-saya'));

        $c->refresh();
        $this->assertAuthenticatedAs($c, 'pelanggan');
        $this->assertTrue(Hash::check('sandi-baru-456', $c->password));
        $this->assertNull($c->token_atur_sandi);
        $this->assertNotSame($ingatLama, $c->remember_token);

        // Tautan yang sama tidak bisa dipakai dua kali.
        auth('pelanggan')->logout();
        $this->get($tautan)->assertRedirect(route('lupa-sandi'));
    }

    public function test_email_tidak_dikenal_mendapat_jawaban_yang_sama_tanpa_email(): void
    {
        $this->post(route('lupa-sandi.kirim'), ['email' => 'siapa@contoh.test'])
            ->assertRedirect(route('lupa-sandi'))
            ->assertSessionHas('terkirim');

        Mail::assertNothingQueued();
    }

    public function test_akun_nonaktif_tidak_dikirimi_tautan(): void
    {
        $this->pelanggan(['aktif' => false]);

        $this->post(route('lupa-sandi.kirim'), ['email' => 'budi@contoh.test'])->assertSessionHas('terkirim');

        Mail::assertNothingQueued();
    }

    public function test_tautan_kedaluwarsa_setelah_satu_jam(): void
    {
        $this->pelanggan();
        $tautan = $this->mintaTautan();

        $this->travel(61)->minutes();

        $this->get($tautan)->assertRedirect(route('lupa-sandi'))->assertSessionHasErrors();
    }

    public function test_pemesan_tamu_bisa_membuat_sandi_lewat_tautan(): void
    {
        $c = Customer::create(['nama' => 'Tamu', 'email' => 'budi@contoh.test', 'no_hp' => '081234567890']);
        $tautan = $this->mintaTautan();

        $this->post($tautan, ['password' => 'sandi-baru-456', 'password_confirmation' => 'sandi-baru-456']);

        $this->assertTrue($c->fresh()->akunAktif());
    }

    public function test_permintaan_dibatasi_per_email(): void
    {
        $this->pelanggan();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('lupa-sandi.kirim'), ['email' => 'budi@contoh.test']);
        }

        Mail::assertQueuedCount(3);
    }
}
