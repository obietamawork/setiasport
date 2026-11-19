<?php

namespace Tests\Feature;

use App\Enums\DesignAssetType;
use App\Models\OptionValue;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
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
 * Detail pesanan panel dirombak mengikuti gambar karyawan (KEP-124): kartu
 * kepala dengan deret tahap dan Perbarui progres, data pelanggan, informasi
 * pesanan, detail barang dengan data pemain, file desain, pembayaran,
 * penyesuaian biaya, ringkasan total, dan riwayat aktivitas.
 */
class DetailPesananPanelTest extends TestCase
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

    /** Jersey kustom 6 pcs SD L, satu pemain bernama, DP terverifikasi. */
    private function pesananKustom(bool $denganStok = false): Order
    {
        $produk = Product::where('sku', 'CUST-JRS-ATASAN')->firstOrFail();
        $opsi = OptionValue::whereIn('label', ['Serena', 'Oblong', 'Pendek', 'Tanpa Saku'])
            ->whereHas('option', fn ($q) => $q->where('product_id', $produk->id))
            ->get()->mapWithKeys(fn (OptionValue $v) => [$v->product_option_id => $v->id])->all();

        if ($denganStok) {
            $this->post(route('keranjang.tambah'), [
                'variant_id' => ProductVariant::where('sku', 'BOLA-MITRE-01')->value('id'), 'qty' => 1,
            ]);
        }

        $this->post(route('kustom.simpan', $produk), [
            'opsi' => $opsi, 'desain_sumber' => 'katalog',
            'baris' => [
                ['kategori' => 'SD', 'ukuran' => 'L', 'jumlah' => 1, 'nama_punggung' => 'ANDI', 'nomor' => '10'],
                ['kategori' => 'SD', 'ukuran' => 'L', 'jumlah' => 5],
            ],
        ]);
        $this->post('/checkout', [
            'nama' => 'Budi Santoso', 'email' => 'budi@contoh.test',
            'no_hp' => '081234567890', 'tipe_pembayaran' => 'DP', 'setuju' => '1',
        ]);

        $order = Order::latest('id')->firstOrFail();
        app(AlurPesanan::class)->terimaPembayaran($order->payments->first());

        return $order->fresh(['items.options', 'items.rosters', 'adjustments', 'payments']);
    }

    public function test_kepala_menampilkan_kode_total_tahap_dan_tombol_perbarui_progres(): void
    {
        $order = $this->pesananKustom();

        $this->actingAs($this->owner())->get(route('panel.pesanan.show', $order))
            ->assertOk()
            ->assertSee('Detail pesanan')
            ->assertSee($order->kode)
            ->assertSeeInOrder(['Total pesanan', rupiah($order->total)])
            ->assertSee('Perbarui progres')
            ->assertSeeInOrder(['Menunggu pembayaran', 'Antrian desain', 'Proses desain', 'Persetujuan desain', 'Produksi', 'Siap', 'Selesai']);
    }

    public function test_kartu_pelanggan_dan_informasi_pesanan(): void
    {
        $order = $this->pesananKustom();

        $this->actingAs($this->owner())->get(route('panel.pesanan.show', $order))
            ->assertSeeInOrder(['Data pelanggan', 'Budi Santoso', '081234567890'])
            ->assertSeeInOrder(['Informasi pesanan', 'Jenis pesanan', 'Jumlah', '6 pcs', 'Total']);
    }

    public function test_detail_jersey_dan_data_pemain_dengan_tombol_ubah(): void
    {
        $order = $this->pesananKustom();

        $this->actingAs($this->owner())->get(route('panel.pesanan.show', $order))
            ->assertSee('Detail jersey')
            ->assertSeeInOrder(['Spesifikasi', 'Bahan', 'Serena'])
            ->assertSee('Data pemain (6)')
            ->assertSee('ANDI')
            ->assertSee('Ubah data pemain')
            ->assertSee('Tambah data pemain')
            ->assertSee('Simpan perubahan');
    }

    public function test_penyesuaian_ringkasan_total_dan_riwayat(): void
    {
        $order = $this->pesananKustom();

        $this->actingAs($this->owner())->post(route('panel.pesanan.penyesuaian.simpan', $order), [
            'jenis' => 'tambah', 'alasan' => 'Tambahan desain', 'nominal' => 20_000,
        ]);
        $order = $order->fresh(['items.options', 'items.rosters', 'adjustments', 'payments']);

        $this->actingAs($this->owner())->get(route('panel.pesanan.show', $order))
            ->assertSeeInOrder(['Penyesuaian biaya', 'Jenis penyesuaian', 'Tambah biaya', 'Potongan harga', 'Diskon khusus', 'Tambahan desain', '+'.rupiah(20_000)])
            ->assertSeeInOrder(['Ringkasan total', 'Subtotal pesanan', rupiah($order->subtotal), 'Penyesuaian biaya', 'Total tagihan', rupiah($order->total)])
            ->assertSeeInOrder(['Riwayat aktivitas', 'Pesanan dibuat', 'Penyesuaian biaya: Tambahan desain']);
    }

    public function test_file_desain_menampilkan_mockup_terbaru(): void
    {
        $order = $this->pesananKustom();
        $item = $order->items->first();

        foreach ([1, 2] as $versi) {
            $item->designAssets()->create([
                'tipe' => DesignAssetType::MOCKUP, 'versi' => $versi, 'path' => "mockup/desain-v{$versi}.jpg",
            ]);
        }

        $this->actingAs($this->owner())->get(route('panel.pesanan.show', $order))
            ->assertSeeInOrder(['File desain', 'desain-v2.jpg', 'Lihat file'])
            ->assertSee('Versi sebelumnya (1)');
    }

    public function test_pembayaran_menampilkan_metode_jumlah_dan_status(): void
    {
        $order = $this->pesananKustom();
        $bayar = $order->payments->first();

        $this->actingAs($this->owner())->get(route('panel.pesanan.show', $order))
            ->assertSeeInOrder(['Pembayaran', 'Metode', $bayar->metode->label(), 'Jumlah', rupiah($bayar->jumlah), 'Status', $bayar->status->label()]);
    }

    /** Kartu Pengiriman dengan label tebal seperti gambar klien (KEP-135). */
    public function test_kartu_pengiriman_dengan_label_yang_jelas(): void
    {
        $order = $this->pesananKustom();
        $order->update(['metode_ambil' => 'KIRIM', 'alamat_kirim' => 'Jl. Raya Solo KM 20, Sragen', 'ongkir' => 19_000]);

        $this->actingAs($this->owner())->get(route('panel.pesanan.show', $order))
            ->assertOk()
            ->assertSeeInOrder(['Pengiriman', 'Dikirim ke alamat pelanggan', 'Ongkir:', rupiah(19_000), 'Alamat pengiriman', 'Jl. Raya Solo KM 20, Sragen']);
    }

    public function test_staf_tanpa_izin_kelola_tidak_melihat_perbarui_progres(): void
    {
        $order = $this->pesananKustom();
        $peran = Role::create(['nama' => 'Kasir uji', 'slug' => 'kasir-uji']);
        $peran->permissions()->sync(Permission::whereIn('slug', ['order.lihat'])->pluck('id'));
        $kasir = User::create(['name' => 'Kasir', 'email' => 'kasir@setiasport.test', 'password' => bcrypt('rahasia-panjang')])->fresh();
        $kasir->roles()->attach($peran);

        $this->actingAs($kasir)->get(route('panel.pesanan.show', $order))
            ->assertOk()
            ->assertDontSee('Perbarui progres')
            ->assertDontSee('Ubah data pemain')
            ->assertDontSee('Tambah penyesuaian');
    }

    public function test_pesanan_campuran_tampil_per_barang_dan_pop_up_memilih_barang(): void
    {
        $order = $this->pesananKustom(denganStok: true);

        $this->assertSame(2, $order->items->count());

        $this->actingAs($this->owner())->get(route('panel.pesanan.show', $order))
            ->assertOk()
            ->assertSee('Detail jersey')
            ->assertSee('Detail barang')
            ->assertSee('Bola Futsal Mitre Ultimatch')
            ->assertSee('Pilih barang');
    }
}
