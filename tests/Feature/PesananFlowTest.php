<?php

namespace Tests\Feature;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Mail\EmailPesanan;
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
use Tests\TestCase;

/**
 * Perjalanan penuh satu pesanan ready stock: keranjang, checkout, verifikasi
 * pembayaran, produksi, sampai serah terima di kasir.
 */
class PesananFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);
    }

    private function bola(): ProductVariant
    {
        return ProductVariant::where('sku', 'BOLA-MITRE-01')->firstOrFail();
    }

    private function buatPesanan(int $qty = 2): Order
    {
        $this->post('/keranjang', ['variant_id' => $this->bola()->id, 'qty' => $qty])
            ->assertRedirect(route('keranjang'));

        $this->post('/checkout', [
            'nama' => 'Budi Santoso',
            'email' => 'budi@contoh.test',
            'no_hp' => '081234567890',
            'setuju' => '1',
        ])->assertRedirect();

        return Order::latest('id')->firstOrFail();
    }

    private function siapkanPesananSiap(): Order
    {
        $order = $this->buatPesanan();
        $alur = app(AlurPesanan::class);

        $alur->terimaPembayaran($order->payments->first());

        $item = $order->items()->first();
        $alur->majukanItem($item->fresh());
        $alur->majukanItem($item->fresh());

        return $order->fresh();
    }

    public function test_checkout_mengunci_stok_tanpa_mengurangi_fisiknya(): void
    {
        $order = $this->buatPesanan(2);
        $varian = $this->bola()->fresh();

        $this->assertSame(OrderStatus::MENUNGGU_PEMBAYARAN, $order->status);
        $this->assertSame(570_000, $order->load(['items.options', 'adjustments', 'payments'])->total);

        // Barangnya masih di rak, tapi tidak boleh dijual ke orang lain.
        $this->assertSame(12, $varian->stok);
        $this->assertSame(2, $varian->stok_dipesan);
        $this->assertSame(10, $varian->stok_tersedia);

        $this->assertSame(PaymentStatus::MENUNGGU, $order->payments->first()->status);
        $this->assertNotNull($order->kadaluarsa_pada);

        Mail::assertQueued(EmailPesanan::class);
        $this->assertDatabaseHas('email_logs', ['order_id' => $order->id, 'template' => 'order-dibuat']);
    }

    public function test_checkout_menolak_email_kosong(): void
    {
        $this->post('/keranjang', ['variant_id' => $this->bola()->id, 'qty' => 1]);

        $this->post('/checkout', ['nama' => 'Budi', 'no_hp' => '0812', 'setuju' => '1'])
            ->assertSessionHasErrors('email');

        $this->assertSame(0, Order::count());
    }

    public function test_keranjang_menolak_jumlah_melebihi_stok(): void
    {
        $this->post('/keranjang', ['variant_id' => $this->bola()->id, 'qty' => 99])
            ->assertSessionHasErrors();

        $this->assertSame(0, $this->bola()->fresh()->stok_dipesan);
    }

    public function test_verifikasi_pembayaran_memulai_pengerjaan(): void
    {
        $order = $this->buatPesanan();
        $kasir = User::where('email', 'admin@setiasport.test')->firstOrFail();

        $this->actingAs($kasir)
            ->post(route('panel.pesanan.pembayaran.verifikasi', [$order, $order->payments->first()]))
            ->assertSessionHas('sukses');

        $order->refresh()->load('items');

        $this->assertSame(OrderStatus::DIPROSES, $order->status);
        $this->assertSame(OrderItemStatus::DIPROSES, $order->items->first()->status_item);
        $this->assertNull($order->kadaluarsa_pada);
        $this->assertDatabaseHas('email_logs', ['order_id' => $order->id, 'template' => 'pembayaran-diterima']);
    }

    public function test_pesanan_siap_setelah_seluruh_tahap_selesai(): void
    {
        $order = $this->buatPesanan();
        $staf = User::where('email', 'owner@setiasport.test')->firstOrFail();
        $alur = app(AlurPesanan::class);

        $alur->terimaPembayaran($order->payments->first(), $staf->id);

        $item = $order->items()->first();
        $alur->majukanItem($item->fresh(), $staf->id);   // DIPROSES -> PACKING
        $alur->majukanItem($item->fresh(), $staf->id);   // PACKING  -> SIAP

        $order->refresh();

        $this->assertSame(OrderStatus::SIAP_DIAMBIL, $order->status);
        $this->assertNotNull($order->kode_ambil);
        $this->assertNotNull($order->siap_pada);

        // Pekerjaan packing tercatat selesai sebagai akibat perpindahan status,
        // bukan sebagai input terpisah dari operator.
        $this->assertDatabaseHas('production_jobs', [
            'order_item_id' => $item->id, 'tahap' => 'PACKING', 'status' => 'SELESAI',
        ]);

        $this->assertDatabaseHas('email_logs', ['order_id' => $order->id, 'template' => 'pesanan-siap']);
    }

    public function test_barang_baru_keluar_dari_stok_saat_diserahkan(): void
    {
        $order = $this->siapkanPesananSiap();
        $kasir = User::where('email', 'gudang@setiasport.test')->firstOrFail();

        $this->actingAs($kasir)
            ->post(route('panel.pesanan.serah-terima', $order), ['diambil_oleh' => 'Budi Santoso'])
            ->assertRedirect(route('panel.serah-terima'));

        $order->refresh();
        $varian = $this->bola()->fresh();

        $this->assertSame(OrderStatus::SELESAI, $order->status);
        $this->assertSame('Budi Santoso', $order->diambil_oleh);
        $this->assertNotNull($order->diambil_pada);

        // Baru sekarang stok fisiknya berkurang, dan reservasinya ikut dilepas.
        $this->assertSame(10, $varian->stok);
        $this->assertSame(0, $varian->stok_dipesan);
    }

    public function test_pesanan_dengan_sisa_tagihan_tidak_bisa_diserahkan(): void
    {
        $order = $this->siapkanPesananSiap();
        $owner = User::where('email', 'owner@setiasport.test')->firstOrFail();

        // Pelanggan menambah barang setelah pesanan siap; tagihannya ikut naik (KEP-007).
        $this->actingAs($owner)->post(route('panel.pesanan.penyesuaian.simpan', $order), [
            'deskripsi' => 'Tambah 1 bola', 'jumlah' => 285_000,
        ])->assertSessionHas('sukses');

        $order->refresh()->load(['items.options', 'adjustments', 'payments']);
        $this->assertSame(285_000, $order->sisa_tagihan);

        $this->actingAs($owner)
            ->post(route('panel.pesanan.serah-terima', $order), ['diambil_oleh' => 'Budi'])
            ->assertSessionHasErrors();

        $this->assertSame(OrderStatus::SIAP_DIAMBIL, $order->fresh()->status);
    }

    public function test_pembatalan_melepas_reservasi_stok(): void
    {
        $order = $this->buatPesanan(3);
        $this->assertSame(3, $this->bola()->fresh()->stok_dipesan);

        app(AlurPesanan::class)->batalkan($order, 'Dibatalkan pelanggan.');

        $varian = $this->bola()->fresh();

        $this->assertSame(OrderStatus::DIBATALKAN, $order->fresh()->status);
        $this->assertSame(12, $varian->stok);
        $this->assertSame(0, $varian->stok_dipesan);
        $this->assertSame(PaymentStatus::KADALUARSA, $order->payments()->first()->status);
    }

    public function test_halaman_pesanan_terbuka_lewat_tautan_tanpa_akun(): void
    {
        $order = $this->buatPesanan();

        $this->get(route('lacak.token', $order->access_token))
            ->assertOk()
            ->assertSee($order->kode)
            // Tautan sengaja bisa dibagikan ke grup tim, jadi kontak disamarkan (KEP-008).
            ->assertDontSee('081234567890')
            ->assertDontSee('budi@contoh.test');
    }

    public function test_lacak_manual_menolak_kombinasi_yang_tidak_cocok(): void
    {
        $order = $this->buatPesanan();

        $this->post('/lacak', ['kode' => $order->kode, 'email' => 'orang.lain@contoh.test'])
            ->assertSessionHasErrors('kode');

        $this->post('/lacak', ['kode' => $order->kode, 'email' => 'budi@contoh.test'])
            ->assertRedirect(route('lacak.token', $order->access_token));
    }
}
