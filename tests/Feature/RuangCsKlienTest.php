<?php

namespace Tests\Feature;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PercakapanCs;
use App\Models\Product;
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
 * Ruang CS mengikuti gambar klien (KEP-150): tab Semua/Baru/Diproses/Selesai
 * berangka, kartu Detail Pelanggan dan Detail Pesanan, Catatan Internal, dan
 * Balasan Cepat.
 */
class RuangCsKlienTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);
        config(['fitur.cs' => true]);
        Setting::simpan('cs_aktif', '1');
        $this->withCredentials();
    }

    private function admin(): User
    {
        return User::where('email', 'admin@setiasport.test')->firstOrFail();
    }

    private function mulai(string $nama = 'Shannia'): PercakapanCs
    {
        $this->postJson(route('cs.mulai'), [
            'nama' => $nama, 'kontak' => strtolower($nama).'@contoh.test', 'topik' => 'KUSTOM',
            'isi' => 'Kak, desainnya bisa direvisi di bagian warna kerahnya saja?',
        ])->assertCreated();

        return PercakapanCs::latest('id')->firstOrFail();
    }

    /** Percakapan yang tertaut ke pesanan terverifikasi milik pelanggan berakun. */
    private function percakapanBerpesanan(): PercakapanCs
    {
        $customer = Customer::create(['nama' => 'Shannia', 'email' => 'shannia@contoh.test', 'no_hp' => '087812347271']);
        $order = Order::create([
            'kode' => Order::kodeBaru(), 'customer_id' => $customer->id, 'status' => OrderStatus::DIPROSES,
            'access_token' => Order::tokenBaru(), 'tipe_pembayaran' => 'DP', 'target_siap_pada' => '2026-11-25',
        ]);
        $order->items()->create([
            'product_id' => Product::where('sku', 'CUST-JRS-ATASAN')->value('id'),
            'nama_produk' => 'Custom Jersey Atasan', 'fulfillment_type' => 'PREORDER', 'qty' => 6, 'harga_satuan' => 70_000,
            'status_item' => OrderItemStatus::ANTRIAN_DESAIN,
        ]);

        $percakapan = $this->mulai();
        $percakapan->update(['customer_id' => $customer->id, 'order_id' => $order->id, 'order_terverifikasi' => true]);

        return $percakapan;
    }

    public function test_ruang_cs_memakai_susunan_gambar_klien(): void
    {
        $this->actingAs($this->admin())->get(route('panel.cs.index'))
            ->assertOk()
            ->assertSeeInOrder(['Semua', 'Baru', 'Diproses', 'Selesai'])
            ->assertSee('Balasan Cepat')
            ->assertSee('Detail Pelanggan')
            ->assertSee('Detail Pesanan')
            ->assertSee('Catatan Internal');
    }

    public function test_tiap_tab_berangka_dan_diproses_tanpa_yang_baru(): void
    {
        $this->mulai('Budi');
        $dibalas = $this->mulai('Rina');
        $this->actingAs($this->admin())
            ->postJson(route('panel.cs.balas', $dibalas), ['isi' => 'Siap, kami cek.'])->assertSuccessful();

        $this->actingAs($this->admin())
            ->getJson(route('panel.cs.daftar', ['saring' => 'terbuka']))
            ->assertOk()
            ->assertJsonCount(1, 'daftar')
            ->assertJsonPath('daftar.0.id', $dibalas->id)
            ->assertJsonPath('jumlah.semua', 2)
            ->assertJsonPath('jumlah.menunggu', 1)
            ->assertJsonPath('jumlah.terbuka', 1)
            ->assertJsonPath('jumlah.selesai', 0);
    }

    public function test_detail_memuat_kartu_pelanggan_dan_pesanan(): void
    {
        $percakapan = $this->percakapanBerpesanan();

        $this->actingAs($this->admin())
            ->getJson(route('panel.cs.detail', $percakapan))
            ->assertOk()
            ->assertJsonPath('percakapan.pelanggan.nama', 'Shannia')
            ->assertJsonPath('percakapan.pelanggan.no_hp', '087812347271')
            ->assertJsonPath('percakapan.pelanggan.email', 'shannia@contoh.test')
            ->assertJsonPath('percakapan.pelanggan.total_pesanan', 1)
            ->assertJsonPath('percakapan.order.jenis', 'Custom')
            ->assertJsonPath('percakapan.order.jumlah', '6 pcs')
            ->assertJsonPath('percakapan.order.target', '25 Nov 2026');
    }

    public function test_catatan_internal_tersimpan_dan_tidak_sampai_ke_pelanggan(): void
    {
        $percakapan = $this->mulai();
        $catatan = 'Permintaan revisi: ubah warna kerah. Kirim ulang mockup maksimal 1 jam.';

        $this->actingAs($this->admin())
            ->postJson(route('panel.cs.catatan', $percakapan), ['catatan' => $catatan])
            ->assertOk()
            ->assertJsonPath('percakapan.catatan_internal', $catatan);

        $this->assertSame($catatan, $percakapan->fresh()->catatan_internal);

        // Pelanggan memegang cookie percakapannya sendiri; catatannya tetap tidak ikut.
        $this->assertStringNotContainsString('ubah warna kerah', (string) $this->getJson(route('cs.status'))->getContent());
    }

    public function test_staf_tanpa_izin_cs_tidak_bisa_menulis_catatan(): void
    {
        $percakapan = $this->mulai();

        $this->actingAs(User::factory()->create())
            ->postJson(route('panel.cs.catatan', $percakapan), ['catatan' => 'x'])
            ->assertForbidden();
    }

    public function test_foto_dari_pelanggan_bisa_diunduh_staf(): void
    {
        Storage::fake('local');
        $percakapan = $this->mulai();
        Storage::disk('local')->put('cs/desain.jpg', UploadedFile::fake()->image('desain.jpg', 600, 600)->getContent());
        $pesan = $percakapan->pesan()->create(['pengirim' => 'PELANGGAN', 'lampiran_path' => 'cs/desain.jpg']);

        $unduh = $this->actingAs($this->admin())
            ->get(route('panel.cs.lampiran', [$percakapan, $pesan, 'unduh' => 1]))
            ->assertOk();

        // Tanpa ?unduh foto tampil di tab (inline); dengan ?unduh tersimpan sebagai berkas.
        $this->assertStringStartsWith('attachment', (string) $unduh->headers->get('content-disposition'));
    }

    public function test_menu_panel_punya_pusat_bantuan(): void
    {
        $this->actingAs($this->admin())->get(route('panel.dashboard'))
            ->assertOk()
            ->assertSee('Pusat Bantuan')
            ->assertSee(route('panel.bantuan'));
    }
}
