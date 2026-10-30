<?php

namespace Tests\Feature;

use App\Enums\EmailStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Customer;
use App\Models\EmailLog;
use App\Models\Order;
use App\Services\KesehatanSistem;
use App\Services\PengirimEmail;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Pengiriman email lewat HTTP API Resend dan webhook statusnya.
 *
 * Status inilah yang membuat admin tahu kapan email gagal sampai dan urusannya
 * harus diambil alih manusia lewat WhatsApp (docs/03), jadi endpoint-nya harus
 * menolak apa pun yang tidak bisa dibuktikan berasal dari Resend.
 */
class EmailResendTest extends TestCase
{
    use RefreshDatabase;

    private const RAHASIA = 'whsec_cmFoYXNpYS11amktdW50dWstd2ViaG9vaw==';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    private function order(): Order
    {
        $c = Customer::create([
            'nama' => 'Budi Santoso', 'email' => 'budi@contoh.test', 'no_hp' => '081234567890',
        ]);

        $order = Order::create([
            'kode' => Order::kodeBaru(),
            'customer_id' => $c->id,
            'status' => OrderStatus::MENUNGGU_PEMBAYARAN,
            'access_token' => Order::tokenBaru(),
            'tipe_pembayaran' => 'LUNAS',
            'kadaluarsa_pada' => now()->addDay(),
        ]);

        $order->payments()->create([
            'jenis' => PaymentType::FULL,
            'metode' => PaymentMethod::TRANSFER,
            'status' => PaymentStatus::MENUNGGU,
            'jumlah' => 285_000,
        ]);

        return $order->load('payments');
    }

    private function kirimWebhook(array $isi, ?int $waktu = null, ?string $rahasia = null): TestResponse
    {
        $body = json_encode($isi);
        $id = 'msg_uji';
        $waktu ??= time();

        $kunci = base64_decode(str_replace('whsec_', '', $rahasia ?? self::RAHASIA));
        $tanda = base64_encode(hash_hmac('sha256', "{$id}.{$waktu}.{$body}", $kunci, true));

        return $this->call('POST', '/webhook/resend', [], [], [], [
            'HTTP_SVIX_ID' => $id,
            'HTTP_SVIX_TIMESTAMP' => (string) $waktu,
            'HTTP_SVIX_SIGNATURE' => 'v1,'.$tanda,
            'CONTENT_TYPE' => 'application/json',
        ], $body);
    }

    private function log(EmailStatus $status = EmailStatus::TERKIRIM): EmailLog
    {
        return EmailLog::create([
            'order_id' => $this->order()->id,
            'ke' => 'budi@contoh.test',
            'template' => 'order-dibuat',
            'subjek' => 'Uji',
            'resend_id' => 'em_abc123',
            'status' => $status,
            'dikirim_pada' => now(),
        ]);
    }

    public function test_email_dikirim_lewat_http_api_dan_id_pesannya_dicatat(): void
    {
        config()->set('mail.default', 'resend');
        config()->set('mail.mailers.resend.key', 're_kunci_uji');

        Http::fake(['api.resend.com/*' => Http::response(['id' => 'em_xyz789'])]);

        $log = app(PengirimEmail::class)->kirim($this->order(), 'order-dibuat');

        // Port 443, bukan SMTP, itulah alasan teknis Resend dipilih (KEP-004).
        Http::assertSent(fn ($r) => $r->url() === 'https://api.resend.com/emails'
            && $r->hasHeader('Authorization', 'Bearer re_kunci_uji'));

        $this->assertSame('em_xyz789', $log->fresh()->resend_id);
    }

    public function test_kegagalan_kirim_dicatat_tanpa_menggagalkan_alur(): void
    {
        config()->set('mail.default', 'resend');
        config()->set('mail.mailers.resend.key', 're_kunci_uji');

        Http::fake(['api.resend.com/*' => Http::response(['message' => 'Domain belum diverifikasi'], 403)]);

        $log = app(PengirimEmail::class)->kirim($this->order(), 'order-dibuat');

        $this->assertSame(EmailStatus::GAGAL, $log->status);
        $this->assertTrue($log->status->perluTindakLanjut());
    }

