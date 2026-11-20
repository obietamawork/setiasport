<?php

namespace Tests\Feature;

use App\Models\LogBot;
use App\Models\Order;
use App\Models\PercakapanCs;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Services\AsistenAi;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\FaqBotSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Lapis AI chatbot CS lewat OpenRouter (KEP-152): hanya bila tidak ada FAQ
 * yang cocok. Semua panggilan dipalsukan; tes tidak pernah memakai kredit.
 */
class AsistenAiTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://openrouter.ai/api/v1/chat/completions';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class, FaqBotSeeder::class]);
        config(['fitur.cs' => true, 'services.openrouter.key' => 'kunci-tes']);
        Setting::simpan('cs_aktif', '1');
        Setting::simpan('bot_aktif', '1');
        Setting::simpan('ai_aktif', '1');
        $this->withCredentials();
    }

    /** @param  array<string, mixed>  $isi */
    private function palsukan(array $isi, int $status = 200): void
    {
        Http::fake([self::URL => Http::response([
            'id' => 'gen-1',
            'model' => 'openai/gpt-6-luna',
            'choices' => [['message' => ['role' => 'assistant', 'content' => json_encode($isi)]]],
            'usage' => ['prompt_tokens' => 1200, 'completion_tokens' => 60, 'cost' => 0.00008],
        ], $status)]);
    }

    private function mulai(string $isi, array $tambahan = []): TestResponse
    {
        return $this->postJson(route('cs.mulai'), array_merge([
            'nama' => 'Pak Warno', 'kontak' => 'warno@contoh.test', 'topik' => 'KUSTOM', 'isi' => $isi,
        ], $tambahan))->assertCreated();
    }

    private function percakapan(): PercakapanCs
    {
        return PercakapanCs::latest('id')->firstOrFail();
    }

    public function test_pertanyaan_tanpa_faq_dijawab_ai_dan_dicatat_biayanya(): void
    {
        $this->palsukan(['jawaban' => 'Bisa, Kak. Jersey bisa diberi nama tim di dada.', 'jenis' => 'jawab', 'alasan' => '']);

        $res = $this->mulai('Apakah nama tim bisa ditaruh di bagian dada?');

        $res->assertJsonPath('balasan.0.isi', 'Bisa, Kak. Jersey bisa diberi nama tim di dada.');
        $this->assertSame('BOT', $this->percakapan()->ditangani);

        $log = LogBot::latest('id')->firstOrFail();
        $this->assertSame('ai', $log->lapis);
        $this->assertSame('openai/gpt-6-luna', $log->model);
        $this->assertSame(1200, $log->token_masuk);
        $this->assertEqualsWithDelta(0.00008, $log->biaya, 0.0000001);

        Http::assertSent(function (Request $r) {
            $body = $r->data();

            return $r->url() === self::URL
                && $r->hasHeader('Authorization', 'Bearer kunci-tes')
                && $body['models'] === ['openai/gpt-6-luna', 'deepseek/deepseek-v4.1-flash']
                && $body['provider']['data_collection'] === 'deny'
                && $body['response_format']['type'] === 'json_schema'
                && str_contains($body['messages'][0]['content'], 'Berapa minimal pemesanan?')
                && end($body['messages'])['content'] === 'Apakah nama tim bisa ditaruh di bagian dada?';
        });
    }

    public function test_faq_tetap_didahulukan_tanpa_memanggil_ai(): void
    {
        Http::fake();

        $this->mulai('Berapa minimal pesan?');

        Http::assertNothingSent();
        $this->assertSame('faq', LogBot::latest('id')->value('lapis'));
    }

    public function test_ai_ragu_diteruskan_ke_admin_dan_masuk_kurasi(): void
    {
        $this->palsukan(['jawaban' => '', 'jenis' => 'serahkan', 'alasan' => 'Tidak ada di data']);

        $this->mulai('Apakah bisa bordir topi untuk komunitas motor?');

        $this->assertSame('ADMIN', $this->percakapan()->ditangani);
        $this->assertSame('AI ragu', $this->percakapan()->alasan_serah);
        $log = LogBot::latest('id')->firstOrFail();
        $this->assertSame('openai/gpt-6-luna', $log->model);
        $this->assertSame(1, LogBot::belumTerjawab()->count());
    }

    public function test_ai_gagal_diteruskan_ke_admin(): void
    {
        $this->palsukan(['galat' => 'x'], 500);

        $this->mulai('Apakah bisa bordir topi untuk komunitas motor?');

        $this->assertSame('ADMIN', $this->percakapan()->ditangani);
        $this->assertSame('AI tidak tersedia', $this->percakapan()->alasan_serah);
    }

    public function test_jawaban_ai_berisi_nomor_rekening_ditahan(): void
    {
        $this->palsukan(['jawaban' => 'Silakan transfer ke 1234 5678 90 a.n. Setia Sport.', 'jenis' => 'jawab', 'alasan' => '']);

        $res = $this->mulai('Boleh titip uangnya ke siapa, Kak?');

        $this->assertStringNotContainsString('1234', (string) $res->getContent());
        $this->assertSame('ADMIN', $this->percakapan()->ditangani);
        $this->assertSame('Jawaban AI ditahan', $this->percakapan()->alasan_serah);
    }

    /**
     * Temuan uji banding model (KEP-152): jam buka "08.00-16.00" berisi
     * delapan angka dan sempat tertahan seperti nomor rekening.
     */
    public function test_penahan_angka_tidak_menahan_harga_jam_dan_tanggal(): void
    {
        $this->assertTrue(AsistenAi::mencurigakan('Silakan transfer ke 1234 5678 90.'));
        $this->assertTrue(AsistenAi::mencurigakan('Hubungi 0851-6002-5810.'));
        $this->assertFalse(AsistenAi::mencurigakan('Total Rp 1.430.000 untuk 26 pcs.'));
        $this->assertFalse(AsistenAi::mencurigakan('Senin-Sabtu 08.00-16.00, Minggu 08.00-14.00.'));
        $this->assertFalse(AsistenAi::mencurigakan('Buka pukul 08:00 - 16:00, selesai 25 November 2026.'));
    }

    /**
     * Temuan tinjauan keamanan (KEP-152): panggilan yang gagal atau tidak bisa
     * dibaca tetap bisa ditagih penyedia, jadi tetap dihitung ke batas bulanan.
     * Tanpa ini, pertanyaan yang sengaja menggagalkan jawaban melewati batas.
     */
    public function test_panggilan_gagal_tetap_dihitung_ke_batas_bulanan(): void
    {
        Setting::simpan('ai_batas_bulanan', '1');
        Http::fake([self::URL => Http::response(['choices' => [['message' => ['content' => 'bukan json']]]], 200)]);

        $this->mulai('Apakah bisa bordir topi untuk komunitas motor?');
        $this->assertNotNull(LogBot::latest('id')->value('model'));

        $this->mulai('Apakah bisa bordir tas untuk komunitas motor?');
        Http::assertSentCount(1);
    }

    public function test_batas_bulanan_habis_tidak_memanggil_ai(): void
    {
        Http::fake();
        Setting::simpan('ai_batas_bulanan', '1');
        LogBot::create(['pertanyaan' => 'x', 'lapis' => 'ai', 'model' => 'openai/gpt-6-luna']);

        $this->mulai('Apakah bisa bordir topi untuk komunitas motor?');

        Http::assertNothingSent();
        $this->assertSame('ADMIN', $this->percakapan()->ditangani);
    }

    public function test_tanpa_kunci_api_ai_dilewati(): void
    {
        Http::fake();
        config(['services.openrouter.key' => '']);

        $this->mulai('Apakah bisa bordir topi untuk komunitas motor?');

        Http::assertNothingSent();
        $this->assertSame('ADMIN', $this->percakapan()->ditangani);
    }

    public function test_pesanan_terverifikasi_ikut_menjadi_konteks_ai(): void
    {
        $this->palsukan(['jawaban' => 'Baik, Kak.', 'jenis' => 'jawab', 'alasan' => '']);
        $varian = ProductVariant::where('sku', 'BOLA-MITRE-01')->firstOrFail();
        $this->post(route('keranjang.tambah'), ['variant_id' => $varian->id, 'qty' => 1]);
        $this->post('/checkout', ['nama' => 'Budi', 'email' => 'budi@contoh.test', 'no_hp' => '081234567890', 'setuju' => '1']);
        $order = Order::latest('id')->firstOrFail();

        $this->mulai('Bolanya bisa dibungkus kado?', ['token_pesanan' => $order->access_token]);

        Http::assertSent(fn (Request $r) => str_contains($r->data()['messages'][0]['content'], $order->kode));
    }
}
