<?php

namespace Tests\Feature;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusLog;
use App\Models\Product;
use App\Models\User;
use App\Services\AlurPesanan;
use App\Support\ProgresStaf;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pop-up Perbarui progres di detail pesanan panel (KEP-124). Daftarnya
 * memuat semua tahap, tetapi yang bisa dipilih hanya langkah yang sah:
 * satu tahap maju (bukan gerbang) atau satu tahap mundur. Alasannya
 * datang dari aturan yang sama dengan penjagaan server.
 */
class PerbaruiProgresTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);
    }

    private function barang(OrderItemStatus $status): OrderItem
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

        return $order->items()->create([
            'product_id' => Product::where('sku', 'CUST-JRS-ATASAN')->value('id'),
            'nama_produk' => 'Custom Jersey Voli', 'fulfillment_type' => 'PREORDER',
            'qty' => 12, 'harga_satuan' => 30_000, 'status_item' => $status,
        ])->fresh();
    }

    /** @return array<string, array{status: string, label: string, keadaan: string, aksi: ?string, alasan: ?string}> */
    private function pilihan(OrderItem $item): array
    {
        return collect(ProgresStaf::pilihan($item, app(AlurPesanan::class)))->keyBy('status')->all();
    }

    public function test_maju_menyimpan_catatan_di_log(): void
    {
        $item = $this->barang(OrderItemStatus::PRINTING);

        $this->actingAs(User::where('email', 'owner@setiasport.test')->firstOrFail())
            ->post(route('panel.pesanan.item.maju', [$item->order, $item]), [
                'dari' => 'PRINTING', 'catatan' => 'Tinta sudah dicek ulang',
            ])
            ->assertSessionHas('sukses');

        $this->assertSame(OrderItemStatus::PRESS, $item->fresh()->status_item);
        $this->assertSame('Tinta sudah dicek ulang', OrderStatusLog::where('order_item_id', $item->id)->where('ke', 'PRESS')->value('catatan'));
    }

    public function test_pilihan_hanya_maju_dan_mundur_satu_tahap(): void
    {
        $pilihan = $this->pilihan($this->barang(OrderItemStatus::JAHIT));

        $this->assertSame('maju', $pilihan['QC']['aksi']);
        $this->assertSame('mundur', $pilihan['POTONG']['aksi']);
        $this->assertSame('kini', $pilihan['JAHIT']['keadaan']);
        $this->assertSame(['POTONG', 'QC'], collect($pilihan)->whereNotNull('aksi')->keys()->sort()->values()->all());
    }

    public function test_gerbang_persetujuan_dan_pembayaran_tidak_bisa_dipilih(): void
    {
        $persetujuan = $this->pilihan($this->barang(OrderItemStatus::MENUNGGU_PERSETUJUAN));
        $this->assertNull($persetujuan['ANTRIAN_PRODUKSI']['aksi']);
        $this->assertStringContainsString('pelanggan', $persetujuan['ANTRIAN_PRODUKSI']['alasan']);
        $this->assertTrue(collect($persetujuan)->every(fn ($p) => $p['aksi'] === null));

        $bayar = $this->pilihan($this->barang(OrderItemStatus::MENUNGGU_PEMBAYARAN));
        $this->assertNull($bayar['ANTRIAN_DESAIN']['aksi']);
        $this->assertStringContainsString('pembayaran', $bayar['ANTRIAN_DESAIN']['alasan']);
    }

    public function test_proses_desain_tanpa_mockup_tidak_bisa_maju(): void
    {
        $pilihan = $this->pilihan($this->barang(OrderItemStatus::PROSES_DESAIN));

        $this->assertNull($pilihan['MENUNGGU_PERSETUJUAN']['aksi']);
        $this->assertStringContainsString('mockup', $pilihan['MENUNGGU_PERSETUJUAN']['alasan']);
    }

    public function test_siap_tidak_bisa_diselesaikan_dari_pop_up(): void
    {
        $pilihan = $this->pilihan($this->barang(OrderItemStatus::SIAP));

        $this->assertNull($pilihan['SELESAI']['aksi']);
        $this->assertStringContainsString('serah terima', $pilihan['SELESAI']['alasan']);
        $this->assertSame('mundur', $pilihan['QC']['aksi']);
    }

    public function test_fase_staf_produksi_menyebut_subtahap(): void
    {
        $fase = collect(ProgresStaf::fase($this->barang(OrderItemStatus::JAHIT)))->keyBy('label');

        $this->assertSame('lewat', $fase['Persetujuan desain']['keadaan']);
        $this->assertSame('kini', $fase['Produksi']['keadaan']);
        $this->assertSame('Jahit', $fase['Produksi']['keterangan']);
        $this->assertSame('nanti', $fase['Siap']['keadaan']);
    }
}
