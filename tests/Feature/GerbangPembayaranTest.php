<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Services\Pembayaran\GerbangPembayaran;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Gerbang pembayaran online. Yang paling penting diuji bukan jalur suksesnya,
 * melainkan penolakan notifikasi palsu: endpoint ini terbuka di internet tanpa
 * sesi, jadi tanda tangan adalah satu-satunya penjaga.
 */
class GerbangPembayaranTest extends TestCase
{
    use RefreshDatabase;

    private const KUNCI = 'SB-Mid-server-KUNCIUJI';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);
    }

    private function aktifkanMidtrans(): void
    {
        config()->set('pembayaran.gerbang', 'midtrans');
        config()->set('pembayaran.midtrans.server_key', self::KUNCI);
        config()->set('pembayaran.midtrans.produksi', false);

        $this->app->forgetInstance(GerbangPembayaran::class);
    }

    private function buatPesanan(): Order
    {
        $varian = ProductVariant::where('sku', 'BOLA-MITRE-01')->firstOrFail();

        $this->post('/keranjang', ['variant_id' => $varian->id, 'qty' => 2]);
        $this->post('/checkout', [
            'nama' => 'Budi Santoso',
            'email' => 'budi@contoh.test',
            'no_hp' => '081234567890',
            'setuju' => '1',
        ]);

        return Order::latest('id')->firstOrFail();
    }

    private function notifikasi(Payment $payment, string $status, array $tambahan = []): array
    {
        $ref = $payment->provider_ref;
        $kode = '200';
        $jumlah = number_format($payment->jumlah, 2, '.', '');

        return array_merge([
            'order_id' => $ref,
            'status_code' => $kode,
            'gross_amount' => $jumlah,
            'transaction_status' => $status,
            'signature_key' => hash('sha512', $ref.$kode.$jumlah.self::KUNCI),
        ], $tambahan);
    }

    private function siapkanTransaksi(): Payment
    {
        $this->aktifkanMidtrans();

        Http::fake(['*snap/v1/transactions' => Http::response([
            'token' => 'tok-uji',
            'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v3/redirection/tok-uji',
        ])]);

        $order = $this->buatPesanan();

        $this->post(route('lacak.bayar', $order->access_token))
            ->assertRedirect('https://app.sandbox.midtrans.com/snap/v3/redirection/tok-uji');

        return $order->payments()->first();
    }

    public function test_tanpa_kunci_gerbang_tidak_aktif_dan_tombolnya_tidak_muncul(): void
    {
        $order = $this->buatPesanan();

        $this->assertFalse(app(GerbangPembayaran::class)->aktif());

        $this->get(route('lacak.token', $order->access_token))
            ->assertOk()
            ->assertDontSee('Bayar online sekarang');

        $this->post(route('lacak.bayar', $order->access_token))->assertNotFound();
    }

    public function test_kunci_setengah_jadi_tetap_dianggap_nonaktif(): void
    {
        config()->set('pembayaran.gerbang', 'midtrans');
        config()->set('pembayaran.midtrans.server_key', null);
        $this->app->forgetInstance(GerbangPembayaran::class);

        // Tombol yang muncul tapi gagal di tengah jalan lebih buruk daripada
        // tombol yang tidak ada sama sekali.
        $this->assertFalse(app(GerbangPembayaran::class)->aktif());
    }

    public function test_transaksi_menyimpan_nomor_rujukan_yang_unik_per_pembayaran(): void
    {
        $payment = $this->siapkanTransaksi();

        $this->assertSame('midtrans', $payment->provider);
        $this->assertSame($payment->order->kode.'-P'.$payment->id, $payment->provider_ref);
        $this->assertSame('tok-uji', $payment->payload_json['snap_token']);
    }

    public function test_notifikasi_dengan_tanda_tangan_palsu_ditolak(): void
    {
        $payment = $this->siapkanTransaksi();

        $palsu = $this->notifikasi($payment, 'settlement');
        $palsu['signature_key'] = str_repeat('a', 128);

        $this->postJson('/webhook/midtrans', $palsu)->assertForbidden();

        // Tanpa penjagaan ini, siapa saja bisa menandai pesanan orang lain lunas.
        $this->assertSame(PaymentStatus::MENUNGGU, $payment->fresh()->status);
        $this->assertSame(OrderStatus::MENUNGGU_PEMBAYARAN, $payment->order->fresh()->status);
    }

    public function test_notifikasi_tanpa_tanda_tangan_ditolak(): void
    {
        $payment = $this->siapkanTransaksi();

        $this->postJson('/webhook/midtrans', ['order_id' => $payment->provider_ref])
            ->assertForbidden();

        $this->assertSame(PaymentStatus::MENUNGGU, $payment->fresh()->status);
    }

    public function test_settlement_melunasi_pembayaran_dan_memulai_pengerjaan(): void
    {
        $payment = $this->siapkanTransaksi();

        $this->postJson('/webhook/midtrans', $this->notifikasi($payment, 'settlement'))->assertOk();

        $payment->refresh();

        $this->assertSame(PaymentStatus::LUNAS, $payment->status);
        $this->assertSame(OrderStatus::DIPROSES, $payment->order->fresh()->status);
        $this->assertDatabaseHas('email_logs', [
            'order_id' => $payment->order_id, 'template' => 'pembayaran-diterima',
        ]);
    }

    public function test_notifikasi_yang_diulang_tidak_diproses_dua_kali(): void
    {
        $payment = $this->siapkanTransaksi();
        $notif = $this->notifikasi($payment, 'settlement');

        $this->postJson('/webhook/midtrans', $notif)->assertOk();
        $this->postJson('/webhook/midtrans', $notif)->assertOk();

        // Midtrans mengirim ulang sampai dibalas 200; email pun tidak boleh dobel.
        $this->assertSame(1, $payment->order->emailLogs()
            ->where('template', 'pembayaran-diterima')->count());
        $this->assertSame(1, $payment->order->payments()
            ->where('status', PaymentStatus::LUNAS->value)->count());
    }

    public function test_capture_yang_ditandai_berisiko_belum_dianggap_lunas(): void
    {
        $payment = $this->siapkanTransaksi();

        $this->postJson('/webhook/midtrans',
            $this->notifikasi($payment, 'capture', ['fraud_status' => 'challenge']))->assertOk();

        // Kasir yang memutuskan, bukan sistem.
        $this->assertSame(PaymentStatus::MENUNGGU, $payment->fresh()->status);
        $this->assertSame(OrderStatus::MENUNGGU_PEMBAYARAN, $payment->order->fresh()->status);
    }

    public function test_pembayaran_kadaluarsa_ditandai_tanpa_menyentuh_pesanan(): void
    {
        $payment = $this->siapkanTransaksi();

        $this->postJson('/webhook/midtrans', $this->notifikasi($payment, 'expire'))->assertOk();

        $this->assertSame(PaymentStatus::KADALUARSA, $payment->fresh()->status);
        $this->assertSame(OrderStatus::MENUNGGU_PEMBAYARAN, $payment->order->fresh()->status);
    }

    public function test_notifikasi_untuk_pembayaran_tak_dikenal_dibalas_404(): void
    {
        $this->aktifkanMidtrans();

        $ref = 'SS-9999-9999-P999';
        $this->postJson('/webhook/midtrans', [
            'order_id' => $ref,
            'status_code' => '200',
            'gross_amount' => '1000.00',
            'transaction_status' => 'settlement',
            'signature_key' => hash('sha512', $ref.'200'.'1000.00'.self::KUNCI),
        ])->assertNotFound();
    }

    public function test_kegagalan_gateway_tidak_menutup_jalur_transfer_manual(): void
    {
        $this->aktifkanMidtrans();
        Http::fake(['*snap/v1/transactions' => Http::response(['error_messages' => ['Ditolak']], 400)]);

        $order = $this->buatPesanan();

        $this->post(route('lacak.bayar', $order->access_token))->assertSessionHasErrors();

        $this->get(route('lacak.token', $order->access_token))
            ->assertOk()
            ->assertSee('Unggah bukti transfer');
    }
}