    public function test_penolakan_resend_saat_dikirim_cron_tetap_tercatat_gagal(): void
    {
        // Seperti di hosting: email masuk antrian dan baru dikirim penjadwal.
        config()->set('queue.default', 'database');
        config()->set('mail.default', 'resend');
        config()->set('mail.mailers.resend.key', 're_kunci_uji');

        Http::fake(['api.resend.com/*' => Http::response(['message' => 'The setiasport.com domain is not verified.'], 403)]);

        $log = app(PengirimEmail::class)->kirim($this->order(), 'order-dibuat');
        $this->assertSame(EmailStatus::TERKIRIM, $log->status);

        Artisan::call('queue:work', ['--stop-when-empty' => true]);

        $log->refresh();
        $this->assertSame(EmailStatus::GAGAL, $log->status);
        $this->assertStringContainsString('domain is not verified', $log->error);
        $this->assertSame(1, app(KesehatanSistem::class)->emailGagal());
    }

    public function test_email_tetap_terkirim_meski_pesanan_belum_punya_tagihan(): void
    {
        config()->set('mail.default', 'resend');
        config()->set('mail.mailers.resend.key', 're_kunci_uji');

        Http::fake(['api.resend.com/*' => Http::response(['id' => 'em_tanpa_tagihan'])]);

        $order = $this->order();
        $order->payments()->delete();

        $log = app(PengirimEmail::class)->kirim($order->fresh(), 'order-dibuat');

        // Email yang gagal dirender adalah email yang tidak pernah terkirim,
        // dan pelanggan tidak akan pernah menerima tautan pesanannya.
        $this->assertSame(EmailStatus::TERKIRIM, $log->status);
        $this->assertSame('em_tanpa_tagihan', $log->fresh()->resend_id);
    }

    public function test_webhook_ditolak_bila_rahasia_belum_dipasang(): void
    {
        config()->set('services.resend.webhook_secret', null);

        $this->kirimWebhook(['type' => 'email.delivered', 'data' => ['email_id' => 'em_abc123']])
            ->assertForbidden();
    }

    public function test_webhook_dengan_tanda_tangan_salah_ditolak(): void
    {
        config()->set('services.resend.webhook_secret', self::RAHASIA);
        $log = $this->log();

        $this->kirimWebhook(
            ['type' => 'email.bounced', 'data' => ['email_id' => 'em_abc123']],
            rahasia: 'whsec_c2FsYWg='
        )->assertForbidden();

        $this->assertSame(EmailStatus::TERKIRIM, $log->fresh()->status);
    }

    public function test_webhook_lama_ditolak_untuk_menutup_celah_pengulangan(): void
    {
        config()->set('services.resend.webhook_secret', self::RAHASIA);
        $log = $this->log();

        $this->kirimWebhook(
            ['type' => 'email.bounced', 'data' => ['email_id' => 'em_abc123']],
            waktu: time() - 3600
        )->assertForbidden();

        $this->assertSame(EmailStatus::TERKIRIM, $log->fresh()->status);
    }

    public function test_status_terkirim_naik_menjadi_sampai_lalu_dibuka(): void
    {
        config()->set('services.resend.webhook_secret', self::RAHASIA);
        $log = $this->log();

        $this->kirimWebhook(['type' => 'email.delivered', 'data' => ['email_id' => 'em_abc123']])->assertOk();
        $this->assertSame(EmailStatus::DELIVERED, $log->fresh()->status);

        $this->kirimWebhook(['type' => 'email.opened', 'data' => ['email_id' => 'em_abc123']])->assertOk();
        $this->assertSame(EmailStatus::OPENED, $log->fresh()->status);
    }

    public function test_peristiwa_yang_datang_terlambat_tidak_memundurkan_status(): void
    {
        config()->set('services.resend.webhook_secret', self::RAHASIA);
        $log = $this->log(EmailStatus::OPENED);

        $this->kirimWebhook(['type' => 'email.delivered', 'data' => ['email_id' => 'em_abc123']])->assertOk();

        $this->assertSame(EmailStatus::OPENED, $log->fresh()->status);
    }

    public function test_bounce_selalu_menang_karena_itu_kabar_terpenting(): void
    {
        config()->set('services.resend.webhook_secret', self::RAHASIA);
        $log = $this->log(EmailStatus::OPENED);

        $this->kirimWebhook(['type' => 'email.bounced', 'data' => ['email_id' => 'em_abc123']])->assertOk();

        $log->refresh();

        $this->assertSame(EmailStatus::BOUNCED, $log->status);
        $this->assertTrue($log->status->perluTindakLanjut());
        $this->assertSame(1, EmailLog::perluTindakLanjut()->count());
    }
}
