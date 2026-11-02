<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Isian yang dikirim sebagai array padahal kodenya mengharapkan teks (KEP-104).
 *
 * Ditemukan lewat pemburu galat otomatis yang mengirim setiap formulir dan
 * parameter alamat dengan isian aneh. Teks panjang, karakter khusus, isian
 * kosong, dan berkas palsu sudah ditangani; array tidak: katalog, daftar akun,
 * dan delapan halaman panel jatuh dengan galat 500 hanya karena alamatnya
 * diubah menjadi ?q[]=x, begitu juga beberapa formulir panel dan halaman QR.
 */
class IsianArrayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);
    }

    private function owner(): User
    {
        return User::where('email', 'owner@setiasport.test')->firstOrFail();
    }

    public function test_parameter_alamat_berupa_array_ditolak_bukan_galat(): void
    {
        $this->get('/katalog?kategori[]=x')->assertStatus(400);
        $this->get('/katalog?q[]=x&jalur[]=y')->assertStatus(400);
        $this->get('/daftar?email[]=x')->assertStatus(400);
        $this->getJson('/pengiriman/area?q[]=x')->assertStatus(400);

        $this->actingAs($this->owner());
        foreach (['/panel/pesanan?status[]=x', '/panel/stok?q[]=x', '/panel/laporan?periode[]=x',
            '/panel/aktivitas?q[]=x', '/panel/produk?saring[]=x', '/panel/pengaturan?tab[]=x',
            '/panel/serah-terima?kode[]=x'] as $alamat) {
            $this->get($alamat)->assertStatus(400);
        }

        // Alamat biasa tidak terpengaruh.
        $this->get('/panel/pesanan?status=MENUNGGU_PEMBAYARAN&q=budi')->assertOk();
    }

    public function test_formulir_dengan_isian_array_tidak_berakhir_galat(): void
    {
        $bola = ProductVariant::where('sku', 'BOLA-MITRE-01')->firstOrFail();
        $this->post(route('keranjang.tambah'), ['variant_id' => $bola->id, 'qty' => 1]);
        $this->delete(route('keranjang.hapus'), ['kunci' => ['x']])->assertRedirect();

        $this->post('/checkout', [
            'nama' => 'Budi Santoso', 'email' => 'budi@contoh.test', 'no_hp' => '081234567890', 'setuju' => '1',
        ]);
        $order = Order::latest('id')->firstOrFail();
        $item = $order->items->first();

        // Halaman QR pekerja: PIN teks, pembanding tahapnya array.
        $this->post(route('kerja.selesai', $item->tokenKerja()), ['pin' => '1234', 'dari' => ['x']])
            ->assertRedirect();

        $this->actingAs($this->owner());
        $kategori = Category::firstOrFail();

        $tanpa500 = fn ($r) => $this->assertLessThan(500, $r->status(), $r->getContent() ?: '');

        $tanpa500($this->post(route('panel.pesanan.item.maju', [$order, $item]), ['dari' => ['x']]));
        $tanpa500($this->post(route('panel.antrian.selesai', $item), ['dari' => ['x']]));
        $tanpa500($this->post(route('panel.kategori.pindah', $kategori), ['arah' => ['naik']]));
        $tanpa500($this->post(route('panel.produk.store'), ['nama' => 'X', 'fulfillment_type' => ['READY_STOCK']]));
        $tanpa500($this->post(route('panel.pekerja.simpan'), ['nama' => 'Sari', 'pin' => ['1234'], 'tahap' => ['JAHIT']]));

        $this->put(route('panel.akun.profil'), [
            'name' => 'Pemilik', 'email' => 'baru@contoh.test', 'password_sekarang' => ['password'],
        ])->assertSessionHasErrors('password_sekarang');

        $this->put(route('panel.akun.sandi'), [
            'password_sekarang' => ['password'], 'password' => 'SandiBaru2026', 'password_confirmation' => 'SandiBaru2026',
        ])->assertSessionHasErrors('password_sekarang', null, 'sandi');
    }

    /**
     * "%" dan "_" di kotak pencarian dulu dibaca sebagai pola LIKE, jadi
     * mencari "%" menampilkan seluruh katalog (KEP-105). Pesanan sudah
     * diperbaiki di KEP-099; katalog, produk, dan stok belum.
     */
    public function test_tanda_persen_di_pencarian_dibaca_sebagai_huruf(): void
    {
        $nama = fn ($r) => $r->viewData('produk')->getCollection()->pluck('nama');

        $this->assertCount(0, $nama($this->get(route('katalog', ['q' => '%']))));
        $this->assertNotEmpty($nama($this->get(route('katalog', ['q' => 'jersey']))));

        $this->actingAs($this->owner());
        $this->assertCount(0, $this->get(route('panel.produk.index', ['q' => '%']))->viewData('produk'));
        $this->assertCount(0, $this->get(route('panel.stok.index', ['q' => '_']))->viewData('varian'));
    }
}
