<?php

namespace Tests\Feature;

use App\Enums\TahapPembatalan;
use App\Mail\EmailPesanan;
use App\Models\Order;
use App\Models\Pembatalan;
use App\Models\Product;
use App\Models\User;
use App\Services\AlurPesanan;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Kartu pengajuan pembatalan seperti gambar klien dan email keputusannya
 * (KEP-128). Ketentuannya tetap dari KEP-041, bukan kalimat di gambar.
 */
class PembatalanEmailTest extends TestCase
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

    /** Pesanan kustom yang DP-nya sudah masuk dan terverifikasi. */
    private function pesananDibayar(): Order
    {
        $jersey = Product::where('sku', 'JRS-FUTSAL-FP')->firstOrFail();

        $this->post(route('keranjang.kustom', $jersey), [
            'qty' => 18,
            'opsi' => $this->opsiLengkap($jersey, 'Milano', 'Panjang', 'Nama & Nomor', 'Setup Desain Kustom'),
        ]);
        $this->post('/checkout', [
            'nama' => 'Budi Santoso', 'email' => 'budi@contoh.test', 'no_hp' => '081234567890',
            'tipe_pembayaran' => 'DP', 'setuju' => '1',
        ]);

        $order = Order::latest('id')->firstOrFail();
        app(AlurPesanan::class)->terimaPembayaran($order->payments->first());

        return $order->fresh(['items.options', 'payments', 'adjustments']);
    }

    private function ajukan(Order $order): Pembatalan
    {
        $this->post(route('lacak.pembatalan', $order->access_token), ['alasan' => 'Turnamennya diundur ke tahun depan.'])
            ->assertSessionHas('sukses');

        return Pembatalan::latest('id')->firstOrFail();
    }

    public function test_kartu_pembatalan_terbuka_dengan_ketentuan_toko(): void
    {
        $order = $this->pesananDibayar();

        $this->get(route('lacak.token', $order->access_token))
            ->assertOk()
            ->assertSeeInOrder([
                'Ajukan pembatalan pesanan',
                TahapPembatalan::AWAL->ketentuan(),
                'Alasan pembatalan',
                'Tulis alasan pembatalan...',
                'Ajukan pembatalan',
                'Pengajuan akan kami tinjau dan diberitahukan melalui email.',
            ])
            ->assertDontSee('Ingin membatalkan pesanan ini?');
    }

    public function test_email_terkirim_saat_pembatalan_disetujui(): void
    {
        $order = $this->pesananDibayar();
        $pembatalan = $this->ajukan($order);

        $this->actingAs($this->owner())
            ->post(route('panel.pesanan.pembatalan.setujui', [$order, $pembatalan]), [
                'refund_jumlah' => $order->sudah_dibayar, 'refund_metode' => 'TRANSFER',
            ])
            ->assertSessionHas('sukses');

        Mail::assertQueued(EmailPesanan::class, fn (EmailPesanan $m) => $m->template === 'pembatalan-diputuskan');

        $html = (new EmailPesanan($order->fresh(), 'pembatalan-diputuskan', 'Subjek'))->render();
        $this->assertStringContainsString('disetujui', $html);
        $this->assertStringContainsString(rupiah($order->sudah_dibayar), $html);
    }

    public function test_email_terkirim_saat_pembatalan_ditolak_berisi_alasannya(): void
    {
        $order = $this->pesananDibayar();
        $pembatalan = $this->ajukan($order);

        $this->actingAs($this->owner())
            ->post(route('panel.pesanan.pembatalan.tolak', [$order, $pembatalan]), [
                'catatan_admin' => 'Kain sudah tercetak dengan nama tim Anda.',
            ])
            ->assertSessionHas('sukses');

        Mail::assertQueued(EmailPesanan::class, fn (EmailPesanan $m) => $m->template === 'pembatalan-diputuskan');

        $html = (new EmailPesanan($order->fresh(), 'pembatalan-diputuskan', 'Subjek'))->render();
        $this->assertStringContainsString('Kain sudah tercetak dengan nama tim Anda.', $html);
        $this->assertStringContainsString('tetap berjalan', $html);
    }

    public function test_pelanggan_tanpa_email_tidak_dikirimi(): void
    {
        $order = $this->pesananDibayar();
        $pembatalan = $this->ajukan($order);
        $order->customer->update(['email' => null]);

        $this->actingAs($this->owner())
            ->post(route('panel.pesanan.pembatalan.tolak', [$order, $pembatalan]), [
                'catatan_admin' => 'Kain sudah tercetak dengan nama tim Anda.',
            ]);

        Mail::assertNotQueued(EmailPesanan::class, fn (EmailPesanan $m) => $m->template === 'pembatalan-diputuskan');
    }
}
