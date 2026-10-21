<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pembatasan percobaan (KEP-083).
 *
 * Yang dijaga: login staf tidak bisa ditebak berulang-ulang, dan pembatasan
 * di rute mana pun berakhir di halaman berbahasa Indonesia, bukan halaman
 * bawaan Laravel.
 */
class BatasPercobaanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class]);
    }

    /**
     * Akun staf membuka seluruh data pelanggan, pembayaran, dan pengaturan,
     * dan akun bawaannya memakai kata sandi yang tertulis di dokumentasi.
     */
    public function test_login_staf_dikunci_setelah_lima_kali_salah(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('panel.masuk'), ['email' => 'owner@setiasport.test', 'password' => 'tebakan-'.$i])
                ->assertSessionHasErrors('email');
        }

        // Kata sandi yang BENAR pun ditolak selama terkunci: kalau tidak, penebak
        // tinggal terus mencoba sampai salah satunya cocok.
        $this->post(route('panel.masuk'), ['email' => 'owner@setiasport.test', 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'Terlalu banyak percobaan masuk. Coba lagi dalam 5 menit.']);

        $this->assertGuest('web');
    }

    /** Salah ketik sekali dua kali tidak menghalangi yang memang pemilik akun. */
    public function test_login_benar_setelah_salah_ketik_tetap_masuk(): void
    {
        $this->post(route('panel.masuk'), ['email' => 'owner@setiasport.test', 'password' => 'salah']);

        $this->post(route('panel.masuk'), ['email' => 'owner@setiasport.test', 'password' => 'password'])
            ->assertRedirect(route('panel.dashboard'));

        $this->assertAuthenticatedAs(User::where('email', 'owner@setiasport.test')->first(), 'web');
    }

    /** Dulu halaman 429 jatuh ke bawaan Laravel berbahasa Inggris. */
    public function test_pembatasan_rute_menampilkan_halaman_indonesia(): void
    {
        // Tombol "tandai selesai" di halaman QR dibatasi 10 kali per menit.
        for ($i = 0; $i < 10; $i++) {
            $this->post(route('kerja.selesai', 'token-karangan'), ['pin' => '1234']);
        }

        $this->post(route('kerja.selesai', 'token-karangan'), ['pin' => '1234'])
            ->assertStatus(429)
            ->assertSee('Terlalu banyak percobaan')
            ->assertDontSee('Too Many Requests');
    }
}
