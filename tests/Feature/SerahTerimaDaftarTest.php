<?php

namespace Tests\Feature;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Daftar serah terima bertab seperti gambar klien (KEP-130): Siap
 * diserahkan, Sudah diserahkan, Semua. Centang dipakai menyerahkan
 * beberapa pesanan lunas sekaligus ke satu pengambil.
 */
class SerahTerimaDaftarTest extends TestCase
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

    private function pesanan(string $pelanggan, bool $lunas = true, OrderStatus $status = OrderStatus::SIAP_DIAMBIL): Order
    {
        $order = Order::create([
            'kode' => Order::kodeBaru(),
            'customer_id' => Customer::create([
                'nama' => $pelanggan, 'email' => Str::random(6).'@contoh.test', 'no_hp' => '081234567890',
            ])->id,
            'status' => $status,
            'access_token' => Order::tokenBaru(),
            'tipe_pembayaran' => 'DP',
            'kode_ambil' => Order::kodeAmbilBaru(),
            'siap_pada' => now()->subDay(),
        ]);

        $order->items()->create([
            'product_id' => Product::where('sku', 'CUST-JRS-ATASAN')->value('id'),
            'nama_produk' => 'Jersey Atasan', 'fulfillment_type' => 'PREORDER',
            'qty' => 6, 'harga_satuan' => 70_000,
            'status_item' => $status === OrderStatus::SELESAI ? OrderItemStatus::SELESAI : OrderItemStatus::SIAP,
            'approved_at' => now(),
        ]);

        $total = $order->fresh(['items.options', 'items.rosters', 'adjustments'])->total;
        $order->payments()->create([
            'jenis' => 'FULL', 'metode' => 'TUNAI', 'status' => PaymentStatus::LUNAS,
            'jumlah' => $lunas ? $total : (int) ($total / 2), 'dibayar_pada' => now(),
        ]);

        return $order->fresh();
    }

    public function test_tab_siap_menampilkan_pesanan_siap_diambil(): void
    {
        $a = $this->pesanan('Shannia');
        $b = $this->pesanan('Dewi L.', lunas: false);
        $c = $this->pesanan('Tim Lama', status: OrderStatus::SELESAI);

        $this->actingAs($this->owner())
            ->get(route('panel.serah-terima'))
            ->assertOk()
            ->assertSee('Siap diserahkan (2)')
            ->assertSeeInOrder(['No. pesanan', 'Penerima', 'Keterangan', 'Status', 'Aksi'])
            ->assertSee($a->kode)
            ->assertSee($b->kode)
            ->assertSee('Jersey Atasan (6 pcs)')
            ->assertDontSee($c->kode);
    }

    public function test_tab_sudah_menampilkan_yang_diserahkan_beserta_pengambilnya(): void
    {
        $a = $this->pesanan('Shannia');
        $c = $this->pesanan('Tim Lama', status: OrderStatus::SELESAI);
        $c->update(['diambil_oleh' => 'Kapten Tim', 'diambil_pada' => now()]);

        $this->actingAs($this->owner())
            ->get(route('panel.serah-terima', ['tab' => 'sudah']))
            ->assertOk()
            ->assertSee($c->kode)
            ->assertSee('Kapten Tim')
            ->assertDontSee($a->kode);
    }

    public function test_serah_massal_hanya_yang_lunas_dan_menyebut_yang_dilewati(): void
    {
        $a = $this->pesanan('Shannia');
        $b = $this->pesanan('Dewi L.', lunas: false);

        $this->actingAs($this->owner())
            ->post(route('panel.serah-terima.massal'), ['pesanan' => [$a->id, $b->id], 'diambil_oleh' => 'Reseller Solo'])
            ->assertSessionHas('sukses', fn (string $pesan) => str_contains($pesan, '1 pesanan diserahkan') && str_contains($pesan, $b->kode));

        $this->assertSame(OrderStatus::SELESAI, $a->fresh()->status);
        $this->assertSame('Reseller Solo', $a->fresh()->diambil_oleh);
        $this->assertSame(OrderStatus::SIAP_DIAMBIL, $b->fresh()->status);
    }

    /**
     * Angka di menu samping menghitung semua pesanan Siap Diambil, termasuk
     * yang akan dikirim. Tabelnya ikut menampilkannya supaya angkanya sama,
     * dengan aksi ke halaman pesanan: paket tidak lewat meja kasir (KEP-026).
     */
    public function test_pesanan_kirim_ikut_tampil_dengan_aksi_catat_resi(): void
    {
        $kirim = $this->pesanan('Tim Luar Kota');
        $kirim->update(['metode_ambil' => 'KIRIM', 'alamat_kirim' => 'Jl. Melati 5, Solo', 'ongkir' => 20_000]);

        $this->actingAs($this->owner())
            ->get(route('panel.serah-terima'))
            ->assertOk()
            ->assertSee('Siap diserahkan (1)')
            ->assertSee($kirim->kode)
            ->assertSee('Siap dikirim')
            ->assertSee(route('panel.pesanan.show', $kirim).'#pengambilan')
            ->assertSee('Catat resi');
    }

    public function test_serah_massal_wajib_nama_pengambil(): void
    {
        $a = $this->pesanan('Shannia');

        $this->actingAs($this->owner())
            ->post(route('panel.serah-terima.massal'), ['pesanan' => [$a->id]])
            ->assertSessionHasErrors('diambil_oleh');

        $this->assertSame(OrderStatus::SIAP_DIAMBIL, $a->fresh()->status);
    }

    public function test_staf_tanpa_izin_serah_terima_ditolak(): void
    {
        $a = $this->pesanan('Shannia');
        $peran = Role::create(['nama' => 'Kasir uji', 'slug' => 'kasir-uji']);
        $peran->permissions()->sync(Permission::whereIn('slug', ['order.lihat'])->pluck('id'));
        $kasir = User::create(['name' => 'Kasir', 'email' => 'kasir@setiasport.test', 'password' => bcrypt('rahasia-panjang')])->fresh();
        $kasir->roles()->attach($peran);

        $this->actingAs($kasir)
            ->post(route('panel.serah-terima.massal'), ['pesanan' => [$a->id], 'diambil_oleh' => 'Siapa saja'])
            ->assertForbidden();

        $this->assertSame(OrderStatus::SIAP_DIAMBIL, $a->fresh()->status);
    }
}
