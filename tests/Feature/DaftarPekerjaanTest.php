<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
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
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Daftar pekerjaan di antrian kerja (KEP-114), permintaan klien "dinggo
 * pengingat pesanan": pekerjaan yang sedang berjalan, diurutkan dari target
 * selesai yang paling dekat, supaya yang hampir telat terlihat lebih dulu.
 */
class DaftarPekerjaanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);
    }

    private function owner(): User
    {
        return User::where('email', 'owner@setiasport.test')->firstOrFail();
    }

    private function kerjaan(string $statusItem, ?string $target, string $pelanggan = 'Tim Uji', string $status = 'DIPROSES'): Order
    {
        $order = Order::create([
            'kode' => Order::kodeBaru(),
            'customer_id' => Customer::create([
                'nama' => $pelanggan, 'email' => Str::random(6).'@contoh.test', 'no_hp' => '0812'.random_int(1000000, 9999999),
            ])->id,
            'status' => $status,
            'access_token' => Order::tokenBaru(),
            'tipe_pembayaran' => 'LUNAS',
            'target_siap_pada' => $target,
        ]);

        $order->items()->create([
            'product_id' => Product::where('sku', 'CUST-JRS-ATASAN')->value('id'),
            'nama_produk' => 'Jersey Voli', 'fulfillment_type' => 'PREORDER',
            'qty' => 12, 'harga_satuan' => 30_000, 'status_item' => $statusItem,
        ]);

        return $order;
    }

    public function test_daftar_diurutkan_dari_target_selesai_terdekat(): void
    {
        $jauh = $this->kerjaan('JAHIT', now()->addDays(6)->toDateString());
        $tanpa = $this->kerjaan('PRINTING', null);
        $dekat = $this->kerjaan('ANTRIAN_DESAIN', now()->addDay()->toDateString());

        $this->actingAs($this->owner())
            ->get(route('panel.antrian.index'))
            ->assertOk()
            ->assertSeeInOrder(['Daftar pekerjaan terbaru', $dekat->kode, $jauh->kode, $tanpa->kode]);
    }

    public function test_pekerjaan_yang_lewat_target_ditandai(): void
    {
        $this->kerjaan('JAHIT', now()->subDays(2)->toDateString());

        $this->actingAs($this->owner())
            ->get(route('panel.antrian.index'))
            ->assertOk()
            ->assertSee('Lewat target');
    }

    public function test_tahap_saat_ini_dan_jumlahnya_tampil(): void
    {
        $this->kerjaan('JAHIT', now()->addDays(3)->toDateString());

        $this->actingAs($this->owner())
            ->get(route('panel.antrian.index'))
            ->assertOk()
            ->assertSeeInOrder(['Daftar pekerjaan terbaru', 'Jersey Voli', 'Jahit', '12 pcs']);
    }

    public function test_pesanan_yang_sudah_selesai_tidak_masuk_daftar(): void
    {
        $selesai = $this->kerjaan('SELESAI', now()->addDay()->toDateString(), status: OrderStatus::SELESAI->value);

        $this->actingAs($this->owner())
            ->get(route('panel.antrian.daftar'))
            ->assertOk()
            ->assertDontSee($selesai->kode);
    }

    /** "daftar" tidak boleh tertangkap rute antrian/{tahap} lalu berakhir 404. */
    public function test_halaman_daftar_lengkap_terbuka(): void
    {
        $a = $this->kerjaan('POTONG', now()->addDays(2)->toDateString());

        $this->actingAs($this->owner())
            ->get(route('panel.antrian.daftar'))
            ->assertOk()
            ->assertSee($a->kode);
    }

    public function test_daftar_hanya_memuat_tahap_yang_dipegang(): void
    {
        $peran = Role::create(['nama' => 'Konveksi uji', 'slug' => 'konveksi-uji']);
        $peran->permissions()->sync(Permission::whereIn('slug', ['produksi.jahit', 'produksi.potong'])->pluck('id'));
        $penjahit = User::create(['name' => 'Penjahit', 'email' => 'penjahit@setiasport.test', 'password' => bcrypt('rahasia-panjang')]);
        $penjahit->roles()->attach($peran);

        $jahit = $this->kerjaan('JAHIT', now()->addDays(2)->toDateString(), 'Klub Jahit');
        $desain = $this->kerjaan('ANTRIAN_DESAIN', now()->addDay()->toDateString(), 'Klub Desain');

        $this->actingAs($penjahit)
            ->get(route('panel.antrian.daftar'))
            ->assertOk()
            ->assertSee($jahit->kode)
            ->assertDontSee($desain->kode);
    }
}
