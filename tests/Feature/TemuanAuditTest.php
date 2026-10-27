<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Temuan audit developer, penguji, keamanan, dan UI/UX (KEP-088, KEP-089).
 */
class TemuanAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['ongkir.penyedia' => 'mati']);
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);
    }

    private function owner(): User
    {
        return User::where('email', 'owner@setiasport.test')->firstOrFail();
    }

    /** @param  array<string, mixed>  $tambahan */
    private function pesanSatuBola(array $tambahan = [])
    {
        $varian = ProductVariant::where('sku', 'BOLA-MITRE-01')->firstOrFail();
        $this->post(route('keranjang.tambah'), ['variant_id' => $varian->id, 'qty' => 1]);

        return $this->post(route('checkout.proses'), array_merge([
            'nama' => 'Budi Santoso', 'email' => 'budi@contoh.test',
            'no_hp' => '081234567890', 'setuju' => '1',
        ], $tambahan));
    }

    /** Setiap pesanan menahan stok sampai batas bayar; skrip tidak boleh memborongnya. */
    public function test_pesanan_baru_dibatasi_per_jaringan(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->pesanSatuBola()->assertSessionHasNoErrors();
        }

        $this->from(route('checkout'))
            ->pesanSatuBola(['buat_akun' => '1', 'password' => 'rahasia123', 'password_confirmation' => 'rahasia123'])
            ->assertRedirect(route('checkout'))
            ->assertSessionHasErrors('checkout')
            // Isian pelanggan tidak hilang, kecuali kata sandinya.
            ->assertSessionHasInput('nama', 'Budi Santoso')
            ->assertSessionMissing('_old_input.password');

        $this->assertSame(5, Order::count());
    }

    /** Salah isi formulir tidak mendekatkan pelanggan ke batas. */
    public function test_salah_isi_formulir_tidak_dihitung(): void
    {
        for ($i = 0; $i < 8; $i++) {
            $this->pesanSatuBola(['email' => 'bukan-email'])->assertSessionHasErrors('email');
        }

        $this->pesanSatuBola()->assertSessionHasNoErrors();
        $this->assertSame(1, Order::count());
    }

    /**
     * old() di dalam x-data dulu ditulis sebagai string JS di antara kutip
     * tunggal: isian yang memuat kutip bisa keluar dari string itu.
     */
    public function test_isian_lama_di_alpine_tidak_bisa_keluar_dari_string(): void
    {
        $sisipan = "x' + alert(document.cookie) + '";

        $this->actingAs($this->owner())
            ->from(route('panel.produk.create'))
            ->post(route('panel.produk.store'), ['nama' => 'Uji', 'fulfillment_type' => $sisipan])
            ->assertSessionHasErrors('fulfillment_type');

        $this->actingAs($this->owner())
            ->get(route('panel.produk.create'))
            ->assertOk()
            ->assertDontSee("jalur: 'x' + alert", false)
            // Kutip tunggal dikodekan @js() sebagai escape unicode JSON.
            ->assertSee($this->kutipJs().' + alert(document.cookie) + '.$this->kutipJs(), false);
    }

    private function kutipJs(): string
    {
        return trim(json_encode("'", JSON_HEX_APOS), '"');
    }

    /**
     * confirm() bawaan berjalan sesudah penjaga kirim ganda, jadi membatalkannya
     * mematikan tombol. Konfirmasi kini lewat data-konfirmasi (app.js).
     */
    public function test_tidak_ada_lagi_confirm_bawaan_di_tampilan(): void
    {
        $pemakai = collect(File::allFiles(resource_path('views')))
            // Pemanggilan berargumen saja; komentar yang menyebut "confirm()" tidak dihitung.
            ->filter(fn ($f) => preg_match('/\bconfirm\((?!\))/', $f->getContents()))
            ->map(fn ($f) => $f->getRelativePathname())
            ->values()
            ->all();

        $this->assertSame([], $pemakai);

        $this->actingAs($this->owner())
            ->get(route('panel.produk.edit', 'bola-futsal-mitre-ultimatch'))
            ->assertOk()
            ->assertSee('data-konfirmasi="Hapus produk Bola Futsal Mitre Ultimatch?"', false)
            ->assertSee('id="dialog-konfirmasi"', false);
    }

    /** Lapisan tautan ::before di <tr> salah sasaran di Safari. */
    public function test_baris_tabel_panel_memakai_data_tautan(): void
    {
        $this->pesanSatuBola();
        $order = Order::firstOrFail();

        $this->actingAs($this->owner())
            ->get(route('panel.pesanan.index'))
            ->assertSee('data-tautan="'.route('panel.pesanan.show', $order).'"', false);

        $this->actingAs($this->owner())
            ->get(route('panel.produk.index'))
            ->assertSee('data-tautan="'.route('panel.produk.edit', 'bola-futsal-mitre-ultimatch').'"', false)
            ->assertDontSee('<tr class="group relative', false);
    }

    /** Situs HTTPS tidak boleh mengirim cookie sesi tanpa Secure karena .env lupa diisi. */
    public function test_cookie_sesi_secure_mengikuti_app_url(): void
    {
        // env() membaca $_SERVER dan $_ENV lebih dulu daripada putenv().
        $asli = [$_SERVER['APP_URL'] ?? null, $_ENV['APP_URL'] ?? null];

        try {
            $_SERVER['APP_URL'] = $_ENV['APP_URL'] = 'https://setiasport.example';
            $this->assertTrue((require config_path('session.php'))['secure']);

            $_SERVER['APP_URL'] = $_ENV['APP_URL'] = 'http://localhost:8080';
            $this->assertFalse((require config_path('session.php'))['secure']);
        } finally {
            [$_SERVER['APP_URL'], $_ENV['APP_URL']] = $asli;
        }
    }
}
