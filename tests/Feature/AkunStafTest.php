<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Akun saya dan pengelolaan akun staf (KEP-084).
 *
 * Yang dijaga: kata sandi bawaan bisa diganti dari panel, sandi sementara
 * dari Pemilik wajib diganti sendiri, akun nonaktif langsung terputus, dan
 * toko tidak pernah kehilangan Pemilik aktif terakhirnya.
 */
class AkunStafTest extends TestCase
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

    private function admin(): User
    {
        return User::where('email', 'admin@setiasport.test')->firstOrFail();
    }

    public function test_staf_mengganti_kata_sandi_bawaannya_sendiri(): void
    {
        $owner = $this->owner();
        $this->assertTrue($owner->masihSandiBawaan());

        $this->actingAs($owner)
            ->put(route('panel.akun.sandi'), [
                'password_sekarang' => 'password',
                'password' => 'TokoSetia2026',
                'password_confirmation' => 'TokoSetia2026',
            ])
            ->assertRedirect(route('panel.akun'))
            ->assertSessionHasNoErrors();

        $this->assertFalse($owner->fresh()->masihSandiBawaan());
        $this->assertTrue(Hash::check('TokoSetia2026', $owner->fresh()->password));
    }

    public function test_kata_sandi_sekarang_yang_salah_ditolak(): void
    {
        $this->actingAs($this->owner())
            ->put(route('panel.akun.sandi'), [
                'password_sekarang' => 'tebakan',
                'password' => 'TokoSetia2026',
                'password_confirmation' => 'TokoSetia2026',
            ])
            ->assertSessionHasErrorsIn('sandi', ['password_sekarang']);

        $this->assertTrue($this->owner()->masihSandiBawaan());
    }

    public function test_kata_sandi_lemah_ditolak(): void
    {
        foreach (['pendek1', 'hanyahurufsaja', '1234567890'] as $lemah) {
            $this->actingAs($this->owner())
                ->put(route('panel.akun.sandi'), [
                    'password_sekarang' => 'password',
                    'password' => $lemah,
                    'password_confirmation' => $lemah,
                ])
                ->assertSessionHasErrorsIn('sandi', ['password']);
        }
    }

    /** Siapa pun yang mungkin tahu sandi lama terputus dari perangkatnya. */
    public function test_mengganti_sandi_mengeluarkan_sesi_di_perangkat_lain(): void
    {
        $owner = $this->owner();
        DB::table('sessions')->insert([
            'id' => 'sesi-hp-lain', 'user_id' => $owner->id, 'ip_address' => '10.0.0.9',
            'user_agent' => 'HP lain', 'payload' => '', 'last_activity' => time(),
        ]);

        $this->actingAs($owner)->put(route('panel.akun.sandi'), [
            'password_sekarang' => 'password',
            'password' => 'TokoSetia2026',
            'password_confirmation' => 'TokoSetia2026',
        ]);

        $this->assertDatabaseMissing('sessions', ['id' => 'sesi-hp-lain']);
    }

    public function test_mengganti_email_butuh_kata_sandi_sekarang(): void
    {
        $this->actingAs($this->owner())
            ->put(route('panel.akun.profil'), ['name' => 'Pemilik', 'email' => 'baru@setiasport.id'])
            ->assertSessionHasErrors('password_sekarang');

        $this->actingAs($this->owner())
            ->put(route('panel.akun.profil'), [
                'name' => 'Pemilik', 'email' => 'baru@setiasport.id', 'password_sekarang' => 'password',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'baru@setiasport.id']);
    }

    /** Akun baru memakai sandi sementara yang ikut diketahui Pemilik. */
    public function test_akun_baru_wajib_mengganti_sandi_sementaranya(): void
    {
        $this->actingAs($this->owner())
            ->post(route('panel.pengguna.simpan'), [
                'name' => 'Rina Kasir', 'email' => 'rina@setiasport.id',
                'peran' => 'admin', 'password' => 'Sementara123',
            ])
            ->assertSessionHas('sukses');

        $rina = User::where('email', 'rina@setiasport.id')->firstOrFail();
        $this->assertTrue($rina->wajib_ganti_sandi);
        $this->assertTrue($rina->punyaPeran('admin'));

        // Selama belum diganti, halaman panel mana pun diarahkan ke Akun saya.
        $this->actingAs($rina)->get(route('panel.dashboard'))->assertRedirect(route('panel.akun'));
        $this->actingAs($rina)->get(route('panel.akun'))->assertOk();

        $this->actingAs($rina)->put(route('panel.akun.sandi'), [
            'password_sekarang' => 'Sementara123',
            'password' => 'RinaPunya2026',
            'password_confirmation' => 'RinaPunya2026',
        ]);

        $this->assertFalse($rina->fresh()->wajib_ganti_sandi);
        $this->actingAs($rina->fresh())->get(route('panel.dashboard'))->assertOk();
    }

    /** Dulu status aktif hanya dicek di formulir masuk. */
    public function test_akun_yang_dinonaktifkan_langsung_terputus(): void
    {
        $admin = $this->admin();
        DB::table('sessions')->insert([
            'id' => 'sesi-admin', 'user_id' => $admin->id, 'ip_address' => '10.0.0.5',
            'user_agent' => 'Kasir', 'payload' => '', 'last_activity' => time(),
        ]);

        $this->actingAs($this->owner())
            ->post(route('panel.pengguna.aktif', $admin))
            ->assertSessionHas('sukses');

        $this->assertFalse($admin->fresh()->aktif);
        $this->assertDatabaseMissing('sessions', ['id' => 'sesi-admin']);

        $this->actingAs($admin->fresh())
            ->get(route('panel.dashboard'))
            ->assertRedirect(route('panel.login'));
        $this->assertGuest('web');
    }

    public function test_pemilik_tidak_bisa_menonaktifkan_dirinya_sendiri(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)
            ->post(route('panel.pengguna.aktif', $owner))
            ->assertSessionHasErrors('pengguna');

        $this->assertTrue($owner->fresh()->aktif);
    }

    /** Tanpa Pemilik aktif, tidak ada yang bisa membuka Pengaturan dan halaman ini. */
    public function test_pemilik_aktif_terakhir_tidak_bisa_diturunkan(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)
            ->put(route('panel.pengguna.update', $owner), [
                'name' => $owner->name, 'email' => $owner->email, 'peran' => 'admin',
            ])
            ->assertSessionHasErrors('pengguna');

        $this->assertTrue($owner->fresh()->punyaPeran('owner'));

        // Begitu ada Pemilik lain, penurunan boleh.
        $admin = $this->admin();
        $admin->roles()->sync(Role::where('slug', 'owner')->pluck('id'));

        $this->actingAs($owner)
            ->put(route('panel.pengguna.update', $owner), [
                'name' => $owner->name, 'email' => $owner->email, 'peran' => 'admin',
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse($owner->fresh()->punyaPeran('owner'));
    }

    public function test_atur_ulang_sandi_mewajibkan_ganti_dan_memutus_sesi(): void
    {
        $admin = $this->admin();
        DB::table('sessions')->insert([
            'id' => 'sesi-admin', 'user_id' => $admin->id, 'ip_address' => '10.0.0.5',
            'user_agent' => 'Kasir', 'payload' => '', 'last_activity' => time(),
        ]);

        $this->actingAs($this->owner())
            ->post(route('panel.pengguna.sandi', $admin), ['password' => 'AturUlang2026'])
            ->assertSessionHas('sukses');

        $admin->refresh();
        $this->assertTrue($admin->wajib_ganti_sandi);
        $this->assertTrue(Hash::check('AturUlang2026', $admin->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'sesi-admin']);
    }

    public function test_admin_tidak_bisa_membuka_halaman_pengguna(): void
    {
        $this->actingAs($this->admin())->get(route('panel.pengguna.index'))->assertForbidden();
    }

    /** Diperiksa dari hash sandinya, bukan dari email akun contoh. */
    public function test_kesiapan_menandai_akun_yang_masih_bersandi_bawaan(): void
    {
        $butir = fn () => collect(app(\App\Services\KesiapanTayang::class)->bagian())
            ->flatMap(fn ($b) => $b['isi'])->keyBy('judul')['Kata sandi bawaan sudah diganti'];

        $this->assertFalse($butir()['siap']);
        $this->assertSame('2 akun masih memakainya', $butir()['keadaan']);

        $this->owner()->forceFill(['password' => 'TokoSetia2026'])->save();
        $this->admin()->forceFill(['password' => 'KasirToko2026'])->save();

        $this->assertTrue($butir()['siap']);
    }
}
