<?php

namespace Tests\Feature;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\StatusPembatalan;
use App\Enums\TahapPembatalan;
use App\Models\OptionValue;
use App\Models\Order;
use App\Models\Pembatalan;
use App\Models\Product;
use App\Models\User;
use App\Services\AlurPesanan;
use App\Services\KebijakanPembatalan;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Pembatalan pesanan dan pengembalian uangnya (KEP-041).
 *
 * Tiga ketentuan dari klien yang diuji di sini: belum dikerjakan uang kembali
 * utuh, desain sudah dikerjakan dipotong biaya desain, sudah masuk produksi
 * tidak ada pengembalian. Ditambah satu pagar yang sama pentingnya: pesanan
 * tidak boleh batal hanya karena pelanggan menekan tombol.
 */
class PembatalanTest extends TestCase
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

    private function jersey(): Product
    {
        return Product::where('sku', 'JRS-FUTSAL-FP')->firstOrFail();
    }

    private function opsi(string ...$label): array
    {
        return OptionValue::whereIn('label', $label)
            ->whereHas('option', fn ($q) => $q->where('product_id', $this->jersey()->id))
            ->pluck('id')
            ->all();
    }

    /** Pesanan kustom yang DP-nya sudah masuk dan terverifikasi. */
    private function pesananDibayar(): Order
    {
        $this->post(route('keranjang.kustom', $this->jersey()), [
            'qty' => 18,
            'opsi' => $this->opsi('Milano', 'Panjang', 'Nama & Nomor', 'Setup Desain Kustom'),
        ]);

        $this->post('/checkout', [
            'nama' => 'Budi Santoso',
            'email' => 'budi@contoh.test',
            'no_hp' => '081234567890',
            'tipe_pembayaran' => 'DP',
            'setuju' => '1',
        ]);

        $order = Order::latest('id')->firstOrFail();

        app(AlurPesanan::class)->terimaPembayaran($order->payments->first());

        return $order->fresh(['items.options', 'payments', 'adjustments']);
    }

    private function ajukan(Order $order, string $alasan = 'Turnamennya diundur ke tahun depan.'): Pembatalan
    {
        $this->post(route('lacak.pembatalan', $order->access_token), ['alasan' => $alasan])
            ->assertSessionHas('sukses');

        return Pembatalan::latest('id')->firstOrFail();
    }

    public function test_sebelum_dikerjakan_pengembalian_penuh(): void
    {
        $order = $this->pesananDibayar();
        $pembatalan = $this->ajukan($order);

        $this->assertSame(TahapPembatalan::AWAL, $pembatalan->tahap);
        $this->assertSame($order->sudah_dibayar, $pembatalan->refund_usulan);
    }

    /** Ketentuan kedua klien: desain sudah dikerjakan, dipotong biaya desain. */
    public function test_setelah_desain_dikerjakan_dipotong_biaya_desain(): void
    {
        $order = $this->pesananDibayar();
        $item = $order->items->first();
        $item->update(['status_item' => OrderItemStatus::PROSES_DESAIN]);

        $pembatalan = $this->ajukan($order->fresh(['items.options', 'payments', 'adjustments']));

        $this->assertSame(TahapPembatalan::DESAIN, $pembatalan->tahap);
        $this->assertSame(
            $order->sudah_dibayar - 75_000,   // Setup Desain Kustom di katalog
            $pembatalan->refund_usulan
        );
    }

    /** Ketentuan ketiga: kain sudah tercetak dengan nama tim, uang muka hangus. */
    public function test_setelah_masuk_produksi_tidak_ada_pengembalian(): void
    {
        $order = $this->pesananDibayar();
        $order->items->first()->update([
            'status_item' => OrderItemStatus::PRINTING,
            'approved_at' => now(),
        ]);

        $pembatalan = $this->ajukan($order->fresh(['items.options', 'payments', 'adjustments']));

        $this->assertSame(TahapPembatalan::PRODUKSI, $pembatalan->tahap);
        $this->assertSame(0, $pembatalan->refund_usulan);
    }

    /**
     * Pengajuan bukan pembatalan. Kalau pesanan langsung batal saat tombol
     * ditekan, satu klik pelanggan bisa menghentikan kain yang sedang dipotong.
     */
    public function test_pengajuan_belum_membatalkan_pesanan(): void
    {
        $order = $this->pesananDibayar();
        $this->ajukan($order);

        $this->assertNotSame(OrderStatus::DIBATALKAN, $order->fresh()->status);
        $this->assertNotSame(
            OrderItemStatus::DIBATALKAN,
            $order->fresh(['items'])->items->first()->status_item
        );
    }

    public function test_toko_menyetujui_dan_mencatat_pengembaliannya(): void
    {
        $order = $this->pesananDibayar();
        $pembatalan = $this->ajukan($order);

        $this->actingAs($this->owner())
            ->post(route('panel.pesanan.pembatalan.setujui', [$order, $pembatalan]), [
                'refund_jumlah' => 1_000_000,
                'refund_metode' => 'TRANSFER',
                'catatan_admin' => 'Dikembalikan ke rekening pemesan.',
            ])
            ->assertSessionHas('sukses');

        $pembatalan->refresh();

        $this->assertSame(StatusPembatalan::DISETUJUI, $pembatalan->status);
        $this->assertSame(1_000_000, $pembatalan->refund_jumlah);
        $this->assertSame(OrderStatus::DIBATALKAN, $order->fresh()->status);

        // Pelanggan membaca keputusannya di halaman pesanannya sendiri.
        $this->get(route('lacak.token', $order->access_token))
            ->assertOk()
            ->assertSee('Pesanan dibatalkan')
            ->assertSee(rupiah(1_000_000));
    }

    public function test_pengembalian_tidak_boleh_melebihi_yang_sudah_dibayar(): void
    {
        $order = $this->pesananDibayar();
        $pembatalan = $this->ajukan($order);

        $this->actingAs($this->owner())
            ->post(route('panel.pesanan.pembatalan.setujui', [$order, $pembatalan]), [
                'refund_jumlah' => $pembatalan->sudah_dibayar + 500_000,
                'refund_metode' => 'TRANSFER',
            ])
            ->assertSessionHasErrors('refund_jumlah');

        $this->assertSame(StatusPembatalan::DIAJUKAN, $pembatalan->fresh()->status);
        $this->assertNotSame(OrderStatus::DIBATALKAN, $order->fresh()->status);
    }

    public function test_penolakan_membiarkan_pesanan_berjalan(): void
    {
        $order = $this->pesananDibayar();
        $pembatalan = $this->ajukan($order);

        $this->actingAs($this->owner())
            ->post(route('panel.pesanan.pembatalan.tolak', [$order, $pembatalan]), [
                'catatan_admin' => 'Kain sudah dipesan khusus untuk pesanan ini.',
            ])
            ->assertSessionHas('sukses');

        $this->assertSame(StatusPembatalan::DITOLAK, $pembatalan->fresh()->status);
        $this->assertNotSame(OrderStatus::DIBATALKAN, $order->fresh()->status);

        $this->get(route('lacak.token', $order->access_token))
            ->assertSee('Kain sudah dipesan khusus');
    }

    public function test_tidak_bisa_mengajukan_dua_kali_sekaligus(): void
    {
        $order = $this->pesananDibayar();
        $this->ajukan($order);

        $this->post(route('lacak.pembatalan', $order->access_token), [
            'alasan' => 'Sekali lagi mengajukan pembatalan yang sama.',
        ])->assertStatus(422);

        $this->assertSame(1, Pembatalan::count());
    }

    public function test_keputusan_yang_sudah_dibuat_tidak_bisa_diulang(): void
    {
        $order = $this->pesananDibayar();
        $pembatalan = $this->ajukan($order);

        app(KebijakanPembatalan::class)->tolak($pembatalan, 'Sudah dibicarakan lewat telepon.', $this->owner()->id);

        $this->actingAs($this->owner())
            ->post(route('panel.pesanan.pembatalan.setujui', [$order, $pembatalan->fresh()]), [
                'refund_jumlah' => 0,
            ])
            ->assertStatus(422);

        $this->assertSame(StatusPembatalan::DITOLAK, $pembatalan->fresh()->status);
    }

    public function test_pesanan_yang_sudah_dikirim_tidak_bisa_diajukan(): void
    {
        $order = $this->pesananDibayar();
        $order->update(['status' => OrderStatus::DIKIRIM]);

        $this->post(route('lacak.pembatalan', $order->access_token), [
            'alasan' => 'Paketnya ternyata tidak jadi dibutuhkan.',
        ])->assertStatus(422);

        $this->assertSame(0, Pembatalan::count());
    }

    public function test_ketentuan_terbaca_pelanggan_sebelum_menekan_tombol(): void
    {
        $order = $this->pesananDibayar();
        $order->items->first()->update([
            'status_item' => OrderItemStatus::PRINTING,
            'approved_at' => now(),
        ]);

        $this->get(route('lacak.token', $order->access_token))
            ->assertOk()
            ->assertSee('uang muka tidak dapat dikembalikan');
    }
}
