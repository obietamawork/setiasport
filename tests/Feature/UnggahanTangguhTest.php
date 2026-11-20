<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PercakapanCs;
use App\Models\Portofolio;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Temuan uji siksaan di produksi (KEP-154): gambar yang kepalanya sah tetapi
 * isinya rusak, misalnya foto yang terpotong saat diunggah lewat sinyal jelek,
 * dulu berakhir dengan halaman galat 500 di hampir semua tempat unggah. Juga
 * tautan galeri yang 404 di wizard pesan custom saat galeri masih kosong.
 */
class UnggahanTangguhTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');
        Storage::fake('public');
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);
    }

    /** PNG kecil yang kepalanya sah tetapi isinya terpotong. */
    private function gambarRusak(string $nama = 'rusak.png'): UploadedFile
    {
        $utuh = UploadedFile::fake()->image('utuh.png', 40, 40);
        $path = tempnam(sys_get_temp_dir(), 'rusak');
        file_put_contents($path, substr((string) file_get_contents($utuh->getRealPath()), 0, 60));

        return new UploadedFile($path, $nama, 'image/png', null, true);
    }

    private function pesananReadyStock(): Order
    {
        $varian = ProductVariant::where('sku', 'BOLA-MITRE-01')->firstOrFail();
        $this->post(route('keranjang.tambah'), ['variant_id' => $varian->id, 'qty' => 1]);
        $this->post('/checkout', ['nama' => 'Budi', 'email' => 'budi@contoh.test', 'no_hp' => '081234567890', 'metode_ambil' => 'AMBIL_TOKO', 'setuju' => '1']);

        return Order::latest('id')->firstOrFail();
    }

    public function test_bukti_bayar_gambar_rusak_ditolak_dengan_pesan_bukan_galat(): void
    {
        $order = $this->pesananReadyStock();

        $this->from(route('lacak.token', $order->access_token))
            ->post(route('lacak.bukti', $order->access_token), ['bukti' => $this->gambarRusak()])
            ->assertRedirect()
            ->assertSessionHasErrors('bukti');

        $this->assertSame(0, Payment::where('order_id', $order->id)->whereNotNull('bukti_path')->count());
    }

    public function test_foto_chat_rusak_ditolak_tanpa_membuat_percakapan_kosong(): void
    {
        config(['fitur.cs' => true]);
        Setting::simpan('cs_aktif', '1');

        $this->post(route('cs.mulai'), [
            'nama' => 'Pak Warno', 'kontak' => 'warno@contoh.test', 'topik' => 'LAIN', 'lampiran' => $this->gambarRusak(),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lampiran');

        $this->assertSame(0, PercakapanCs::count());
    }

    public function test_unggahan_panel_gambar_rusak_kembali_dengan_pesan(): void
    {
        $pemilik = User::where('email', 'owner@setiasport.test')->firstOrFail();

        $this->actingAs($pemilik)
            ->from(route('panel.pengaturan.index'))
            ->post(route('panel.pengaturan.qris'), ['qris' => $this->gambarRusak('qris.png')])
            ->assertRedirect(route('panel.pengaturan.index'))
            ->assertSessionHasErrors('qris');
    }

    public function test_wizard_custom_tanpa_galeri_tidak_menawarkan_desain_katalog(): void
    {
        $produk = Product::where('slug', 'like', 'custom-%')->firstOrFail();

        $this->get(route('kustom.buat', $produk))
            ->assertOk()
            ->assertDontSee('Lihat galeri desain')
            ->assertDontSee("desainSumber = 'katalog'", false)
            ->assertSee("desainSumber = 'admin'", false)
            ->assertSee('adaGaleri: false', false);
    }

    public function test_wizard_custom_dengan_galeri_menawarkan_desain_katalog(): void
    {
        Portofolio::create(['judul' => 'Jersey SSB', 'slug' => 'jersey-ssb', 'tahun' => 2026, 'gambar_path' => 'portofolio/a.webp', 'aktif' => true]);
        $produk = Product::where('slug', 'like', 'custom-%')->firstOrFail();

        $this->get(route('kustom.buat', $produk))
            ->assertOk()
            ->assertSee('Lihat galeri desain')
            ->assertSee("desainSumber = 'katalog'", false)
            ->assertSee('adaGaleri: true', false);
    }

    public function test_batas_ukuran_foto_dikirim_ke_peramban(): void
    {
        config(['fitur.cs' => true]);
        Setting::simpan('cs_aktif', '1');
        Setting::simpan('upload_maks_mb', '8');

        // @js menulis tanda kutip sebagai ".
        $this->get('/')->assertSee('batasFotoMb'.chr(92).'u0022:8', false);
        $order = $this->pesananReadyStock();
        $this->get(route('lacak.token', $order->access_token))->assertSee('data-batas-mb="8"', false);
    }
}
