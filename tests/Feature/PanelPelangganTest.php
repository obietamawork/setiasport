<?php

namespace Tests\Feature;

use App\Mail\AturUlangSandi;
use App\Models\ActivityLog;
use App\Models\AlamatPelanggan;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Menu Pelanggan di panel (KEP-157): Pemilik dan Admin mencari pelanggan,
 * melihat riwayat dan alamatnya, mengubah kontak, mengirim tautan atur ulang
 * kata sandi, dan menonaktifkan akun. Staf tidak pernah melihat atau mengetik
 * kata sandi pelanggan.
 */
class PanelPelangganTest extends TestCase
{
    use RefreshDatabase;

    private Customer $pelanggan;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class]);
        $this->pelanggan = Customer::create(['nama' => 'Budi Santoso', 'email' => 'budi@contoh.test', 'no_hp' => '081234567890']);
        $this->pelanggan->forceFill(['password' => 'sandi-lama-123', 'email_terverifikasi_pada' => now()])->save();
    }

    private function staf(string $email = 'admin@setiasport.test'): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    public function test_pemilik_dan_admin_melihat_menu_dan_daftar_pelanggan(): void
    {
        foreach (['owner@setiasport.test', 'admin@setiasport.test'] as $email) {
            $this->actingAs($this->staf($email))->get(route('panel.pelanggan.index'))
                ->assertOk()->assertSee('Budi Santoso')->assertSee(route('panel.pelanggan.index'), false);
        }
    }

    public function test_staf_tanpa_izin_ditolak(): void
    {
        $tanpaPeran = User::create(['name' => 'Kasir', 'email' => 'kasir@contoh.test', 'password' => 'rahasia-123', 'aktif' => true]);

        $this->actingAs($tanpaPeran)->get(route('panel.pelanggan.index'))->assertForbidden();
    }

    public function test_cari_lewat_nama_email_atau_nomor_hp(): void
    {
        Customer::create(['nama' => 'Siti', 'email' => 'siti@contoh.test', 'no_hp' => '085700000000']);

        foreach (['Santoso', 'budi@contoh', '0812345'] as $kata) {
            $this->actingAs($this->staf())->get(route('panel.pelanggan.index', ['q' => $kata]))
                ->assertSee('Budi Santoso')->assertDontSee('Siti');
        }
    }

    public function test_detail_menampilkan_alamat_dan_status_akun(): void
    {
        AlamatPelanggan::create(['customer_id' => $this->pelanggan->id, 'label' => 'Rumah', 'alamat' => 'Jl. Mawar No. 5', 'utama' => true]);

        $this->actingAs($this->staf())->get(route('panel.pelanggan.show', $this->pelanggan))
            ->assertOk()->assertSee('Jl. Mawar No. 5')->assertSee('Akun aktif');
    }

    public function test_ubah_kontak_dan_catatan_tercatat_di_aktivitas(): void
    {
        $this->actingAs($this->staf())->put(route('panel.pelanggan.ubah', $this->pelanggan), [
            'nama' => 'Budi S.', 'email' => 'budi.baru@contoh.test', 'no_hp' => '085611112222', 'catatan' => 'Pelatih SSB, pesan tiap semester.',
        ])->assertRedirect();

        $c = $this->pelanggan->fresh();
        $this->assertSame('budi.baru@contoh.test', $c->email);
        $this->assertSame('Pelatih SSB, pesan tiap semester.', $c->catatan);
        $this->assertTrue(ActivityLog::where('aksi', 'pelanggan.diubah')->exists());
    }

    public function test_ganti_email_mengeluarkan_sesi_pelanggan_yang_sedang_masuk(): void
    {
        $this->actingAs($this->pelanggan, 'pelanggan')->get(route('pesanan-saya'))->assertOk();

        $this->actingAs($this->staf(), 'web')->put(route('panel.pelanggan.ubah', $this->pelanggan), [
            'nama' => 'Budi', 'email' => 'budi.baru@contoh.test', 'no_hp' => '081234567890',
        ])->assertSessionHasNoErrors();
        $this->assertSame('budi.baru@contoh.test', $this->pelanggan->fresh()->email);

        // Penjaga sesi memuat ulang pelanggan dari basis data di setiap permintaan.
        $this->pelanggan->refresh();

        $this->get(route('pesanan-saya'))->assertRedirect(route('masuk'));
        $this->assertGuest('pelanggan');
    }

    public function test_ganti_email_membatalkan_tautan_yang_terkirim_ke_email_lama(): void
    {
        $tokenSandi = $this->pelanggan->siapkanAturUlangSandi();
        $this->pelanggan->forceFill(['remember_token' => 'token-ingat-lama'])->save();

        $this->actingAs($this->staf())->put(route('panel.pelanggan.ubah', $this->pelanggan), [
            'nama' => 'Budi', 'email' => 'budi.baru@contoh.test', 'no_hp' => '081234567890',
        ])->assertRedirect();

        $c = $this->pelanggan->fresh();
        $this->assertNull($c->token_atur_sandi);
        $this->assertNull($c->token_verifikasi);
        $this->assertNotSame('token-ingat-lama', $c->remember_token);
        $this->get(route('atur-sandi', $tokenSandi))->assertRedirect(route('lupa-sandi'));
    }

    public function test_email_yang_sudah_dipakai_pelanggan_lain_ditolak(): void
    {
        Customer::create(['nama' => 'Siti', 'email' => 'siti@contoh.test', 'no_hp' => '085700000000']);

        $this->actingAs($this->staf())->put(route('panel.pelanggan.ubah', $this->pelanggan), [
            'nama' => 'Budi', 'email' => 'siti@contoh.test', 'no_hp' => '081234567890',
        ])->assertSessionHasErrors('email');
    }

    public function test_kirim_tautan_atur_ulang_sandi(): void
    {
        $this->actingAs($this->staf())->post(route('panel.pelanggan.atur-sandi', $this->pelanggan))->assertRedirect();

        Mail::assertQueued(AturUlangSandi::class, fn (AturUlangSandi $m) => $m->hasTo('budi@contoh.test'));
        $this->assertTrue(ActivityLog::where('aksi', 'pelanggan.tautan-sandi')->exists());
    }

    public function test_akun_nonaktif_tidak_bisa_masuk_dan_sesinya_berakhir(): void
    {
        $this->actingAs($this->staf())->post(route('panel.pelanggan.nonaktifkan', $this->pelanggan))->assertRedirect();
        $this->assertFalse($this->pelanggan->fresh()->aktif);

        $this->actingAs($this->pelanggan->fresh(), 'pelanggan')->get(route('pesanan-saya'))->assertRedirect(route('masuk'));

        auth('pelanggan')->logout();
        $this->post(route('masuk.proses'), ['email' => 'budi@contoh.test', 'password' => 'sandi-lama-123'])
            ->assertSessionHasErrors('email');
        $this->assertGuest('pelanggan');

        $this->actingAs($this->staf())->post(route('panel.pelanggan.aktifkan', $this->pelanggan))->assertRedirect();
        $this->assertTrue($this->pelanggan->fresh()->aktif);
    }
}
