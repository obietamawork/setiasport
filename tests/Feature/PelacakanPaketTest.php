<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Services\Ongkir\Biteship;
use App\Services\Ongkir\PenyediaOngkir;
use App\Services\SinkronPaket;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Posisi paket menurut kurir (KEP-061).
 *
 * Yang dijaga: pesanan tetap jalan saat kurirnya tidak menjawab, data lama tidak
 * dihapus oleh jawaban kosong, dan status "delivered" dari kurir TIDAK menutup
 * pesanan sendiri.
 */
class PelacakanPaketTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);
        cache()->flush();
        config(['ongkir.penyedia' => 'mati']);
    }

    private function nyalakanPenyedia(): void
    {
        $this->app->instance(PenyediaOngkir::class, new Biteship(
            kunci: 'kunci-uji',
            url: 'https://api.biteship.test',
            areaAsal: 'IDNP6IDNC148IDND874',
            kurir: ['jne', 'jnt'],
        ));
    }

    private function pesananDikirim(array $ubah = []): Order
    {
        $order = Order::create(array_merge([
            'kode' => Order::kodeBaru(),
            'customer_id' => Customer::create([
                'nama' => 'SSB Garuda', 'email' => 'garuda@contoh.test', 'no_hp' => '081200000000',
            ])->id,
            'status' => OrderStatus::DIKIRIM,
            'access_token' => Order::tokenBaru(),
            'tipe_pembayaran' => 'LUNAS',
            'metode_ambil' => \App\Enums\MetodeAmbil::KIRIM,
            'alamat_kirim' => 'Jl. Melati 5, Wonogiri',
            'jasa_kirim' => 'JNE',
            'no_resi' => 'JNE0012345678',
            'dikirim_pada' => now()->subDay(),
        ], $ubah));

        $order->items()->create([
            'product_id' => \App\Models\Product::where('sku', 'BOLA-MITRE-01')->value('id'),
            'nama_produk' => 'Bola Futsal', 'fulfillment_type' => 'READY_STOCK',
            'qty' => 1, 'harga_satuan' => 285_000, 'status_item' => 'SELESAI',
        ]);

        return $order->fresh();
    }

    private function jawabanKurir(string $status = 'on_process'): void
    {
        Http::fake(['*/v1/trackings/*' => Http::response([
            'status' => $status,
            'note' => 'Paket tiba di gerai Solo',
            'history' => [
                ['updated_at' => '2026-09-26T09:00:00Z', 'note' => 'Diterima di gerai Wonogiri'],
                ['updated_at' => '2026-09-27T05:00:00Z', 'note' => 'Paket tiba di gerai Solo'],
            ],
        ])]);
    }

    public function test_posisi_paket_disimpan_di_pesanan(): void
    {
        $this->nyalakanPenyedia();
        $this->jawabanKurir();

        $order = $this->pesananDikirim();

        $this->assertSame(1, app(SinkronPaket::class)->segarkanSemua());

        $order->refresh();

        $this->assertSame('on_process', $order->lacak_status);
        $this->assertSame('Paket tiba di gerai Solo', $order->lacak_catatan);
        $this->assertNotNull($order->lacak_pada);

        // Kejadian terbaru di urutan pertama, supaya halaman pelanggan tidak
        // perlu membalik daftarnya sendiri.
        $this->assertSame('Paket tiba di gerai Solo', $order->lacak_riwayat[0]['keterangan']);
    }

    /** Penyedia mati berarti tidak ada panggilan sama sekali, bukan galat. */
    public function test_penyedia_mati_tidak_memanggil_apa_pun(): void
    {
        Http::fake();
        $this->pesananDikirim();

        $this->assertSame(0, app(SinkronPaket::class)->segarkanSemua());

        Http::assertNothingSent();
    }

    /** Jawaban kosong tidak boleh menghapus posisi terakhir yang sudah diketahui. */
    public function test_kurir_tidak_menjawab_data_lama_dibiarkan(): void
    {
        $this->nyalakanPenyedia();

        $order = $this->pesananDikirim([
            'lacak_status' => 'on_process',
            'lacak_catatan' => 'Paket tiba di gerai Solo',
            'lacak_pada' => now()->subHours(3),
        ]);

        Http::fake(['*' => Http::response(['error' => 'saldo habis'], 402)]);

        $this->assertFalse(app(SinkronPaket::class)->segarkan($order));

        $order->refresh();

        $this->assertSame('on_process', $order->lacak_status);
        $this->assertSame('Paket tiba di gerai Solo', $order->lacak_catatan);
    }

    /**
     * Kurir bilang "delivered" bukan penutup pesanan: diterima satpam sekolah
     * dan diterima pemesannya adalah dua hal berbeda.
     */
    public function test_status_terkirim_dari_kurir_tidak_menutup_pesanan(): void
    {
        $this->nyalakanPenyedia();
        $this->jawabanKurir('delivered');

        $order = $this->pesananDikirim();
        app(SinkronPaket::class)->segarkan($order);

        $order->refresh();

        $this->assertTrue($order->paketSampai());
        $this->assertSame(OrderStatus::DIKIRIM, $order->status);
        $this->assertNull($order->diterima_pada);
    }

    /** Nama jasa kirim yang diketik orang toko dicocokkan sendiri ke kode kurir. */
    public function test_nama_jasa_kirim_dicocokkan_ke_kode_kurir(): void
    {
        $this->nyalakanPenyedia();
        $this->jawabanKurir();

        $order = $this->pesananDikirim(['jasa_kirim' => 'J&T Express', 'no_resi' => 'JP1234567890']);

        app(SinkronPaket::class)->segarkan($order);

        Http::assertSent(fn ($permintaan) => str_contains($permintaan->url(), '/couriers/jnt'));
    }

    public function test_pelanggan_melihat_posisi_paketnya(): void
    {
        $order = $this->pesananDikirim([
            'lacak_status' => 'on_process',
            'lacak_catatan' => 'Paket tiba di gerai Solo',
            'lacak_riwayat' => [
                ['waktu' => '2026-09-27T05:00:00Z', 'keterangan' => 'Paket tiba di gerai Solo'],
                ['waktu' => '2026-09-26T09:00:00Z', 'keterangan' => 'Diterima di gerai Wonogiri'],
            ],
            'lacak_pada' => now()->subMinutes(20),
        ]);

        $this->get(route('lacak.token', $order->access_token))
            ->assertOk()
            ->assertSee('Paket tiba di gerai Solo')
            ->assertSee('Diterima di gerai Wonogiri')
            // Nomor resinya tetap ditampilkan: tidak semua orang percaya sistem.
            ->assertSee('JNE0012345678');
    }

    /** Pesanan yang belum berangkat tidak ikut ditanyakan ke kurir. */
    public function test_hanya_pesanan_yang_sudah_berangkat_yang_disegarkan(): void
    {
        $this->nyalakanPenyedia();
        Http::fake();

        $this->pesananDikirim(['status' => OrderStatus::SIAP_DIAMBIL, 'no_resi' => null]);

        $this->assertSame(0, app(SinkronPaket::class)->segarkanSemua());

        Http::assertNothingSent();
    }
}
