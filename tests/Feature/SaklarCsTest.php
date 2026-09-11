<?php

namespace Tests\Feature;

use App\Mail\EmailPesanan;
use App\Models\Order;
use App\Models\PercakapanCs;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\User;
use App\Services\LayananCs;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Satu kode, dua versi (KEP-033). Klien belum yakin akan memakai CS, jadi
 * mematikan saklar di Pengaturan harus menghasilkan website yang bersih tanpa
 * CS: tidak ada teks yang menyuruh pelanggan chat ke CS yang tidak ada, dan
 * tidak ada jalur tersisa untuk mengirim pesan ke kotak yang tidak dibaca.
 */
class SaklarCsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);
    }

    private function matikanCs(): void
    {
        Setting::simpan('cs_aktif', '0');
    }

    private function admin(): User
    {
        return User::where('email', 'admin@setiasport.test')->firstOrFail();
    }

    private function buatPesanan(): Order
    {
        $bola = ProductVariant::where('sku', 'BOLA-MITRE-01')->firstOrFail();
        $this->post('/keranjang', ['variant_id' => $bola->id, 'qty' => 1]);
        $this->post('/checkout', [
            'nama' => 'Budi', 'email' => 'budi@contoh.test', 'no_hp' => '081234567890', 'setuju' => '1',
        ]);

        return Order::latest('id')->firstOrFail();
    }

    private function tidakMenyebutChatCs(string $html, string $halaman): void
    {
        $this->assertDoesNotMatchRegularExpression('/chat cs/i', $html,
            "Halaman $halaman masih menyebut chat CS padahal CS dimatikan.");
    }

    public function test_halaman_publik_bersih_dari_chat_cs_dan_beralih_ke_whatsapp(): void
    {
        $this->matikanCs();

        $beranda = $this->get(route('beranda'))->assertOk();
        $this->tidakMenyebutChatCs($beranda->getContent(), 'beranda');
        $beranda->assertDontSee('widgetCs', false);
        $beranda->assertSee('https://wa.me/6285160025810', false);
        $beranda->assertSee('WhatsApp toko 0851-6002-5810');

        $this->tidakMenyebutChatCs($this->get(route('katalog'))->getContent(), 'katalog');

        $bola = ProductVariant::where('sku', 'BOLA-MITRE-01')->firstOrFail();
        $this->post('/keranjang', ['variant_id' => $bola->id, 'qty' => 1]);
        $this->tidakMenyebutChatCs($this->get(route('checkout'))->getContent(), 'checkout');
    }

    public function test_halaman_pesanan_menawarkan_whatsapp_bukan_chat(): void
    {
        $order = $this->buatPesanan();
        $this->matikanCs();

        $halaman = $this->get(route('lacak.token', $order->access_token))->assertOk();

        $this->tidakMenyebutChatCs($halaman->getContent(), 'pesanan');
        $halaman->assertSee('Hubungi lewat WhatsApp');
        $halaman->assertSee(rawurlencode($order->kode), false);
    }

    public function test_email_menyebut_whatsapp_saat_cs_mati(): void
    {
        $order = $this->buatPesanan();
        $this->matikanCs();

        $html = (new EmailPesanan($order->fresh(), 'order-dibuat', 'Pesanan', null))->render();

        $this->tidakMenyebutChatCs($html, 'email');
        $this->assertStringContainsString('WhatsApp toko 0851-6002-5810', $html);
    }

    /**
     * Pelanggan yang sudah punya percakapan tidak boleh tetap bisa mengirim
     * pesan setelah CS dimatikan: tidak ada yang membaca kotak itu lagi.
     */
    public function test_semua_jalur_chat_pelanggan_tertutup(): void
    {
        $this->postJson(route('cs.mulai'), [
            'nama' => 'Pak Warno', 'kontak' => 'warno@contoh.test', 'topik' => 'STOK', 'isi' => 'Ada stok bola?',
        ])->assertCreated();

        $this->matikanCs();

        $this->getJson(route('cs.status'))->assertNotFound();
        $this->postJson(route('cs.kirim'), ['isi' => 'Halo, masih ada?'])->assertNotFound();
        $this->postJson(route('cs.mulai'), [
            'nama' => 'Orang lain', 'kontak' => 'lain@contoh.test', 'topik' => 'STOK', 'isi' => 'Tes',
        ])->assertNotFound();

        $this->assertSame(1, PercakapanCs::firstOrFail()->pesan()->count());
    }

    public function test_riwayat_tetap_bisa_dibaca_sebagai_arsip_tapi_tidak_dibalas(): void
    {
        $this->postJson(route('cs.mulai'), [
            'nama' => 'Bu Sri', 'kontak' => 'sri@contoh.test', 'topik' => 'KOMPLAIN', 'isi' => 'Jahitan lepas.',
        ])->assertCreated();
        $percakapan = PercakapanCs::firstOrFail();

        $this->matikanCs();

        // Log chat adalah catatan resmi komplain: tidak boleh ikut hilang.
        $this->actingAs($this->admin())->get(route('panel.dashboard'))->assertSee('Arsip chat');
        $this->actingAs($this->admin())->get(route('panel.cs.show', $percakapan))
            ->assertOk()
            ->assertSee('ini arsip');

        $this->actingAs($this->admin())
            ->postJson(route('panel.cs.balas', $percakapan), ['isi' => 'Kami cek.'])
            ->assertStatus(422);
    }

    public function test_menu_chat_hilang_kalau_cs_mati_dan_belum_pernah_dipakai(): void
    {
        $this->matikanCs();

        $this->actingAs($this->admin())->get(route('panel.dashboard'))
            ->assertOk()
            ->assertDontSee('Arsip chat')
            ->assertDontSee('Chat CS');
    }

    public function test_tidak_ada_peringatan_email_saat_cs_mati(): void
    {
        $this->travelTo(now()->setTime(10, 0));

        $this->postJson(route('cs.mulai'), [
            'nama' => 'Pak Warno', 'kontak' => 'warno@contoh.test', 'topik' => 'STOK', 'isi' => 'Ada stok?',
        ]);
        PercakapanCs::firstOrFail()->update(['menunggu_sejak' => now()->subHour()]);

        $this->matikanCs();

        $this->assertSame(0, app(LayananCs::class)->peringatkanYangMenunggu());
    }

    public function test_menyalakan_lagi_mengembalikan_semuanya(): void
    {
        $this->matikanCs();
        Setting::simpan('cs_aktif', '1');

        $this->get(route('beranda'))->assertSee('Chat dengan CS');
        $this->getJson(route('cs.status'))->assertOk();
    }
}
