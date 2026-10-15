<?php

namespace Tests\Feature;

use App\Models\OptionValue;
use App\Models\Order;
use App\Models\Product;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Alur pemesanan kustom empat langkah (KEP-040): pilih produk, desain & detail,
 * data pemain, cek pesanan.
 */
class AlurPesanKustomTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);
    }

    private function produk(): Product
    {
        return Product::where('sku', 'CUST-JRS-ATASAN')->firstOrFail();
    }

    private function opsi(string ...$label): array
    {
        $produk = $this->produk();

        return OptionValue::whereIn('label', $label)
            ->whereHas('option', fn ($q) => $q->where('product_id', $produk->id))
            ->get()
            ->mapWithKeys(fn (OptionValue $v) => [$v->product_option_id => $v->id])
            ->all();
    }

    public function test_halaman_pemesanan_memuat_empat_langkah(): void
    {
        $this->get(route('kustom.buat', $this->produk()))
            ->assertOk()
            ->assertSeeInOrder(['Pilih Produk', 'Desain &amp; Detail', 'Data Pemain', 'Cek Pesanan'], false)
            ->assertSee('Tempel dari Excel');
    }

    /** Harga per ukuran tidak muat di satu kotak jumlah, jadi halaman produk mengarah ke sini. */
    public function test_halaman_produk_mengarah_ke_alur_pemesanan(): void
    {
        $this->get(route('produk', $this->produk()))
            ->assertOk()
            ->assertSee(route('kustom.buat', $this->produk()), false);
    }

    public function test_pesanan_masuk_keranjang_dengan_harga_per_ukuran(): void
    {
        $this->post(route('kustom.simpan', $this->produk()), [
            'opsi' => $this->opsi('Serena', 'Oblong', 'Pendek', 'Tanpa Saku'),
            'baris' => [
                ['kategori' => 'SD', 'ukuran' => 'L', 'jumlah' => 4, 'nama_punggung' => 'ANDI', 'nomor' => '10'],
                ['kategori' => 'DEWASA', 'ukuran' => 'XL', 'jumlah' => 2],
            ],
            'desain_sumber' => 'katalog',
        ])->assertRedirect(route('keranjang'));

        $this->get(route('keranjang'))
            ->assertOk()
            ->assertSee('Custom Jersey Atasan')
            // 4 x 45.000 SD + 2 x 60.000 dewasa
            ->assertSee(rupiah(300_000));
    }

    public function test_kurang_dari_minimum_ditolak_dengan_penjelasan(): void
    {
        $this->from(route('kustom.buat', $this->produk()))
            ->post(route('kustom.simpan', $this->produk()), [
                'opsi' => $this->opsi('Serena', 'Oblong', 'Pendek', 'Tanpa Saku'),
                'baris' => [['kategori' => 'SD', 'ukuran' => 'L', 'jumlah' => 3]],
                'desain_sumber' => 'katalog',
            ])
            ->assertRedirect(route('kustom.buat', $this->produk()))
            ->assertSessionHasErrors('baris');

        $this->assertTrue(app(\App\Services\Keranjang::class)->kosong());
    }

    public function test_dari_pemesanan_sampai_pesanan_jadi(): void
    {
        $this->post(route('kustom.simpan', $this->produk()), [
            'opsi' => $this->opsi('Milano', 'Pakai Kerah', 'Panjang', 'Tanpa Saku'),
            'baris' => [
                ['kategori' => 'SMP', 'ukuran' => 'M', 'jumlah' => 6, 'nama_punggung' => 'BUDI', 'nomor' => '7'],
            ],
            'desain_sumber' => 'admin',
            'catatan_desain' => 'Warna dasar navy, logo tim di dada kiri.',
        ])->assertRedirect(route('keranjang'));

        $this->post('/checkout', [
            'nama' => 'Budi Santoso',
            'email' => 'budi@contoh.test',
            'no_hp' => '081234567890',
            'tipe_pembayaran' => 'DP',
            'setuju' => '1',
        ])->assertRedirect();

        $order = Order::latest('id')->firstOrFail();
        $item = $order->items->first();

        $this->assertSame(6, $item->qty);

        // Milano SMP 75.000 + kerah 10.000 + lengan panjang 10.000
        $this->assertSame(95_000 * 6, $item->subtotal);
        $this->assertSame(95_000, $item->rosters()->first()->harga_satuan);
        $this->assertStringContainsString('Minta dibuatkan desain', $item->catatan);
        $this->assertStringContainsString('navy', $item->catatan);
    }

    /** Pesanan tetap dibuat di web; WhatsApp hanya jalur bantuan (KEP-040). */
    public function test_tombol_whatsapp_hanya_bantuan(): void
    {
        $html = $this->get(route('kustom.buat', $this->produk()))->getContent();

        $this->assertStringContainsString('Tanya lewat WhatsApp', $html);
        $this->assertStringContainsString('Masukkan ke keranjang', $html);
    }

    private function pesanDenganDesainSumber(string $sumber): Order
    {
        $this->post(route('kustom.simpan', $this->produk()), [
            'opsi' => $this->opsi('Serena', 'Oblong', 'Pendek', 'Tanpa Saku'),
            'baris' => [['kategori' => 'SD', 'ukuran' => 'L', 'jumlah' => 6]],
            'desain_sumber' => $sumber,
        ])->assertRedirect(route('keranjang'));

        $this->post('/checkout', [
            'nama' => 'Budi Santoso',
            'email' => 'budi@contoh.test',
            'no_hp' => '081234567890',
            'tipe_pembayaran' => 'DP',
            'setuju' => '1',
        ])->assertRedirect();

        return Order::latest('id')->firstOrFail();
    }

    /**
     * Pilihan "sumber desain" di langkah 2 (KEP-040) sempat hanya terlipat ke
     * teks bebas lewat KustomController::catatan() dan tidak bisa dibaca balik
     * sebagai data (KEP-068). Kalau kolomnya tidak terisi, halaman pesanan
     * tidak tahu harus mengingatkan pelanggan mengunggah apa pun.
     */
    public function test_desain_sumber_tersimpan_sebagai_kolom_terstruktur(): void
    {
        $order = $this->pesanDenganDesainSumber('sendiri');

        $this->assertSame('sendiri', $order->items->first()->desain_sumber);
    }

    /**
     * Pelanggan yang sudah bilang mau kirim desain sendiri saat memesan tidak
     * perlu lagi menemukan sendiri tautan "Kirim desain sendiri" yang
     * terlipat di ujung halaman (KEP-068): kotaknya langsung terbuka dan
     * diberi pengingat menonjol begitu halaman pesanan dibuka.
     */
    public function test_kotak_unggah_desain_sendiri_terbuka_otomatis(): void
    {
        $order = $this->pesanDenganDesainSumber('sendiri');

        $this->get(route('lacak.token', $order->access_token))
            ->assertOk()
            ->assertSee('Anda memilih mengirim desain sendiri')
            ->assertSeeInOrder(['Anda memilih mengirim desain sendiri', 'Kirim desain sendiri'], false);
    }

    /** Begitu ada yang diunggah, pengingatnya tidak perlu tampil lagi. */
    public function test_pengingat_desain_sendiri_hilang_setelah_pelanggan_mengunggah(): void
    {
        $order = $this->pesanDenganDesainSumber('sendiri');
        $item = $order->items->first();

        $this->post(route('lacak.item.desain', [$order->access_token, $item]), [
            'tautan' => 'https://drive.google.com/file/d/contoh',
        ])->assertSessionHas('sukses');

        $this->get(route('lacak.token', $order->access_token))
            ->assertOk()
            ->assertDontSee('Anda memilih mengirim desain sendiri');
    }

    /** Pelanggan yang memilih katalog atau minta dibantu admin tidak perlu diingatkan mengunggah. */
    public function test_pengingat_desain_sendiri_tidak_tampil_untuk_sumber_lain(): void
    {
        $order = $this->pesanDenganDesainSumber('katalog');

        $this->get(route('lacak.token', $order->access_token))
            ->assertOk()
            ->assertDontSee('Anda memilih mengirim desain sendiri');
    }
}
