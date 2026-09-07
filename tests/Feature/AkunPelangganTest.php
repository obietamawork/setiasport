<?php

namespace Tests\Feature;

use App\Mail\KonfirmasiAkun;
use App\Models\Customer;
use App\Models\Order;
use App\Models\ProductVariant;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Akun pelanggan adalah jalur KEDUA, bukan pengganti (KEP-020), dan
 * penguasaan email wajib dibuktikan sebelum akunnya bisa dipakai (KEP-021).
 */
class AkunPelangganTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);
    }

    private function isiKeranjang(int $qty = 1): void
    {
        $this->post('/keranjang', [
            'variant_id' => ProductVariant::where('sku', 'BOLA-MITRE-01')->value('id'),
            'qty' => $qty,
        ]);
    }

    private function dataCheckout(array $tambahan = []): array
    {
        return array_merge([
            'nama' => 'Budi Santoso',
            'email' => 'budi@contoh.test',
            'no_hp' => '081234567890',
            'setuju' => '1',
        ], $tambahan);
    }

    private function dataDaftar(array $tambahan = []): array
    {
        return array_merge([
            'nama' => 'Budi Santoso',
            'email' => 'budi@contoh.test',
            'no_hp' => '081234567890',
            'password' => 'rahasia12345',
            'password_confirmation' => 'rahasia12345',
        ], $tambahan);
    }

    private function pelangganAktif(string $email = 'rian@contoh.test'): Customer
    {
        $c = Customer::create([
            'nama' => 'Rian Pratama',
            'email' => $email,
            'no_hp' => '081298765432',
            'password' => Hash::make('rahasia12345'),
        ]);

        $c->selesaikanKonfirmasi();

        return $c->fresh();
    }

    /** Menyalin token mentah dari email yang diantre. */
    private function tokenTerkirim(): string
    {
        $tautan = null;

        Mail::assertQueued(KonfirmasiAkun::class, function (KonfirmasiAkun $m) use (&$tautan) {
            $tautan = $m->tautan;

            return true;
        });

        return basename($tautan);
    }

    public function test_checkout_tamu_tetap_jalan_tanpa_akun(): void
    {
        $this->isiKeranjang();
        $this->post('/checkout', $this->dataCheckout())->assertRedirect();

        $this->assertFalse(Customer::where('email', 'budi@contoh.test')->firstOrFail()->akunAktif());
        $this->assertGuest('pelanggan');
        $this->assertSame(1, Order::count());
    }

    public function test_tautan_bertoken_tetap_membuka_pesanan_tanpa_masuk(): void
    {
        $this->isiKeranjang();
        $this->post('/checkout', $this->dataCheckout());

        $order = Order::latest('id')->firstOrFail();

        $this->get(route('lacak.token', $order->access_token))->assertOk()->assertSee($order->kode);
    }

    public function test_pendaftaran_tidak_langsung_memberi_akses(): void
    {
        // Pesanan tamu lebih dulu ada, lalu orang lain mencoba mendaftar
        // memakai email yang sama.
        $this->isiKeranjang();
        $this->post('/checkout', $this->dataCheckout());

        $this->post('/daftar', $this->dataDaftar(['nama' => 'Penyerang']))
            ->assertRedirect(route('daftar.terkirim'));

        // Email pelanggan bukan rahasia, jadi mengetahuinya saja tidak boleh
        // cukup untuk mewarisi riwayat pesanan orang lain (KEP-021).
        $this->assertGuest('pelanggan');
        $this->get('/pesanan-saya')->assertRedirect(route('masuk'));

        $pelanggan = Customer::where('email', 'budi@contoh.test')->firstOrFail();
        $this->assertFalse($pelanggan->akunAktif());

        // Nama pemilik asli tidak ikut tertimpa.
        $this->assertSame('Budi Santoso', $pelanggan->nama);
    }

    public function test_masuk_ditolak_selama_belum_dikonfirmasi(): void
    {
        $this->post('/daftar', $this->dataDaftar());

        $this->post('/masuk', ['email' => 'budi@contoh.test', 'password' => 'rahasia12345'])
            ->assertSessionHasErrors('email');

        $this->assertGuest('pelanggan');
    }

    public function test_konfirmasi_mengaktifkan_akun_dan_mewarisi_riwayat(): void
    {
        $this->isiKeranjang();
        $this->post('/checkout', $this->dataCheckout());

        $this->post('/daftar', $this->dataDaftar());

        $this->get(route('daftar.konfirmasi', $this->tokenTerkirim()))
            ->assertRedirect(route('pesanan-saya'));

        $pelanggan = Customer::where('email', 'budi@contoh.test')->firstOrFail();

        $this->assertTrue($pelanggan->akunAktif());
        $this->assertAuthenticatedAs($pelanggan, 'pelanggan');
        $this->get('/pesanan-saya')->assertOk()->assertSee(Order::first()->kode);
    }

    public function test_akun_aktif_tidak_bisa_direbut_lewat_pendaftaran_ulang(): void
    {
        $korban = $this->pelangganAktif('rian@contoh.test');

        $this->post('/daftar', $this->dataDaftar([
            'nama' => 'Penyerang',
            'email' => 'rian@contoh.test',
            'password' => 'sandi-penyerang',
            'password_confirmation' => 'sandi-penyerang',
        ]))->assertRedirect(route('daftar.terkirim'));

        $korban->refresh();

        // Kata sandi, nama, dan status akunnya tidak boleh tersentuh sama sekali.
        $this->assertTrue(Hash::check('rahasia12345', $korban->password));
        $this->assertSame('Rian Pratama', $korban->nama);
        Mail::assertNotQueued(KonfirmasiAkun::class);

        $this->post('/masuk', ['email' => 'rian@contoh.test', 'password' => 'sandi-penyerang'])
            ->assertSessionHasErrors('email');
    }

    public function test_jawaban_pendaftaran_sama_untuk_email_dikenal_maupun_tidak(): void
    {
        $this->pelangganAktif('rian@contoh.test');

        $terdaftar = $this->post('/daftar', $this->dataDaftar(['email' => 'rian@contoh.test']));
        $baru = $this->post('/daftar', $this->dataDaftar(['email' => 'orang-baru@contoh.test']));

        // Halaman ini tidak boleh bisa dipakai memastikan email mana yang
        // terdaftar di toko.
        $terdaftar->assertRedirect(route('daftar.terkirim'));
        $baru->assertRedirect(route('daftar.terkirim'));
    }

    public function test_kata_sandi_salah_memberi_pesan_yang_sama_dengan_email_tak_dikenal(): void
    {
        $this->pelangganAktif('rian@contoh.test');

        $this->post('/masuk', ['email' => 'rian@contoh.test', 'password' => 'salah'])
            ->assertSessionHasErrors(['email' => 'Email atau kata sandi tidak cocok.']);

        $this->post('/masuk', ['email' => 'entah@contoh.test', 'password' => 'salah'])
            ->assertSessionHasErrors(['email' => 'Email atau kata sandi tidak cocok.']);

        $this->assertGuest('pelanggan');
    }

    public function test_tautan_konfirmasi_kedaluwarsa_ditolak(): void
    {
        $this->post('/daftar', $this->dataDaftar());
        $token = $this->tokenTerkirim();

        Customer::where('email', 'budi@contoh.test')
            ->update(['token_dibuat_pada' => now()->subDays(2)]);

        $this->get(route('daftar.konfirmasi', $token))
            ->assertRedirect(route('daftar'))
            ->assertSessionHasErrors();

        $this->assertGuest('pelanggan');
    }

    public function test_token_asal_ditolak(): void
    {
        $this->post('/daftar', $this->dataDaftar());

        $this->get(route('daftar.konfirmasi', str_repeat('a', 64)))
            ->assertRedirect(route('daftar'));

        $this->assertGuest('pelanggan');
    }

    public function test_akun_dari_checkout_juga_menunggu_konfirmasi(): void
    {
        $this->isiKeranjang();

        $this->post('/checkout', $this->dataCheckout([
            'buat_akun' => '1',
            'password' => 'rahasia12345',
            'password_confirmation' => 'rahasia12345',
        ]))->assertRedirect();

        // Pesanannya tetap berjalan normal; hanya akunnya yang menunggu.
        $this->assertSame(1, Order::count());
        $this->assertGuest('pelanggan');
        Mail::assertQueued(KonfirmasiAkun::class);

        $this->get(route('daftar.konfirmasi', $this->tokenTerkirim()))
            ->assertRedirect(route('pesanan-saya'));

        $this->assertAuthenticated('pelanggan');
    }

    public function test_kata_sandi_hanya_diperiksa_bila_akun_dibuat(): void
    {
        $this->isiKeranjang();

        $this->post('/checkout', $this->dataCheckout(['password' => '']))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Order::count());
    }

    public function test_kata_sandi_lemah_ditolak_saat_membuat_akun(): void
    {
        $this->isiKeranjang();

        $this->post('/checkout', $this->dataCheckout([
            'buat_akun' => '1',
            'password' => '123',
            'password_confirmation' => '123',
        ]))->assertSessionHasErrors('password');

        $this->assertSame(0, Order::count());
    }

    public function test_pemesan_tamu_tidak_menimpa_profil_akun_aktif(): void
    {
        $korban = $this->pelangganAktif('rian@contoh.test');

        $this->isiKeranjang();
        $this->post('/checkout', $this->dataCheckout([
            'nama' => 'Orang Lain',
            'email' => 'rian@contoh.test',
            'no_hp' => '080000000000',
        ]))->assertRedirect();

        $korban->refresh();

        $this->assertSame('Rian Pratama', $korban->nama);
        $this->assertSame('081298765432', $korban->no_hp);
    }

    public function test_halaman_pesanan_saya_tertutup_bagi_tamu(): void
    {
        // Diarahkan ke halaman masuk pelanggan, bukan ke halaman masuk staf.
        $this->get('/pesanan-saya')->assertRedirect(route('masuk'));
    }

    public function test_pelanggan_tidak_bisa_masuk_panel_internal(): void
    {
        // Penjaga sesi terpisah: akun pelanggan tidak pernah menyentuh panel.
        $this->actingAs($this->pelangganAktif(), 'pelanggan')
            ->get('/panel')
            ->assertRedirect(route('panel.login'));
    }

    public function test_pelanggan_yang_masuk_tidak_perlu_mengetik_ulang_datanya(): void
    {
        $pelanggan = $this->pelangganAktif();

        $this->actingAs($pelanggan, 'pelanggan');
        $this->isiKeranjang();

        // Email tidak dikirim dari formulir karena kolomnya terkunci.
        $this->post('/checkout', ['nama' => 'Rian Pratama', 'setuju' => '1'])
            ->assertSessionHasNoErrors();

        $this->assertSame($pelanggan->id, Order::latest('id')->firstOrFail()->customer_id);
        $this->assertSame(1, Customer::count());
    }

    public function test_lacak_manual_mengalihkan_pelanggan_yang_sudah_masuk(): void
    {
        $this->actingAs($this->pelangganAktif(), 'pelanggan')
            ->get('/lacak')
            ->assertRedirect(route('pesanan-saya'));
    }
}
