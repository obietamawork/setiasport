<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Mail\EmailPesanan;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\AlurPesanan;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Audit sisi pelanggan sampai pembayaran (KEP-097).
 */
class AlurPembayaranTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);
    }

    private function owner(): User
    {
        return User::where('email', 'owner@setiasport.test')->firstOrFail();
    }

    /** Pesanan kustom dengan DP, dibuat lewat jalur yang sama dengan pelanggan. */
    private function pesananDp(array $kirim = []): Order
    {
        $produk = Product::where('sku', 'CUST-JRS-ATASAN')->firstOrFail();

        $this->post(route('kustom.simpan', $produk), [
            'opsi' => $this->opsiLengkap($produk),
            'baris' => [['kategori' => 'SD', 'ukuran' => 'L', 'jumlah' => 6]],
            'desain_sumber' => 'katalog',
        ])->assertSessionHasNoErrors();

        $this->post('/checkout', array_merge([
            'nama' => 'Budi Santoso', 'email' => 'budi@contoh.test', 'no_hp' => '081234567890',
            'tipe_pembayaran' => 'DP', 'setuju' => '1',
        ], $kirim))->assertRedirect();

        return Order::latest('id')->firstOrFail();
    }

    private function unggahBukti(Order $order): void
    {
        $this->post(route('lacak.bukti', $order->access_token), [
            'bukti' => UploadedFile::fake()->image('transfer.jpg', 700, 1400),
        ])->assertSessionHas('sukses');
    }

    private function tolak(Order $order, string $alasan = 'Nominal transfer kurang Rp 5.000.')
    {
        $bayar = $order->payments()->where('status', PaymentStatus::MENUNGGU)->firstOrFail();

        return $this->actingAs($this->owner())
            ->post(route('panel.pesanan.pembayaran.tolak', [$order, $bayar]), ['alasan' => $alasan]);
    }

    /**
     * Dulu penolakan hanya menandai gagal: tagihannya hilang dari halaman
     * pesanan, pelanggan tidak tahu sebabnya, dan pesanan hangus saat batas
     * bayar lewat.
     */
    public function test_bukti_yang_ditolak_diganti_tagihan_baru_dengan_alasannya(): void
    {
        $order = $this->pesananDp();
        $this->unggahBukti($order);
        $this->travel(20)->hours();

        $this->tolak($order)->assertSessionHas('sukses');

        $order->refresh()->load('payments');
        $gagal = $order->payments->firstWhere('status', PaymentStatus::GAGAL);
        $baru = $order->payments->firstWhere('status', PaymentStatus::MENUNGGU);

        $this->assertSame('Nominal transfer kurang Rp 5.000.', $gagal->alasan_tolak);
        $this->assertNotNull($baru);
        $this->assertSame($gagal->jumlah, $baru->jumlah);
        $this->assertSame($gagal->jenis, $baru->jenis);

        // Batas bayarnya dimulai ulang: penolakan di jam ke-20 tidak boleh
        // membuat pesanan hangus empat jam kemudian.
        $this->assertTrue($order->kadaluarsa_pada->gt(now()->addHours(23)));

        Mail::assertQueued(EmailPesanan::class, fn (EmailPesanan $m) => $m->template === 'pembayaran-ditolak');

        $this->get(route('lacak.token', $order->access_token))
            ->assertOk()
            ->assertSee('Bukti sebelumnya belum bisa kami terima:')
            ->assertSee('Nominal transfer kurang Rp 5.000.')
            ->assertSee('Kirim bukti');
    }

    public function test_setelah_ditolak_pelanggan_bisa_membayar_ulang_sampai_lunas(): void
    {
        $order = $this->pesananDp();
        $this->unggahBukti($order);
        $this->tolak($order);

        $this->unggahBukti($order);

        $this->get(route('lacak.token', $order->access_token))
            ->assertOk()
            ->assertDontSee('Bukti sebelumnya belum bisa kami terima:');

        $baru = $order->payments()->where('status', PaymentStatus::MENUNGGU)->firstOrFail();
        app(AlurPesanan::class)->terimaPembayaran($baru, $this->owner()->id);

        $this->assertNotSame(OrderStatus::MENUNGGU_PEMBAYARAN, $order->fresh()->status);
    }

    /** Pesanan yang batas bayarnya dimulai ulang tidak ikut dibatalkan penjadwal. */
    public function test_pesanan_yang_buktinya_ditolak_tidak_hangus_di_batas_lama(): void
    {
        $order = $this->pesananDp();
        $this->unggahBukti($order);
        $this->travel(20)->hours();
        $this->tolak($order);
        $this->travel(5)->hours();

        collect(app(Schedule::class)->events())
            ->firstOrFail(fn ($e) => $e->description === 'kadaluarsa-order')
            ->run(app());

        $this->assertSame(OrderStatus::MENUNGGU_PEMBAYARAN, $order->fresh()->status);
    }

    public function test_pembayaran_yang_sudah_lunas_tidak_bisa_ditolak(): void
    {
        $order = $this->pesananDp();
        $bayar = $order->payments()->firstOrFail();
        app(AlurPesanan::class)->terimaPembayaran($bayar, $this->owner()->id);

        $this->actingAs($this->owner())
            ->post(route('panel.pesanan.pembayaran.tolak', [$order, $bayar]), ['alasan' => 'Salah klik'])
            ->assertStatus(422);

        $this->assertSame(PaymentStatus::LUNAS, $bayar->fresh()->status);
    }

    public function test_halaman_pesanan_kedaluwarsa_menjelaskan_sebab_dan_langkah_berikutnya(): void
    {
        $order = $this->pesananDp();
        $order->update(['kadaluarsa_pada' => now()->subMinute()]);

        collect(app(Schedule::class)->events())
            ->firstOrFail(fn ($e) => $e->description === 'kadaluarsa-order')
            ->run(app());

        $this->get(route('lacak.token', $order->access_token))
            ->assertOk()
            ->assertSee('Kedaluwarsa')
            ->assertSee('Pesanan ini dibatalkan otomatis')
            ->assertSee('Pesan lagi')
            ->assertDontSee('Yang perlu dibayar sekarang');
    }

    /** Pesanan yang dikirim tidak pernah lewat toko; tagihan pelunasannya tidak boleh menyuruh datang ke kasir. */
    public function test_pelunasan_pesanan_yang_dikirim_tidak_menyebut_kasir_atau_kode_ambil(): void
    {
        $order = $this->pesananDp(['metode_ambil' => 'KIRIM', 'alamat_kirim' => 'Jl. Melati 5, Jakarta']);
        app(AlurPesanan::class)->terimaPembayaran($order->payments()->firstOrFail(), $this->owner()->id);
        $order->update(['status' => OrderStatus::MENUNGGU_PELUNASAN]);

        $this->get(route('lacak.token', $order->access_token))
            ->assertOk()
            ->assertSee('paketnya kami kirim ke alamat Anda')
            ->assertDontSee('kode ambil terbit')
            ->assertDontSee('di kasir saat datang');

        $email = (new EmailPesanan($order->fresh(), 'menunggu-pelunasan', 'x'))->render();
        $this->assertStringContainsString('sebelum paketnya kami kirim', $email);
        $this->assertStringNotContainsString('kasir saat datang', $email);
    }

    public function test_email_pembayaran_diterima_untuk_pesanan_dikirim_tidak_menyebut_ambil_di_toko(): void
    {
        $order = $this->pesananDp(['metode_ambil' => 'KIRIM', 'alamat_kirim' => 'Jl. Melati 5, Jakarta']);
        app(AlurPesanan::class)->terimaPembayaran($order->payments()->firstOrFail(), $this->owner()->id);

        $email = (new EmailPesanan($order->fresh(), 'pembayaran-diterima', 'x'))->render();

        $this->assertStringContainsString('sebelum paketnya kami kirim', $email);
        $this->assertStringNotContainsString('mengambil', $email);
        $this->assertStringNotContainsString('siap diambil', $email);
    }
}
