<?php

namespace Tests\Feature;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Support\RiwayatPesanan;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Riwayat di halaman lacak sebagai garis waktu berikon (KEP-127): judul,
 * satu kalimat, dan waktunya, dari yang terlama. Catatan staf tidak ikut.
 */
class RiwayatLacakTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);
    }

    private function barang(): OrderItem
    {
        $order = Order::create([
            'kode' => Order::kodeBaru(),
            'customer_id' => Customer::create([
                'nama' => 'Tim Voli', 'email' => Str::random(6).'@contoh.test', 'no_hp' => '081234567890',
            ])->id,
            'status' => OrderStatus::DIPROSES,
            'access_token' => Order::tokenBaru(),
            'tipe_pembayaran' => 'DP',
        ]);
        $order->forceFill(['created_at' => now()->subDays(3)])->save();

        return $order->items()->create([
            'product_id' => Product::where('sku', 'CUST-JRS-ATASAN')->value('id'),
            'nama_produk' => 'Custom Jersey Voli', 'fulfillment_type' => 'PREORDER',
            'qty' => 12, 'harga_satuan' => 30_000, 'status_item' => OrderItemStatus::PRESS,
            'approved_at' => now(),
        ]);
    }

    private function log(OrderItem $item, ?string $dari, string $ke, int $jamLalu, ?string $catatan = null): void
    {
        $item->order->statusLogs()->create([
            'order_item_id' => $item->id, 'dari' => $dari, 'ke' => $ke,
            'catatan' => $catatan, 'created_at' => now()->subHours($jamLalu),
        ]);
    }

    /** @return list<array{ikon: string, judul: string, kalimat: string, waktu: Carbon}> */
    private function riwayat(OrderItem $item): array
    {
        return RiwayatPesanan::untuk($item->order->fresh(['items', 'statusLogs.orderItem']));
    }

    public function test_riwayat_dimulai_dari_pesanan_dibuat(): void
    {
        $item = $this->barang();

        $pertama = $this->riwayat($item)[0];

        $this->assertSame('Pesanan berhasil dibuat', $pertama['judul']);
        $this->assertStringContainsString($item->order->kode, $pertama['kalimat']);
    }

    public function test_judul_per_tahap_urut_dari_yang_terlama(): void
    {
        $item = $this->barang();
        $this->log($item, 'MENUNGGU_PERSETUJUAN', 'ANTRIAN_PRODUKSI', 30);
        $this->log($item, 'ANTRIAN_PRODUKSI', 'PRINTING', 20);
        $this->log($item, 'PRINTING', 'PRESS', 2);

        $judul = array_column($this->riwayat($item), 'judul');

        $this->assertSame(['Pesanan berhasil dibuat', 'Desain disetujui', 'Proses printing', 'Proses press / sublim'], $judul);
    }

    public function test_catatan_staf_tidak_tampil_tapi_perubahan_jumlah_tampil(): void
    {
        $item = $this->barang();
        $this->log($item, 'PRINTING', 'PRESS', 2, 'Tinta dicek ulang oleh operator');
        $this->log($item, 'PRESS', 'PRESS', 1, 'Jumlah Custom Jersey Voli diubah dari 12 menjadi 14 pcs.');

        $riwayat = $this->riwayat($item);
        $teks = collect($riwayat)->map(fn ($r) => $r['judul'].' '.$r['kalimat'])->implode(' ');

        $this->assertStringNotContainsString('Tinta dicek ulang', $teks);
        $this->assertSame('Pesanan diperbarui', end($riwayat)['judul']);
        $this->assertStringContainsString('menjadi 14 pcs', end($riwayat)['kalimat']);
    }

    /**
     * Pesanan kembali ke Diproses dari Menunggu Pelunasan saat barang
     * dikerjakan ulang atau ditambah. Itu bukan pembayaran baru.
     */
    public function test_kembali_diproses_dari_pelunasan_tidak_ditulis_sebagai_pembayaran(): void
    {
        $item = $this->barang();
        $item->order->statusLogs()->create(['dari' => 'MENUNGGU_PEMBAYARAN', 'ke' => 'DIPROSES', 'created_at' => now()->subHours(40)]);
        $item->order->statusLogs()->create(['dari' => 'MENUNGGU_PELUNASAN', 'ke' => 'DIPROSES', 'created_at' => now()->subHour()]);

        $judul = array_column($this->riwayat($item), 'judul');

        $this->assertCount(1, array_keys($judul, 'Pembayaran diterima', true));
    }

    public function test_halaman_lacak_menampilkan_garis_waktu(): void
    {
        $item = $this->barang();
        $this->log($item, 'ANTRIAN_PRODUKSI', 'PRINTING', 20);

        $this->get(route('lacak.token', $item->order->access_token))
            ->assertOk()
            ->assertSeeInOrder(['Riwayat', 'Berikut adalah riwayat aktivitas dan pembaruan pesanan Anda.', 'Pesanan berhasil dibuat', 'Proses printing']);
    }
}
