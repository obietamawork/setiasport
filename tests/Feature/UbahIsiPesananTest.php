<?php

namespace Tests\Feature;

use App\Models\OptionValue;
use App\Models\Order;
use App\Models\Product;
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
 * Menambah dan mengurangi isi pesanan yang sudah berjalan (KEP-044).
 *
 * Yang dijaga: tagihan, daftar pemain, dan jumlah potong selalu satu angka yang
 * sama. Selama ini penyesuaian biaya hanya mengubah tagihan, sementara lembar
 * kerja tetap menyebut jumlah lama, dan konveksi menjahit sesuai lembar kerja.
 */
class UbahIsiPesananTest extends TestCase
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

    private function opsiKustom(string ...$label): array
    {
        $produk = Product::where('sku', 'CUST-JRS-ATASAN')->firstOrFail();

        return OptionValue::whereIn('label', $label)
            ->whereHas('option', fn ($q) => $q->where('product_id', $produk->id))
            ->get()
            ->mapWithKeys(fn (OptionValue $v) => [$v->product_option_id => $v->id])
            ->all();
    }

    /** Pesanan kustom 6 pcs SD Serena, DP terverifikasi. */
    private function pesananKustom(): Order
    {
        $produk = Product::where('sku', 'CUST-JRS-ATASAN')->firstOrFail();

        $this->post(route('kustom.simpan', $produk), [
            'opsi' => $this->opsiKustom('Serena', 'Oblong', 'Pendek', 'Tanpa Saku'),
            'baris' => [['kategori' => 'SD', 'ukuran' => 'L', 'jumlah' => 6]],
            'desain_sumber' => 'katalog',
        ]);

        $this->post('/checkout', [
            'nama' => 'Budi Santoso', 'email' => 'budi@contoh.test',
            'no_hp' => '081234567890', 'tipe_pembayaran' => 'DP', 'setuju' => '1',
        ]);

        $order = Order::latest('id')->firstOrFail();
        app(AlurPesanan::class)->terimaPembayaran($order->payments->first());

        return $order->fresh(['items.options', 'items.rosters', 'payments', 'adjustments']);
    }

    public function test_menambah_pemain_menaikkan_jumlah_dan_tagihan(): void
    {
        $order = $this->pesananKustom();
        $item = $order->items->first();

        $this->assertSame(6, $item->qty);
        $this->assertSame(270_000, $order->total);

        $this->actingAs($this->owner())
            ->post(route('panel.pesanan.item.jumlah', [$order, $item]), [
                'baris' => [
                    ['kategori' => 'SD', 'ukuran' => 'L', 'jumlah' => 6],
                    ['kategori' => 'SD', 'ukuran' => 'M', 'jumlah' => 2, 'nama_punggung' => 'RIZKY'],
                ],
            ])
            ->assertSessionHas('sukses');

        $order = $order->fresh(['items.options', 'items.rosters', 'payments', 'adjustments']);

        $this->assertSame(8, $order->items->first()->qty);
        $this->assertSame(360_000, $order->total);
        $this->assertSame(2, $order->items->first()->rosters->count());
    }

    /** Harga yang sudah disepakati tidak boleh ikut naik saat katalog naik. */
    public function test_ukuran_lama_tetap_memakai_harga_kesepakatan(): void
    {
        $order = $this->pesananKustom();
        $item = $order->items->first();

        \App\Models\ProductPrice::where('product_id', $item->product_id)->update(['harga_satuan' => 99_000]);

        $this->actingAs($this->owner())
            ->post(route('panel.pesanan.item.jumlah', [$order, $item]), [
                'baris' => [['kategori' => 'SD', 'ukuran' => 'L', 'jumlah' => 8]],
            ])
            ->assertSessionHas('sukses');

        // 8 x 45.000, bukan 8 x 99.000
        $this->assertSame(360_000, $order->fresh(['items.options', 'items.rosters', 'adjustments'])->total);
    }

    /** Ukuran yang benar-benar baru memakai harga katalog hari ini. */
    public function test_ukuran_baru_memakai_harga_katalog(): void
    {
        $order = $this->pesananKustom();
        $item = $order->items->first();

        $this->actingAs($this->owner())
            ->post(route('panel.pesanan.item.jumlah', [$order, $item]), [
                'baris' => [
                    ['kategori' => 'SD', 'ukuran' => 'L', 'jumlah' => 6],
                    ['kategori' => 'DEWASA', 'ukuran' => 'XL', 'jumlah' => 1],
                ],
            ])
            ->assertSessionHas('sukses');

        $order = $order->fresh(['items.options', 'items.rosters', 'adjustments']);

        // 6 x 45.000 SD + 1 x 60.000 dewasa
        $this->assertSame(330_000, $order->total);
    }

    public function test_mengurangi_pesanan_memunculkan_kelebihan_bayar(): void
    {
        $order = $this->pesananKustom();
        $item = $order->items->first();

        // Pelunasan penuh dulu, supaya pengurangan menghasilkan kelebihan bayar.
        $order->payments()->create([
            'jenis' => \App\Enums\PaymentType::PELUNASAN,
            'metode' => \App\Enums\PaymentMethod::TRANSFER,
            'status' => \App\Enums\PaymentStatus::LUNAS,
            'jumlah' => $order->sisa_tagihan,
            'dibayar_pada' => now(),
        ]);

        $this->actingAs($this->owner())
            ->post(route('panel.pesanan.item.jumlah', [$order, $item]), [
                'baris' => [['kategori' => 'SD', 'ukuran' => 'L', 'jumlah' => 4]],
            ])
            ->assertSessionHas('sukses');

        $order = $order->fresh(['items.options', 'items.rosters', 'payments', 'adjustments']);

        $this->assertSame(180_000, $order->total);
        $this->assertSame(270_000, $order->sudah_dibayar);

        $this->actingAs($this->owner())
            ->get(route('panel.pesanan.show', $order))
            ->assertSee('Kelebihan bayar');
    }

    /** Perubahan tagihan tanpa jejak adalah hal pertama yang dipersoalkan orang. */
    public function test_perubahan_tercatat_di_riwayat_pelanggan(): void
    {
        $order = $this->pesananKustom();
        $item = $order->items->first();

        $this->actingAs($this->owner())
            ->post(route('panel.pesanan.item.jumlah', [$order, $item]), [
                'baris' => [['kategori' => 'SD', 'ukuran' => 'L', 'jumlah' => 7]],
            ]);

        $this->get(route('lacak.token', $order->access_token))
            ->assertOk()
            ->assertSee('diubah dari 6 menjadi 7 pcs');
    }

    public function test_jumlah_barang_stok_ikut_menyesuaikan_reservasi(): void
    {
        $varian = ProductVariant::where('sku', 'BOLA-MITRE-01')->firstOrFail();
        $dipesanAwal = $varian->stok_dipesan;

        $this->post(route('keranjang.tambah'), ['variant_id' => $varian->id, 'qty' => 2]);
        $this->post('/checkout', [
            'nama' => 'Budi Santoso', 'email' => 'budi@contoh.test',
            'no_hp' => '081234567890', 'setuju' => '1',
        ]);

        $order = Order::latest('id')->firstOrFail();

        $this->assertSame($dipesanAwal + 2, $varian->fresh()->stok_dipesan);

        $this->actingAs($this->owner())
            ->post(route('panel.pesanan.item.jumlah', [$order, $order->items->first()]), ['qty' => 5])
            ->assertSessionHas('sukses');

        $this->assertSame(5, $order->fresh(['items'])->items->first()->qty);
        $this->assertSame($dipesanAwal + 5, $varian->fresh()->stok_dipesan);
    }

    public function test_menambah_melebihi_stok_ditolak(): void
    {
        $varian = ProductVariant::where('sku', 'BOLA-MITRE-01')->firstOrFail();

        $this->post(route('keranjang.tambah'), ['variant_id' => $varian->id, 'qty' => 1]);
        $this->post('/checkout', [
            'nama' => 'Budi Santoso', 'email' => 'budi@contoh.test',
            'no_hp' => '081234567890', 'setuju' => '1',
        ]);

        $order = Order::latest('id')->firstOrFail();

        $this->actingAs($this->owner())
            ->post(route('panel.pesanan.item.jumlah', [$order, $order->items->first()]), [
                'qty' => $varian->stok + 10,
            ])
            ->assertSessionHasErrors('qty');

        $this->assertSame(1, $order->fresh(['items'])->items->first()->qty);
    }

    public function test_pesanan_yang_sudah_final_tidak_bisa_diubah(): void
    {
        $order = $this->pesananKustom();
        $item = $order->items->first();

        app(AlurPesanan::class)->batalkan($order, 'Uji coba.', $this->owner()->id);

        $this->actingAs($this->owner())
            ->post(route('panel.pesanan.item.jumlah', [$order, $item]), [
                'baris' => [['kategori' => 'SD', 'ukuran' => 'L', 'jumlah' => 10]],
            ])
            ->assertStatus(422);
    }
}
