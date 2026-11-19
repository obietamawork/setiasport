<?php

namespace Tests\Feature;

use App\Models\FaqBot;
use App\Models\LogBot;
use App\Models\Order;
use App\Models\PercakapanCs;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\FaqBotSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Chatbot CS berlapis (KEP-151): menu topik dan pencocokan FAQ lebih dulu,
 * lalu admin manusia. Lapis AI di antaranya diuji di AsistenAiTest.
 */
class AsistenCsTest extends TestCase
{
    use RefreshDatabase;

    private const COOKIE = 'cs_percakapan';

    private string $token = '';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class, FaqBotSeeder::class]);
        config(['fitur.cs' => true]);
        Setting::simpan('cs_aktif', '1');
        Setting::simpan('bot_aktif', '1');
        $this->withCredentials();
    }

    private function mulai(string $isi, string $topik = 'KUSTOM', array $tambahan = []): TestResponse
    {
        $res = $this->postJson(route('cs.mulai'), array_merge([
            'nama' => 'Pak Warno', 'kontak' => 'warno@contoh.test', 'topik' => $topik, 'isi' => $isi,
        ], $tambahan))->assertCreated();

        $cookie = collect($res->headers->getCookies())->first(fn ($c) => $c->getName() === self::COOKIE);
        $this->token = CookieValuePrefix::remove(app('encrypter')->decrypt($cookie->getValue(), false));

        return $res;
    }

    private function kirim(string $isi): TestResponse
    {
        return $this->withCookie(self::COOKIE, $this->token)
            ->postJson(route('cs.kirim'), ['isi' => $isi])
            ->assertCreated();
    }

    private function percakapan(): PercakapanCs
    {
        return PercakapanCs::latest('id')->firstOrFail();
    }

    /** @return list<string> */
    private function isiBalasan(TestResponse $res): array
    {
        return collect($res->json('balasan'))->pluck('isi')->all();
    }

    // ------------------------------------------------------------ lapis FAQ

    public function test_pertanyaan_umum_dijawab_faq_tanpa_membunyikan_staf(): void
    {
        $res = $this->mulai('Berapa minimal pesan jersey custom kak?');

        $res->assertJsonPath('balasan.0.pengirim', 'BOT');
        $this->assertStringContainsString('Minimal pemesanan', $this->isiBalasan($res)[0]);
        $this->assertContains('Tidak membantu', $res->json('balasan.0.pilihan'));

        $percakapan = $this->percakapan();
        $this->assertSame('BOT', $percakapan->ditangani);
        $this->assertNull($percakapan->menunggu_sejak);
        $this->assertSame(0, $percakapan->belum_dibaca_staf);
        $this->assertSame('faq', LogBot::latest('id')->value('lapis'));
    }

    public function test_jawaban_faq_memakai_data_toko(): void
    {
        Setting::simpan('toko_alamat', 'Jl. Manyar II No.2, Wonogiri');

        $res = $this->mulai('Alamat tokonya di mana?');

        $this->assertStringContainsString('Jl. Manyar II No.2, Wonogiri', $this->isiBalasan($res)[0]);
        $this->assertStringNotContainsString('{toko_alamat}', $this->isiBalasan($res)[0]);
    }

    public function test_salam_dijawab_dengan_menu_topik(): void
    {
        $res = $this->mulai('Halo kak, permisi');

        $pilihan = $res->json('balasan.0.pilihan');
        $this->assertContains('Bicara dengan admin', $pilihan);
        $this->assertContains(FaqBot::where('tampil_di_menu', true)->orderBy('urutan')->value('pertanyaan'), $pilihan);
    }

    public function test_pertanyaan_yang_cocok_dengan_beberapa_faq_ditawari_pilihan(): void
    {
        FaqBot::create(['pertanyaan' => 'Apakah bisa sablon kaos polos?', 'jawaban' => 'Bisa.', 'kata_kunci' => 'sablon', 'aktif' => true]);
        FaqBot::create(['pertanyaan' => 'Berapa harga sablon logo?', 'jawaban' => 'Mulai Rp5.000.', 'kata_kunci' => 'sablon', 'aktif' => true]);

        $res = $this->mulai('sablon');

        $pilihan = $res->json('balasan.0.pilihan');
        $this->assertContains('Apakah bisa sablon kaos polos?', $pilihan);
        $this->assertContains('Berapa harga sablon logo?', $pilihan);
    }

    // ---------------------------------------------------- serah ke manusia

    public function test_topik_komplain_langsung_ke_admin(): void
    {
        $res = $this->mulai('Jahitan jersey lepas.', 'KOMPLAIN');

        $this->assertSame([], $res->json('balasan'));
        $this->assertSame('ADMIN', $this->percakapan()->ditangani);
        $this->assertNotNull($this->percakapan()->menunggu_sejak);
    }

    public function test_permintaan_admin_diteruskan(): void
    {
        $this->mulai('Berapa minimal pesan?');
        $res = $this->kirim('Bicara dengan admin');

        $percakapan = $this->percakapan();
        $this->assertSame('ADMIN', $percakapan->ditangani);
        $this->assertSame('Pelanggan meminta admin', $percakapan->alasan_serah);
        $this->assertNotNull($percakapan->menunggu_sejak);
        $this->assertGreaterThan(0, $percakapan->belum_dibaca_staf);
        $this->assertStringContainsString('admin', strtolower($this->isiBalasan($res)[0]));
    }

    public function test_kata_komplain_dan_masalah_pembayaran_diteruskan(): void
    {
        $this->mulai('Jerseynya robek di bagian ketiak.');
        $this->assertSame('Komplain', $this->percakapan()->alasan_serah);

        $this->mulai('Saya sudah transfer tapi belum diverifikasi.', 'LAIN');
        $this->assertSame('Pembayaran', $this->percakapan()->alasan_serah);
    }

    public function test_foto_dari_pelanggan_diteruskan(): void
    {
        Storage::fake('local');
        $this->mulai('Halo');

        $this->withCookie(self::COOKIE, $this->token)
            ->post(route('cs.kirim'), ['lampiran' => UploadedFile::fake()->image('desain.jpg', 400, 400)], ['Accept' => 'application/json'])
            ->assertCreated();

        $this->assertSame('ADMIN', $this->percakapan()->ditangani);
    }

    public function test_tanpa_faq_cocok_dan_ai_mati_diteruskan_dan_masuk_daftar_kurasi(): void
    {
        $this->mulai('Apakah bisa bordir topi untuk komunitas motor?');

        $this->assertSame('ADMIN', $this->percakapan()->ditangani);
        $log = LogBot::latest('id')->firstOrFail();
        $this->assertSame('admin', $log->lapis);
        $this->assertNull($log->dikurasi_pada);
    }

    public function test_dua_kali_tidak_membantu_diteruskan(): void
    {
        $this->mulai('Berapa minimal pesan?');
        $this->kirim('Tidak membantu');
        $this->assertSame('BOT', $this->percakapan()->ditangani);

        $this->kirim('Tidak membantu');
        $this->assertSame('ADMIN', $this->percakapan()->ditangani);
    }

    public function test_balasan_staf_menghentikan_bot(): void
    {
        $this->mulai('Berapa minimal pesan?');
        $this->actingAs(User::where('email', 'admin@setiasport.test')->firstOrFail())
            ->postJson(route('panel.cs.balas', $this->percakapan()), ['isi' => 'Halo, saya Rina dari CS.'])
            ->assertSuccessful();
        auth('web')->logout();

        $res = $this->kirim('Berapa lama pengerjaannya?');

        $this->assertSame([], $res->json('balasan'));
        $this->assertSame('ADMIN', $this->percakapan()->ditangani);
        $this->assertNotNull($this->percakapan()->menunggu_sejak);
    }

    public function test_bot_dimatikan_semua_chat_ke_admin(): void
    {
        Setting::simpan('bot_aktif', '0');

        $res = $this->mulai('Berapa minimal pesan?');

        $this->assertSame([], $res->json('balasan'));
        $this->assertSame('ADMIN', $this->percakapan()->ditangani);
    }

    // ------------------------------------------------- jawaban dari sistem

    public function test_status_pesanan_terverifikasi_dijawab_dari_data(): void
    {
        $varian = ProductVariant::where('sku', 'BOLA-MITRE-01')->firstOrFail();
        $this->post(route('keranjang.tambah'), ['variant_id' => $varian->id, 'qty' => 1]);
        $this->post('/checkout', ['nama' => 'Budi', 'email' => 'budi@contoh.test', 'no_hp' => '081234567890', 'setuju' => '1']);
        $order = Order::latest('id')->firstOrFail();

        $res = $this->mulai('Bagaimana status pesanan saya?', 'LAIN', ['token_pesanan' => $order->access_token]);

        $this->assertStringContainsString($order->kode, $this->isiBalasan($res)[0]);
        $this->assertStringContainsString($order->status->label(), $this->isiBalasan($res)[0]);
    }

    public function test_status_pesanan_tanpa_verifikasi_tidak_membocorkan_data(): void
    {
        $varian = ProductVariant::where('sku', 'BOLA-MITRE-01')->firstOrFail();
        $this->post(route('keranjang.tambah'), ['variant_id' => $varian->id, 'qty' => 1]);
        $this->post('/checkout', ['nama' => 'Budi', 'email' => 'budi@contoh.test', 'no_hp' => '081234567890', 'setuju' => '1']);
        $order = Order::latest('id')->firstOrFail();

        // Kode yang sekadar diketik tidak membuktikan penanya pemesannya (KEP-030).
        $res = $this->mulai('Status pesanan '.$order->kode.' sudah sampai mana?', 'LAIN');

        $this->assertStringNotContainsString($order->status->label(), $this->isiBalasan($res)[0]);
        $this->assertStringContainsString('halaman pesanan', $this->isiBalasan($res)[0]);
    }
}
