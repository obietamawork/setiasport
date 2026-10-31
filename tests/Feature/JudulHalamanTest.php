<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\TemplateBalasan;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Judul halaman, pratinjau tautan, dan larangan indeks (KEP-080).
 *
 * Yang dijaga: setiap tab bisa dibedakan dari tab lain yang sering terbuka
 * bersamaan, lembar cetak tersimpan sebagai PDF dengan nama yang bisa dicari
 * lagi, tautan produk yang dibagikan punya pratinjau, dan halaman berisi data
 * pribadi tidak masuk hasil pencarian.
 */
class JudulHalamanTest extends TestCase
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

    private function pesanan(): Order
    {
        $varian = ProductVariant::where('sku', 'BOLA-MITRE-01')->firstOrFail();

        $this->post(route('keranjang.tambah'), ['variant_id' => $varian->id, 'qty' => 1]);
        $this->post('/checkout', [
            'nama' => 'Budi Santoso', 'email' => 'budi@contoh.test',
            'no_hp' => '081234567890', 'setuju' => '1',
        ]);

        return Order::latest('id')->firstOrFail();
    }

    /**
     * Staf sering membuka halaman pesanan di panel berdampingan dengan halaman
     * pelacakannya. Dulu keduanya berjudul "Pesanan SS-... | Setia Sport".
     */
    public function test_tab_panel_tidak_kembar_dengan_tab_etalase(): void
    {
        $order = $this->pesanan();
        $toko = setting('toko_nama');

        $this->get(route('lacak.token', $order->access_token))
            ->assertSee('<title>Pesanan '.$order->kode.' | '.$toko.'</title>', false);

        $this->actingAs($this->owner())
            ->get(route('panel.pesanan.show', $order))
            ->assertSee('<title>Pesanan '.$order->kode.' · Budi Santoso | Panel '.$toko.'</title>', false);

        $produk = Product::where('sku', 'BOLA-MITRE-01')->firstOrFail();

        $this->actingAs($this->owner())
            ->get(route('panel.produk.edit', $produk))
            ->assertSee('<title>Ubah produk: '.e($produk->nama).' | Panel '.$toko.'</title>', false);
    }

    public function test_judul_katalog_membedakan_kategori_pencarian_dan_halaman(): void
    {
        // "Futsal" ada di bawah Bola dan di bawah Jersey: nama anak saja tidak unik.
        $this->get(route('katalog', ['kategori' => 'bola-futsal']))
            ->assertSee('<title>Bola › Futsal |', false);

        $this->get(route('katalog', ['q' => 'jersey']))
            ->assertSee('<title>Hasil pencarian &quot;jersey&quot; |', false);

        $this->get(route('katalog', ['page' => 2]))
            ->assertSee('<title>Katalog produk (halaman 2) |', false);

        // Mengikuti label menu yang diklik pelanggan.
        $this->get(route('katalog', ['jalur' => 'kustom']))
            ->assertSee('<title>Custom jersey |', false);
    }

    /**
     * Tab, judul besar, dan judul yang menempel di HP menyebut hal yang sama
     * (KEP-101). Dulu bilah menempel di HP selalu "Katalog", judul besar
     * kategori anak hanya "Futsal", dan jalur kustom berjudul besar "Pesan
     * kustom" sementara tab dan menunya "Custom jersey".
     */
    public function test_judul_katalog_sama_di_tab_judul_besar_dan_bilah_hp(): void
    {
        $lekat = fn (string $judul) => '<p class="text-sm font-semibold">'.$judul.'</p>';

        $this->get(route('katalog', ['kategori' => 'bola-futsal']))
            ->assertSee('<title>Bola › Futsal |', false)
            ->assertSee($lekat('Bola › Futsal'), false)
            ->assertSee('aria-label="Remah roti"', false)
            ->assertSee('<h1 class="text-3xl font-extrabold sm:text-4xl">Futsal</h1>', false)
            ->assertDontSee($lekat('Katalog'), false);

        $this->get(route('katalog', ['q' => 'jersey']))
            ->assertSee($lekat('Hasil pencarian &quot;jersey&quot;'), false)
            ->assertSee('<h1 class="text-3xl font-extrabold sm:text-4xl">Hasil pencarian &quot;jersey&quot;</h1>', false);

        $this->get(route('katalog', ['jalur' => 'kustom']))
            ->assertSee($lekat('Custom jersey'), false)
            ->assertSee('<h1 class="text-3xl font-extrabold sm:text-4xl">Custom jersey</h1>', false);

        $this->get(route('katalog'))
            ->assertSee('<title>Katalog produk |', false)
            ->assertSee('<h1 class="text-3xl font-extrabold sm:text-4xl">Katalog produk</h1>', false)
            ->assertDontSee('aria-label="Remah roti"', false);
    }

    /** Tab PC yang sempit dulu hanya menampilkan "Perlengkapan olahraga len...". */
    public function test_tab_halaman_depan_diawali_nama_toko(): void
    {
        $this->get(route('beranda'))
            ->assertSee('<title>'.e(setting('toko_nama')).' | '.e(setting('tentang_judul')).'</title>', false);

        // Halaman lain tetap diakhiri nama toko.
        $this->get(route('lacak'))->assertSee('<title>Lacak pesanan | '.e(setting('toko_nama')).'</title>', false);
    }

    public function test_judul_panel_menyebut_yang_sedang_dibuka(): void
    {
        $this->actingAs($this->owner());
        $toko = setting('toko_nama');
        $kategori = Category::where('slug', 'bola-futsal')->firstOrFail();

        $this->get(route('panel.kategori.edit', $kategori))
            ->assertSee('<h1 class="kop-judul">Ubah kategori: Futsal</h1>', false);

        $template = TemplateBalasan::create(['judul' => 'Jam buka', 'isi' => 'Buka setiap hari.']);
        $this->get(route('panel.cs.template.edit', $template))
            ->assertSee('<title>Ubah template: Jam buka | Panel '.$toko.'</title>', false);

        $this->get(route('panel.pesanan.index'))
            ->assertSee('<title>Pesanan | Panel '.$toko.'</title>', false);
        $this->get(route('panel.pesanan.index', ['jalur' => 'kustom', 'status' => 'MENUNGGU_PEMBAYARAN']))
            ->assertSee('<title>Pesanan: Kustom, Menunggu pembayaran | Panel '.$toko.'</title>', false);
        $this->get(route('panel.pesanan.index', ['q' => 'budi']))
            ->assertSee('<title>Pesanan: cari &quot;budi&quot; | Panel '.$toko.'</title>', false);
    }

    /** Judul lembar cetak menjadi nama berkas saat disimpan sebagai PDF. */
    public function test_judul_lembar_cetak_memuat_pelanggan_dan_toko(): void
    {
        $order = $this->pesanan();
        $toko = setting('toko_nama');

        $this->actingAs($this->owner())
            ->get(route('panel.pesanan.invoice', $order))
            ->assertSee('<title>Invoice '.$order->kode.' - Budi Santoso - '.$toko.'</title>', false);

        $this->actingAs($this->owner())
            ->get(route('panel.pesanan.worksheet', $order))
            ->assertSee('<title>Lembar kerja '.$order->kode.' - Budi Santoso - '.$toko.'</title>', false);
    }

    /**
     * Tautan pelacakan memuat nama, alamat, dan isi pesanan pelanggan. Kalau
     * sempat tersebar di tempat umum, ia tetap tidak boleh muncul di Google.
     */
    public function test_halaman_berisi_data_pribadi_tidak_diindeks(): void
    {
        $order = $this->pesanan();
        $noindex = '<meta name="robots" content="noindex">';

        $this->get(route('lacak.token', $order->access_token))->assertSee($noindex, false);
        $this->get(route('keranjang'))->assertSee($noindex, false);

        // Halaman yang memang untuk dicari orang tetap boleh diindeks.
        $this->get(route('katalog'))->assertDontSee($noindex, false);
        $this->get(route('beranda'))->assertDontSee($noindex, false);
    }

    public function test_pratinjau_tautan_produk_memakai_fotonya(): void
    {
        $produk = Product::where('sku', 'BOLA-MITRE-01')->firstOrFail();

        // Belum berfoto: pratinjau jatuh ke logo, bukan kosong.
        $this->get(route('produk', $produk))
            ->assertSee('<meta property="og:title" content="'.e($produk->nama).'">', false)
            ->assertSee('<meta property="og:image" content="'.asset('logo.png').'">', false);

        $produk->images()->create(['external_url' => 'https://cdn.contoh.test/bola.jpg', 'urutan' => 0]);

        $this->get(route('produk', $produk))
            ->assertSee('<meta property="og:image" content="https://cdn.contoh.test/bola.jpg">', false)
            ->assertSee('<meta property="og:type" content="product">', false)
            ->assertSee('<meta name="twitter:card" content="summary_large_image">', false);
    }
}
