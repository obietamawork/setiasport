<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Kerangka panel dari revisi klien (KEP-111): sidebar navy gelap dengan
 * kelompok seperti gambar contoh, kotak cari untuk seluruh panel, dan lonceng
 * yang memuat daftar "perlu ditangani".
 *
 * Yang dijaga bukan warnanya, melainkan tiga hal yang kalau salah membuat
 * staf tersesat: menu lama tidak boleh hilang hanya karena tidak ada di
 * gambar, kode yang tepat harus langsung membuka pesanannya, dan tidak ada
 * hasil cari atau isi lonceng yang berujung halaman terlarang.
 */
class KerangkaPanelTest extends TestCase
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

    /** Staf yang hanya memegang izin yang disebut. */
    private function stafDengan(array $izin): User
    {
        $peran = Role::create(['nama' => 'Peran uji', 'slug' => 'uji-'.Str::random(6)]);
        $peran->permissions()->sync(Permission::whereIn('slug', $izin)->pluck('id'));

        $user = User::create([
            'name' => 'Staf Uji', 'email' => Str::random(8).'@setiasport.test', 'password' => bcrypt('rahasia-panjang'),
        ]);
        $user->roles()->attach($peran);

        return $user;
    }

    private function pesanan(): Order
    {
        return Order::create([
            'kode' => Order::kodeBaru(),
            'customer_id' => Customer::create([
                'nama' => 'SSB Garuda', 'email' => 'garuda@contoh.test', 'no_hp' => '081234567890',
            ])->id,
            'status' => OrderStatus::MENUNGGU_PEMBAYARAN,
            'access_token' => Order::tokenBaru(),
            'tipe_pembayaran' => 'LUNAS',
        ]);
    }

    private function menuSamping(User $user): string
    {
        $html = $this->actingAs($user)->get(route('panel.dashboard'))->assertOk()->getContent();

        return Str::between($html, 'aria-label="Navigasi panel"', '</nav>');
    }

    // ---------------------------------------------------------------- menu

    public function test_menu_samping_dikelompokkan_seperti_contoh_klien(): void
    {
        $menu = $this->menuSamping($this->owner());

        $urutan = ['Dashboard', 'Pesanan', 'Antrian kerja', 'Serah terima',
            'Katalog', 'Produk', 'Kategori', 'Bahan', 'Stok',
            'Laporan', 'Penjualan', 'Stok barang', 'Produk terlaris',
            'Lainnya', 'Banner beranda', 'Portofolio', 'Testimoni', 'Pengaturan'];

        $posisi = -1;
        foreach ($urutan as $label) {
            $ketemu = strpos($menu, '>'.$label.'<', $posisi + 1);
            $this->assertNotFalse($ketemu, "Menu \"{$label}\" tidak ada atau salah urutan.");
            $posisi = $ketemu;
        }
    }

    /**
     * CS resmi aktif (KEP-149): Customer Service tepat di bawah Pesanan, lalu
     * menu kerja karyawan, seperti gambar Ruang CS klien.
     */
    public function test_customer_service_di_atas_menu_kerja_karyawan(): void
    {
        config(['fitur.cs' => true]);
        Setting::simpan('cs_aktif', '1');

        $menu = $this->menuSamping($this->owner());

        $posisi = -1;
        foreach (['Dashboard', 'Pesanan', 'Customer Service', 'Antrian kerja', 'Serah terima'] as $label) {
            $ketemu = strpos($menu, '>'.$label.'<', $posisi + 1);
            $this->assertNotFalse($ketemu, "Menu \"{$label}\" tidak ada atau salah urutan.");
            $posisi = $ketemu;
        }
        $this->assertStringNotContainsString('>Chat CS<', $menu);
    }

    public function test_menu_yang_tidak_ada_di_gambar_contoh_tetap_ada(): void
    {
        $menu = $this->menuSamping($this->owner());

        foreach (['Pengguna', 'Pekerja konveksi', 'Kesiapan tayang', 'Riwayat aktivitas'] as $label) {
            $this->assertStringContainsString('>'.$label.'<', $menu);
        }
    }

    public function test_kelompok_laporan_mengikuti_izin(): void
    {
        $menu = $this->menuSamping($this->stafDengan(['order.lihat']));

        $this->assertStringNotContainsString('>Laporan<', $menu);
        $this->assertStringNotContainsString('>Penjualan<', $menu);
    }

    // ---------------------------------------------------------- cari global

    public function test_kode_pesanan_yang_tepat_langsung_membuka_pesanannya(): void
    {
        $order = $this->pesanan();

        $this->actingAs($this->owner())
            ->get(route('panel.cari', ['q' => strtolower($order->kode)]))
            ->assertRedirect(route('panel.pesanan.show', $order));
    }

    public function test_cari_menampilkan_pesanan_pelanggan_dan_produk(): void
    {
        $order = $this->pesanan();
        $produk = Product::firstOrFail();

        $this->actingAs($this->owner())
            ->get(route('panel.cari', ['q' => 'Garuda']))
            ->assertOk()
            ->assertSee($order->kode)
            ->assertSee('081234567890');

        $this->actingAs($this->owner())
            ->get(route('panel.cari', ['q' => $produk->sku]))
            ->assertOk()
            ->assertSee($produk->nama);
    }

    public function test_cari_tidak_menampilkan_produk_kepada_staf_tanpa_izin_katalog(): void
    {
        $produk = Product::firstOrFail();

        $this->actingAs($this->stafDengan(['order.lihat']))
            ->get(route('panel.cari', ['q' => $produk->sku]))
            ->assertOk()
            ->assertDontSee(route('panel.produk.edit', $produk));
    }

    public function test_cari_tanpa_kata_kunci_tidak_galat(): void
    {
        $this->actingAs($this->owner())
            ->get(route('panel.cari'))
            ->assertOk()
            ->assertSee('Ketik kode pesanan');
    }

    // ---------------------------------------------------------------- lonceng

    public function test_lonceng_menyebut_bukti_transfer_yang_menunggu_di_halaman_mana_pun(): void
    {
        $order = $this->pesanan();
        $order->payments()->create([
            'jenis' => 'FULL', 'metode' => 'TRANSFER', 'status' => PaymentStatus::MENUNGGU,
            'jumlah' => 100_000, 'bukti_path' => 'bukti/contoh.jpg',
        ]);

        $html = $this->actingAs($this->owner())->get(route('panel.bantuan'))->assertOk()->getContent();
        $lonceng = Str::between($html, 'data-lonceng', '</details>');

        $this->assertStringContainsString('Bukti transfer menunggu diperiksa', $lonceng);
    }

    public function test_lonceng_tidak_memuat_hal_di_luar_izin(): void
    {
        $order = $this->pesanan();
        $order->payments()->create([
            'jenis' => 'FULL', 'metode' => 'TRANSFER', 'status' => PaymentStatus::MENUNGGU,
            'jumlah' => 100_000, 'bukti_path' => 'bukti/contoh.jpg',
        ]);

        $html = $this->actingAs($this->stafDengan(['produksi.jahit']))
            ->get(route('panel.bantuan'))->assertOk()->getContent();

        $this->assertStringContainsString('data-lonceng', $html);
        $this->assertStringNotContainsString('Bukti transfer menunggu diperiksa', $html);
    }
}
