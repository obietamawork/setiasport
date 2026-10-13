<?php

namespace Tests\Feature;

use App\Enums\MetodeAmbil;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Services\Ongkir\Biteship;
use App\Services\Ongkir\PenyediaOngkir;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Pelanggan memilih sendiri kirim atau ambil di toko (KEP-058).
 *
 * Yang paling penting diuji di sini bukan jalan mulusnya, melainkan dua hal
 * yang gampang keliru: pesanan tetap bisa dibuat saat penyedia ongkirnya mati
 * atau sedang error, dan harga ongkir dari formulir tidak pernah dipercaya.
 */
class CheckoutOngkirTest extends TestCase
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

    private function keranjang(int $qty = 1): ProductVariant
    {
        $varian = ProductVariant::where('sku', 'BOLA-MITRE-01')->firstOrFail();

        $this->post(route('keranjang.tambah'), ['variant_id' => $varian->id, 'qty' => $qty]);

        return $varian;
    }

    /** @param array<string, mixed> $tambahan */
    private function kirimCheckout(array $tambahan = [])
    {
        return $this->post('/checkout', array_merge([
            'nama' => 'Budi Santoso',
            'email' => 'budi@contoh.test',
            'no_hp' => '081234567890',
            'setuju' => '1',
        ], $tambahan));
    }

    private function nyalakanPenyedia(): void
    {
        $this->app->instance(PenyediaOngkir::class, new Biteship(
            kunci: 'kunci-uji',
            url: 'https://api.biteship.test',
            areaAsal: 'IDNP6IDNC148IDND874',
            kurir: ['jne', 'jnt'],
        ));

        cache()->flush();
    }

    private function tarifPalsu(int $harga = 22_000): void
    {
        Http::fake(['*/v1/rates/couriers' => Http::response([
            'pricing' => [[
                'courier_code' => 'jne', 'courier_service_code' => 'REG',
                'courier_name' => 'JNE', 'courier_service_name' => 'Reguler',
                'price' => $harga, 'duration' => '2-3 hari',
            ]],
        ])]);
    }

    public function test_bawaannya_diambil_di_toko_dan_tanpa_ongkir(): void
    {
        $this->keranjang();
        $this->kirimCheckout()->assertRedirect();

        $order = Order::latest('id')->firstOrFail();

        $this->assertSame(MetodeAmbil::AMBIL_TOKO, $order->metode_ambil);
        $this->assertSame(0, (int) $order->ongkir);
        $this->assertNull($order->alamat_kirim);
    }

    /**
     * Keadaan toko hari ini: kunci penyedianya belum ada. Pesanan kirim tetap
     * harus jadi, karena ongkir yang dihitung admin memang cara kerja selama ini.
     */
    public function test_penyedia_mati_pesanan_kirim_tetap_jadi_dengan_ongkir_menyusul(): void
    {
        Http::fake();
        $this->keranjang(2);

        $this->kirimCheckout([
            'metode_ambil' => 'KIRIM',
            'alamat_kirim' => 'Jl. Melati 5, Wonogiri',
        ])->assertRedirect();

        $order = Order::latest('id')->firstOrFail();

        $this->assertSame(MetodeAmbil::KIRIM, $order->metode_ambil);
        $this->assertSame('Jl. Melati 5, Wonogiri', $order->alamat_kirim);
        $this->assertSame(0, (int) $order->ongkir);

        Http::assertNothingSent();
    }

    public function test_halaman_checkout_menjelaskan_ongkir_menyusul_saat_penyedia_mati(): void
    {
        $this->keranjang();

        $this->get(route('checkout'))
            ->assertOk()
            ->assertSee('Kirim ke alamat saya')
            ->assertSee('setelah paket ditimbang');
    }

    public function test_minta_dikirim_tanpa_alamat_ditolak(): void
    {
        $this->keranjang();

        $this->kirimCheckout(['metode_ambil' => 'KIRIM'])
            ->assertSessionHasErrors('alamat_kirim');

        $this->assertSame(0, Order::count());
    }

    public function test_ongkir_terpilih_masuk_tagihan(): void
    {
        $this->nyalakanPenyedia();
        $this->tarifPalsu();
        $this->keranjang();

        $this->kirimCheckout([
            'metode_ambil' => 'KIRIM',
            'alamat_kirim' => 'Jl. Melati 5, Wonogiri',
            'area_tujuan' => 'IDNP6IDNC148IDND874',
            'tarif' => 'jne:REG',
        ])->assertRedirect();

        $order = Order::latest('id')->firstOrFail();

        $this->assertSame(22_000, (int) $order->ongkir);
        $this->assertSame('jne', $order->jasa_kirim);
        $this->assertSame('REG', $order->layanan_kirim);
        $this->assertSame('IDNP6IDNC148IDND874', $order->area_tujuan);
        $this->assertSame(285_000 + 22_000, $order->total);
        $this->assertSame(307_000, (int) $order->payments->first()->jumlah);
    }

    /** Harga dari formulir tidak dipakai: yang masuk tarif yang dihitung ulang (KEP-009). */
    public function test_harga_ongkir_kiriman_browser_diabaikan(): void
    {
        $this->nyalakanPenyedia();
        $this->tarifPalsu();
        $this->keranjang();

        $this->kirimCheckout([
            'metode_ambil' => 'KIRIM',
            'alamat_kirim' => 'Jl. Melati 5, Wonogiri',
            'area_tujuan' => 'IDNP6IDNC148IDND874',
            'tarif' => 'jne:REG',
            'ongkir' => 1_000,
        ])->assertRedirect();

        $this->assertSame(22_000, (int) Order::latest('id')->firstOrFail()->ongkir);
    }

    public function test_kurir_yang_tidak_ada_di_daftar_ditolak_bukan_dianggap_gratis(): void
    {
        $this->nyalakanPenyedia();
        $this->tarifPalsu();
        $this->keranjang();

        $this->kirimCheckout([
            'metode_ambil' => 'KIRIM',
            'alamat_kirim' => 'Jl. Melati 5, Wonogiri',
            'area_tujuan' => 'IDNP6IDNC148IDND874',
            'tarif' => 'sicepat:BEST',
        ])->assertSessionHasErrors('tarif');

        $this->assertSame(0, Order::count());
    }

    /** Penyedianya hidup tetapi sedang tidak menjawab: pesanan tetap jadi. */
    public function test_penyedia_error_pesanan_tetap_jadi(): void
    {
        $this->nyalakanPenyedia();
        Http::fake(['*' => Http::response(['error' => 'saldo habis'], 402)]);
        $this->keranjang();

        $this->kirimCheckout([
            'metode_ambil' => 'KIRIM',
            'alamat_kirim' => 'Jl. Melati 5, Wonogiri',
            'area_tujuan' => 'IDNP6IDNC148IDND874',
            'tarif' => 'jne:REG',
        ])->assertRedirect();

        $order = Order::latest('id')->firstOrFail();

        $this->assertSame(MetodeAmbil::KIRIM, $order->metode_ambil);
        $this->assertSame(0, (int) $order->ongkir);
    }

    public function test_belanja_di_atas_batas_bebas_ongkir(): void
    {
        Setting::simpan('gratis_ongkir_minimal', '200000');

        $this->nyalakanPenyedia();
        $this->tarifPalsu();
        $this->keranjang();

        $this->kirimCheckout([
            'metode_ambil' => 'KIRIM',
            'alamat_kirim' => 'Jl. Melati 5, Wonogiri',
            'area_tujuan' => 'IDNP6IDNC148IDND874',
            'tarif' => 'jne:REG',
        ])->assertRedirect();

        $order = Order::latest('id')->firstOrFail();

        $this->assertSame(0, (int) $order->ongkir);
        $this->assertSame(285_000, $order->total);
    }

    /** Berat dijumlahkan di server dari isi keranjang, bukan diterima dari browser. */
    public function test_berat_dihitung_server_bukan_dari_browser(): void
    {
        $this->nyalakanPenyedia();
        $this->tarifPalsu();

        $varian = $this->keranjang(3);
        $satuan = (int) (Product::findOrFail($varian->product_id)->berat_gram
            ?: Setting::ambil('berat_bawaan_gram', 300));

        $this->get('/pengiriman/tarif?area=IDNP6IDNC148IDND874&berat=1')
            ->assertOk()
            ->assertJsonPath('berat', $satuan * 3)
            ->assertJsonPath('tarif.0.kode', 'jne:REG');
    }

    public function test_pencarian_area_menolak_kata_terlalu_pendek(): void
    {
        $this->nyalakanPenyedia();
        Http::fake();

        $this->get('/pengiriman/area?q=wo')->assertOk()->assertJsonPath('area', []);

        Http::assertNothingSent();
    }

    /**
     * Kotak pencarian tujuan ini publik dan tidak perlu login (KEP-058),
     * dibatasi throttle:30,1 di routes/web.php karena tiap panggilan ke
     * Biteship berbiaya. Selama area asal toko belum diisi (jeda wajar
     * antara kunci API dipasang dan staf sempat mencarinya di Pengaturan,
     * KEP-067), kotak ini harus tetap diam sama sekali, bukan ikut
     * memanggil Biteship hanya karena kuncinya sudah ada.
     */
    public function test_pencarian_area_diam_selama_area_asal_toko_belum_diisi(): void
    {
        $this->app->instance(PenyediaOngkir::class, new Biteship(
            kunci: 'kunci-uji',
            url: 'https://api.biteship.test',
            areaAsal: null,
            kurir: ['jne', 'jnt'],
        ));
        cache()->flush();
        Http::fake();

        $this->get('/pengiriman/area?q=wonogiri')->assertOk()->assertJsonPath('area', []);

        Http::assertNothingSent();
    }

    /** Uang muka adalah tanda jadi atas barangnya, bukan atas ongkos kirimnya. */
    public function test_dp_tidak_menghitung_ongkir(): void
    {
        $this->nyalakanPenyedia();
        $this->tarifPalsu(30_000);

        $produk = Product::where('sku', 'CUST-JRS-ATASAN')->firstOrFail();

        $opsi = \App\Models\OptionValue::whereIn('label', ['Serena', 'Oblong', 'Pendek', 'Tanpa Saku'])
            ->whereHas('option', fn ($q) => $q->where('product_id', $produk->id))
            ->get()
            ->mapWithKeys(fn ($v) => [$v->product_option_id => $v->id])
            ->all();

        $this->post(route('kustom.simpan', $produk), [
            'opsi' => $opsi,
            'baris' => [['kategori' => 'DEWASA', 'ukuran' => 'L', 'jumlah' => 6]],
            'desain_sumber' => 'katalog',
        ])->assertRedirect(route('keranjang'));

        $this->kirimCheckout([
            'tipe_pembayaran' => 'DP',
            'metode_ambil' => 'KIRIM',
            'alamat_kirim' => 'Jl. Melati 5, Wonogiri',
            'area_tujuan' => 'IDNP6IDNC148IDND874',
            'tarif' => 'jne:REG',
        ])->assertRedirect();

        $order = Order::latest('id')->firstOrFail();

        $this->assertSame(30_000, (int) $order->ongkir);
        $this->assertSame(
            (int) ceil(($order->subtotal + $order->biaya_layanan) * 50 / 100),
            $order->dpMinimum()
        );
        $this->assertSame($order->dpMinimum(), (int) $order->payments->first()->jumlah);
    }
}
