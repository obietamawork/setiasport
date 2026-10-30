<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\OptionValue;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Keranjang;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Temuan audit alur pemesanan dan keamanan (KEP-096).
 *
 * Semuanya celah yang hanya terbuka lewat permintaan yang dirakit sendiri:
 * antarmukanya tidak pernah mengirim nilai seperti ini, jadi tes lama yang
 * menirukan antarmuka tidak pernah menemukannya.
 */
class KeamananPemesananTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);
    }

    private function futsal(): Product
    {
        return Product::where('sku', 'JRS-FUTSAL-FP')->firstOrFail();
    }

    private function nilai(Product $produk, string $label): int
    {
        return OptionValue::where('label', $label)
            ->whereHas('option', fn ($q) => $q->where('product_id', $produk->id))
            ->value('id');
    }

    /**
     * Bahan Milano +7.000 dan Hyget −8.000 berasal dari opsi yang sama. Kalau
     * keduanya diterima, harganya −1.000 per potong dan lembar kerjanya
     * memuat dua bahan sekaligus.
     */
    public function test_dua_nilai_dari_satu_opsi_ditolak(): void
    {
        $produk = $this->futsal();
        $opsi = $this->opsiLengkap($produk, 'Milano');
        $opsi[] = $this->nilai($produk, 'Hyget');

        $this->post(route('keranjang.kustom', $produk), ['qty' => 12, 'opsi' => $opsi])
            ->assertSessionHasErrors();

        $this->assertTrue(app(Keranjang::class)->kosong());
    }

    public function test_dua_bahan_juga_ditolak_di_konfigurator_empat_langkah(): void
    {
        $produk = Product::where('sku', 'CUST-JRS-ATASAN')->firstOrFail();
        $opsi = $this->opsiLengkap($produk, 'Serena');
        $opsi[] = $this->nilai($produk, 'Milano');

        $this->post(route('kustom.simpan', $produk), [
            'opsi' => $opsi,
            'baris' => [['kategori' => 'SD', 'ukuran' => 'L', 'jumlah' => 6]],
            'desain_sumber' => 'katalog',
        ])->assertSessionHasErrors(['baris' => 'Pilih satu saja untuk Bahan.']);

        $this->assertTrue(app(Keranjang::class)->kosong());
    }

    /** Lembar kerja tanpa model kerah tidak bisa dikerjakan konveksi. */
    public function test_opsi_wajib_yang_dilewati_ditolak(): void
    {
        $produk = $this->futsal();

        $this->post(route('keranjang.kustom', $produk), [
            'qty' => 12,
            'opsi' => [$this->nilai($produk, 'Milano')],
        ])->assertSessionHasErrors();

        $this->assertTrue(app(Keranjang::class)->kosong());
    }

    public function test_opsi_tidak_wajib_tetap_boleh_dikosongkan(): void
    {
        $produk = $this->futsal();

        $this->post(route('keranjang.kustom', $produk), [
            'qty' => 12,
            'opsi' => $this->opsiLengkap($produk),
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, app(Keranjang::class)->jumlahBaris());
    }

    /** Pembeli melihat bahan dan model yang ia bayar sebelum membuat pesanan. */
    public function test_keranjang_menampilkan_pilihan_barang_kustom(): void
    {
        $produk = $this->futsal();

        $this->post(route('keranjang.kustom', $produk), [
            'qty' => 12,
            'opsi' => $this->opsiLengkap($produk, 'Hyget', 'Kerah Polo'),
        ]);

        $this->get(route('keranjang'))
            ->assertOk()
            ->assertSee('Bahan: Hyget')
            ->assertSee('Kerah: Kerah Polo')
            ->assertSee('Belum termasuk ongkos kirim.');
    }

    public function test_keranjang_dibatasi_tiga_puluh_baris(): void
    {
        $produk = $this->futsal();
        $opsi = $this->opsiLengkap($produk);

        foreach (range(1, Keranjang::MAKS_BARIS) as $i) {
            $this->post(route('keranjang.kustom', $produk), ['qty' => 12, 'opsi' => $opsi])
                ->assertSessionHasNoErrors();
        }

        $this->post(route('keranjang.kustom', $produk), ['qty' => 12, 'opsi' => $opsi])
            ->assertSessionHasErrors();

        $this->assertSame(Keranjang::MAKS_BARIS, app(Keranjang::class)->jumlahBaris());
    }

    public function test_daftar_ukuran_dibatasi_lima_ratus_baris(): void
    {
        $produk = Product::where('sku', 'CUST-JRS-ATASAN')->firstOrFail();

        $this->post(route('kustom.simpan', $produk), [
            'opsi' => $this->opsiLengkap($produk),
            'baris' => array_fill(0, 501, ['kategori' => 'SD', 'ukuran' => 'L', 'jumlah' => 1]),
            'desain_sumber' => 'katalog',
        ])->assertSessionHasErrors('baris');
    }

    /**
     * Pembatas login dulu satu kunci per IP yang dikosongkan saat login berhasil.
     * Pemilik akun mana pun bisa login ke akunnya sendiri tiap beberapa tebakan
     * dan menebak sandi akun lain tanpa batas.
     */
    public function test_login_ke_akun_sendiri_tidak_mengosongkan_pembatas_tebakan(): void
    {
        foreach (['korban@contoh.test', 'penyerang@contoh.test'] as $email) {
            Customer::create([
                'nama' => 'Pelanggan', 'email' => $email, 'no_hp' => '081200000000',
                'password' => Hash::make('rahasia12345'),
            ])->selesaikanKonfirmasi();
        }

        foreach (range(1, 8) as $i) {
            $this->post('/masuk', ['email' => 'korban@contoh.test', 'password' => "tebakan-{$i}"]);
        }

        $this->post('/masuk', ['email' => 'penyerang@contoh.test', 'password' => 'rahasia12345']);
        $this->assertAuthenticated('pelanggan');
        $this->post('/keluar');

        // Tebakan yang benar pun tidak lagi diperiksa selama masih dibatasi.
        $this->post('/masuk', ['email' => 'korban@contoh.test', 'password' => 'rahasia12345'])
            ->assertSessionHasErrors(['email' => 'Terlalu banyak percobaan. Coba lagi dalam beberapa menit.']);
        $this->assertGuest('pelanggan');
    }

    public function test_tebakan_ke_banyak_email_dari_satu_jaringan_ikut_dibatasi(): void
    {
        foreach (range(1, 30) as $i) {
            $this->post('/masuk', ['email' => "orang{$i}@contoh.test", 'password' => 'tebakan']);
        }

        $this->post('/masuk', ['email' => 'orang-lain@contoh.test', 'password' => 'tebakan'])
            ->assertSessionHasErrors(['email' => 'Terlalu banyak percobaan. Coba lagi dalam beberapa menit.']);
    }

    /** Kode pesanan berurutan; pencarian yang berhasil tidak boleh membuka jatah tebakan baru. */
    public function test_pencarian_pesanan_yang_berhasil_tidak_mengosongkan_pembatas(): void
    {
        $this->post('/keranjang', ['variant_id' => ProductVariant::where('sku', 'BOLA-MITRE-01')->value('id'), 'qty' => 1]);
        $this->post('/checkout', ['nama' => 'Budi', 'email' => 'budi@contoh.test', 'no_hp' => '081234567890', 'setuju' => '1']);
        $milikSendiri = Order::firstOrFail();

        foreach (range(1, 9) as $i) {
            $this->post('/lacak', ['kode' => 'SS-0000-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT), 'email' => 'korban@contoh.test']);
        }

        $this->post('/lacak', ['kode' => $milikSendiri->kode, 'email' => 'budi@contoh.test'])
            ->assertRedirect(route('lacak.token', $milikSendiri->access_token));

        $this->post('/lacak', ['kode' => 'SS-0000-0010', 'email' => 'korban@contoh.test'])
            ->assertSessionHasErrors(['kode' => 'Terlalu banyak percobaan. Coba lagi dalam beberapa menit.']);
    }
}
