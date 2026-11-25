<?php

namespace Tests\Feature;

use App\Enums\OrderItemStatus;
use App\Enums\PaymentType;
use App\Jobs\KabarkanTagihanDiperbarui;
use App\Mail\EmailPesanan;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\AlurPesanan;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Tagihan yang berubah setelah pesanan dibuat (KEP-158): pelanggan dikabari,
 * kelebihan bayar dicatat saat dikembalikan, potongan tidak melebihi total,
 * dan tagihan tidak bisa ditambah setelah paket berangkat.
 */
class TagihanDiperbaruiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Queue::fake([KabarkanTagihanDiperbarui::class]);
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);
    }

    private function admin(): User
    {
        return User::where('email', 'admin@setiasport.test')->firstOrFail();
    }

    /** Pesanan dua bola yang baru dibuat dan belum dibayar. */
    private function pesananBaru(): Order
    {
        $bola = ProductVariant::where('sku', 'BOLA-MITRE-01')->firstOrFail();
        $this->post('/keranjang', ['variant_id' => $bola->id, 'qty' => 2]);
        $this->post('/checkout', ['nama' => 'Budi', 'email' => 'budi@contoh.test', 'no_hp' => '081234567890', 'setuju' => '1']);

        return Order::latest('id')->firstOrFail();
    }

    /** Pesanan dua bola yang sudah lunas dan siap. */
    private function pesananLunas(): Order
    {
        $order = $this->pesananBaru();
        $alur = app(AlurPesanan::class);
        $alur->terimaPembayaran($order->payments->first(), $this->admin()->id);

        foreach ($order->fresh('items')->items as $item) {
            while ($item->fresh()->status_item !== OrderItemStatus::SIAP) {
                $alur->majukanItem($item->fresh(), $this->admin()->id);
            }
        }

        return $this->segar($order);
    }

    /** Pesanan lunas yang paketnya sudah diserahkan ke kurir. */
    private function pesananBerangkat(): Order
    {
        $order = $this->pesananLunas();
        $this->actingAs($this->admin())->post(route('panel.pesanan.pengiriman.atur', $order), [
            'metode_ambil' => 'KIRIM', 'alamat_kirim' => 'Jl. Mawar 5, Wonogiri',
        ])->assertSessionHas('sukses');
        $this->actingAs($this->admin())->post(route('panel.pesanan.pengiriman.kirim', $order), [
            'jasa_kirim' => 'JNE', 'no_resi' => 'JNE0012345678',
        ])->assertSessionHas('sukses');

        return $this->segar($order);
    }

    private function segar(Order $order): Order
    {
        return $order->fresh(['items.options', 'items.rosters', 'payments', 'adjustments']);
    }

    /** @param  array<string, mixed>  $isian */
    private function penyesuaian(Order $order, array $isian): TestResponse
    {
        return $this->actingAs($this->admin())->post(route('panel.pesanan.penyesuaian.simpan', $order), $isian);
    }

    private function jalankanKabar(Order $order): void
    {
        app()->call([new KabarkanTagihanDiperbarui($order->id), 'handle']);
    }

    // ---- 1. Kabar tagihan diperbarui ----

    public function test_beberapa_penyesuaian_beruntun_hanya_menjadwalkan_satu_kabar_tertunda(): void
    {
        $order = $this->pesananBaru();

        foreach ([5_000, 7_000, 9_000] as $nominal) {
            $this->penyesuaian($order, ['jenis' => 'tambah', 'alasan' => 'Tambahan desain', 'nominal' => $nominal])
                ->assertSessionHas('sukses');
        }

        Queue::assertPushed(KabarkanTagihanDiperbarui::class, 1);
        Queue::assertPushed(KabarkanTagihanDiperbarui::class, fn ($job) => $job->orderId === $order->id && $job->delay !== null);
    }

    public function test_ongkir_yang_diisi_admin_ikut_dikabarkan(): void
    {
        $order = $this->pesananLunas();

        $this->actingAs($this->admin())->post(route('panel.pesanan.pengiriman.atur', $order), [
            'metode_ambil' => 'KIRIM', 'alamat_kirim' => 'Jl. Mawar 5, Wonogiri', 'ongkir' => 15_000,
        ])->assertSessionHas('sukses');

        Queue::assertPushed(KabarkanTagihanDiperbarui::class, fn ($job) => $job->orderId === $order->id);
    }

    public function test_kabar_memuat_rincian_dan_sisa_yang_baru(): void
    {
        $order = $this->pesananLunas();
        $this->penyesuaian($order, ['jenis' => 'tambah', 'alasan' => 'Tambahan desain', 'keterangan' => 'tambah 2 pemain', 'nominal' => 25_000]);

        $this->jalankanKabar($order);

        Mail::assertQueued(EmailPesanan::class, function (EmailPesanan $mail) {
            $isi = $mail->render();

            return $mail->template === 'tagihan-diperbarui'
                && str_contains($isi, 'Tambahan desain: tambah 2 pemain')
                && str_contains($isi, rupiah(25_000));
        });
        $this->assertDatabaseHas('email_logs', ['order_id' => $order->id, 'template' => 'tagihan-diperbarui']);
    }

    public function test_tagihan_yang_kembali_seperti_semula_tidak_dikabarkan(): void
    {
        $order = $this->pesananBaru();
        $this->penyesuaian($order, ['jenis' => 'tambah', 'alasan' => 'Tambahan desain', 'nominal' => 5_000]);
        $baris = $order->adjustments()->firstOrFail();
        $this->actingAs($this->admin())->delete(route('panel.pesanan.penyesuaian.hapus', [$order, $baris]))->assertSessionHas('sukses');

        $this->jalankanKabar($order);

        Mail::assertNotQueued(EmailPesanan::class, fn (EmailPesanan $mail) => $mail->template === 'tagihan-diperbarui');
    }

    // ---- 2. Pengembalian kelebihan bayar ----

    public function test_kelebihan_bayar_dicatat_sebagai_pengembalian(): void
    {
        $order = $this->pesananLunas();
        $this->penyesuaian($order, ['jenis' => 'potong', 'alasan' => 'Perubahan spesifikasi', 'nominal' => 10_000]);
        $this->assertSame(10_000, $this->segar($order)->kelebihan_bayar);

        $this->actingAs($this->admin())->post(route('panel.pesanan.pengembalian.simpan', $order), [
            'jumlah' => 10_000, 'metode' => 'TUNAI', 'catatan' => 'Diberikan di kasir',
        ])->assertSessionHas('sukses');

        $order = $this->segar($order);
        $this->assertSame(0, $order->kelebihan_bayar);
        $this->assertSame(10_000, $order->dikembalikan);
        $this->assertSame($order->total, $order->sudah_dibayar);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'jenis' => PaymentType::PENGEMBALIAN->value, 'jumlah' => 10_000]);
        $this->assertTrue(ActivityLog::where('aksi', 'pengembalian.dicatat')->exists());
        $this->get(route('lacak.token', $order->access_token))->assertSee('Dikembalikan');
    }

    public function test_pengembalian_tidak_boleh_melebihi_kelebihan_bayar(): void
    {
        $order = $this->pesananLunas();
        $this->penyesuaian($order, ['jenis' => 'potong', 'alasan' => 'Perubahan spesifikasi', 'nominal' => 10_000]);

        $this->actingAs($this->admin())->post(route('panel.pesanan.pengembalian.simpan', $order), [
            'jumlah' => 10_001, 'metode' => 'TUNAI',
        ])->assertSessionHasErrors('jumlah');

        $this->assertDatabaseMissing('payments', ['jenis' => PaymentType::PENGEMBALIAN->value]);
    }

    public function test_pesanan_batal_tidak_bisa_dicatati_pengembalian_kelebihan_bayar(): void
    {
        $order = $this->pesananLunas();
        $this->actingAs($this->admin())->post(route('panel.pesanan.batal', $order), [
            'alasan' => 'Pelanggan membatalkan', 'refund_jumlah' => 0,
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->admin())->post(route('panel.pesanan.pengembalian.simpan', $order), [
            'jumlah' => 1_000, 'metode' => 'TUNAI',
        ])->assertSessionHasErrors(['jumlah' => 'Pesanan ini dibatalkan. Pengembalian uangnya diatur lewat keputusan pembatalan.']);

        $this->assertDatabaseMissing('payments', ['jenis' => PaymentType::PENGEMBALIAN->value]);
        $this->assertSame(0, $this->segar($order)->kelebihan_bayar);
    }

    public function test_staf_tanpa_izin_pembayaran_tidak_bisa_mencatat_pengembalian(): void
    {
        $order = $this->pesananLunas();
        $tanpaPeran = User::create(['name' => 'Kasir', 'email' => 'kasir@contoh.test', 'password' => 'rahasia-123', 'aktif' => true]);

        $this->actingAs($tanpaPeran)->post(route('panel.pesanan.pengembalian.simpan', $order), [
            'jumlah' => 1_000, 'metode' => 'TUNAI',
        ])->assertForbidden();
    }

    public function test_pengembalian_masuk_laporan_dikembalikan_bukan_uang_masuk(): void
    {
        $order = $this->pesananLunas();
        $dibayar = $order->sudah_dibayar;
        $this->penyesuaian($order, ['jenis' => 'potong', 'alasan' => 'Perubahan spesifikasi', 'nominal' => 10_000]);
        $this->actingAs($this->admin())->post(route('panel.pesanan.pengembalian.simpan', $order), ['jumlah' => 10_000, 'metode' => 'TRANSFER']);

        $this->actingAs(User::where('email', 'owner@setiasport.test')->firstOrFail())->get(route('panel.laporan'))
            ->assertViewHas('uangMasuk', $dibayar)
            ->assertViewHas('dikembalikan', 10_000);
    }

    public function test_tambahan_setelah_pengembalian_dihitung_dari_uang_bersih(): void
    {
        $order = $this->pesananLunas();
        $this->penyesuaian($order, ['jenis' => 'potong', 'alasan' => 'Perubahan spesifikasi', 'nominal' => 10_000]);
        $this->actingAs($this->admin())->post(route('panel.pesanan.pengembalian.simpan', $order), ['jumlah' => 10_000, 'metode' => 'TUNAI']);

        $this->penyesuaian($order, ['jenis' => 'tambah', 'alasan' => 'Tambahan desain', 'nominal' => 20_000]);

        $this->assertSame(20_000, $this->segar($order)->sisa_tagihan);
    }

    // ---- 3. Potongan tidak melebihi total ----

    public function test_potongan_melebihi_total_pesanan_ditolak(): void
    {
        $order = $this->pesananBaru();
        $total = $this->segar($order)->total;

        $this->penyesuaian($order, ['jenis' => 'potong', 'alasan' => 'Perubahan spesifikasi', 'nominal' => $total + 1])
            ->assertSessionHasErrors(['nominal' => 'Potongan melebihi total pesanan '.rupiah($total).'.']);

        $this->assertSame(0, $order->adjustments()->count());
    }

    // ---- 5. Tidak ada tambahan setelah paket berangkat ----

    public function test_setelah_paket_berangkat_tagihan_tidak_bisa_ditambah_tetapi_potongan_boleh(): void
    {
        $order = $this->pesananBerangkat();

        $this->penyesuaian($order, ['jenis' => 'tambah', 'alasan' => 'Tambahan desain', 'nominal' => 5_000])
            ->assertSessionHasErrors('jenis');
        $this->penyesuaian($order, ['jenis' => 'potong', 'alasan' => 'Lainnya', 'keterangan' => 'Kompensasi kotak penyok', 'nominal' => 5_000])
            ->assertSessionHas('sukses');

        $this->assertSame(-5_000, (int) $order->adjustments()->sum('jumlah'));
    }

    public function test_setelah_paket_berangkat_potongan_tidak_bisa_dihapus(): void
    {
        $order = $this->pesananBerangkat();
        $this->penyesuaian($order, ['jenis' => 'potong', 'alasan' => 'Lainnya', 'keterangan' => 'Kompensasi', 'nominal' => 5_000]);
        $baris = $order->adjustments()->firstOrFail();

        $this->actingAs($this->admin())->delete(route('panel.pesanan.penyesuaian.hapus', [$order, $baris]))
            ->assertSessionHasErrors('penyesuaian');

        $this->assertSame(1, $order->adjustments()->count());
    }

    public function test_setelah_paket_berangkat_isi_pesanan_tidak_bisa_diubah(): void
    {
        $order = $this->pesananBerangkat();
        $item = $order->items->first();

        $this->actingAs($this->admin())->post(route('panel.pesanan.item.jumlah', [$order, $item]), ['qty' => 3])
            ->assertRedirect();

        $this->assertSame('Paketnya sudah berangkat, isi pesanan tidak bisa diubah lagi.', session('errors')?->first());
        $this->assertSame(2, $item->fresh()->qty);
    }
}
