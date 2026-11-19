<?php

namespace Tests\Feature;

use App\Enums\DesignAssetType;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\OptionValue;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * File desain lewat tautan Google Drive atau Dropbox (KEP-139), di samping
 * WhatsApp. Tidak ada berkas yang masuk ke server: yang disimpan hanya
 * tautan https, sebagai berkas desain bertipe unggahan pelanggan.
 */
class TautanDesainTest extends TestCase
{
    use RefreshDatabase;

    private const DRIVE = 'https://drive.google.com/drive/folders/abc123';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);
    }

    private function pesanLewatKonfigurator(string $sumber, ?string $tautan): TestResponse
    {
        $produk = Product::where('sku', 'CUST-JRS-ATASAN')->firstOrFail();
        $opsi = OptionValue::whereIn('label', ['Serena', 'Oblong', 'Pendek', 'Tanpa Saku'])
            ->whereHas('option', fn ($q) => $q->where('product_id', $produk->id))
            ->get()->mapWithKeys(fn (OptionValue $v) => [$v->product_option_id => $v->id])->all();

        return $this->post(route('kustom.simpan', $produk), [
            'opsi' => $opsi, 'desain_sumber' => $sumber, 'tautan_desain' => $tautan,
            'baris' => [['kategori' => 'SD', 'ukuran' => 'L', 'jumlah' => 6]],
        ]);
    }

    private function checkout(): Order
    {
        $this->post('/checkout', [
            'nama' => 'Tim Voli', 'email' => 'voli@contoh.test', 'no_hp' => '081234567890',
            'tipe_pembayaran' => 'DP', 'setuju' => '1',
        ])->assertSessionHasNoErrors();

        return Order::latest('id')->firstOrFail();
    }

    private function barangKirimSendiri(bool $disetujui = false): OrderItem
    {
        $order = Order::create([
            'kode' => Order::kodeBaru(),
            'customer_id' => Customer::create(['nama' => 'Tim Voli', 'email' => Str::random(6).'@contoh.test', 'no_hp' => '081234567890'])->id,
            'status' => OrderStatus::DIPROSES,
            'access_token' => Order::tokenBaru(),
            'tipe_pembayaran' => 'DP',
        ]);

        return $order->items()->create([
            'product_id' => Product::where('sku', 'CUST-JRS-ATASAN')->value('id'),
            'nama_produk' => 'Custom Jersey Atasan', 'fulfillment_type' => 'PREORDER',
            'qty' => 6, 'harga_satuan' => 70_000, 'desain_sumber' => 'sendiri',
            'status_item' => $disetujui ? OrderItemStatus::JAHIT : OrderItemStatus::ANTRIAN_DESAIN,
            'approved_at' => $disetujui ? now() : null,
        ]);
    }

    public function test_tautan_dari_konfigurator_tersimpan_di_pesanan(): void
    {
        $this->pesanLewatKonfigurator('sendiri', self::DRIVE)->assertSessionHasNoErrors();
        $item = $this->checkout()->items()->firstOrFail();

        $aset = $item->designAssets()->where('tipe', DesignAssetType::UPLOAD_PELANGGAN)->firstOrFail();
        $this->assertSame(self::DRIVE, $aset->external_url);
        $this->assertNull($aset->path);
    }

    public function test_tautan_bukan_https_ditolak(): void
    {
        $this->pesanLewatKonfigurator('sendiri', 'javascript:alert(1)')->assertSessionHasErrors('tautan_desain');
        $this->pesanLewatKonfigurator('sendiri', 'http://drive.google.com/x')->assertSessionHasErrors('tautan_desain');
    }

    public function test_tautan_diabaikan_bila_bukan_kirim_desain_sendiri(): void
    {
        $this->pesanLewatKonfigurator('katalog', self::DRIVE)->assertSessionHasNoErrors();
        $item = $this->checkout()->items()->firstOrFail();

        $this->assertSame(0, $item->designAssets()->count());
    }

    public function test_pelanggan_bisa_mengirim_tautan_dari_halaman_pesanan(): void
    {
        $item = $this->barangKirimSendiri();

        $this->get(route('lacak.token', $item->order->access_token))
            ->assertOk()
            ->assertSee('Tautan Google Drive atau Dropbox');

        $this->post(route('lacak.item.tautan-desain', [$item->order->access_token, $item]), ['tautan_desain' => self::DRIVE])
            ->assertSessionHas('sukses');

        $this->assertSame(self::DRIVE, $item->designAssets()->where('tipe', DesignAssetType::UPLOAD_PELANGGAN)->value('external_url'));
        $this->get(route('lacak.token', $item->order->access_token))->assertSee(self::DRIVE);
    }

    public function test_tautan_terkunci_setelah_desain_disetujui(): void
    {
        $item = $this->barangKirimSendiri(disetujui: true);

        $this->post(route('lacak.item.tautan-desain', [$item->order->access_token, $item]), ['tautan_desain' => self::DRIVE])
            ->assertSessionHasErrors();

        $this->assertSame(0, $item->designAssets()->count());
    }

    public function test_admin_melihat_tautan_di_detail_pesanan(): void
    {
        $item = $this->barangKirimSendiri();
        $item->designAssets()->create(['tipe' => DesignAssetType::UPLOAD_PELANGGAN, 'versi' => 1, 'external_url' => self::DRIVE]);

        $this->actingAs(User::where('email', 'owner@setiasport.test')->firstOrFail())
            ->get(route('panel.pesanan.show', $item->order))
            ->assertOk()
            ->assertSeeInOrder(['File desain', 'File dari pelanggan', self::DRIVE]);
    }
}
