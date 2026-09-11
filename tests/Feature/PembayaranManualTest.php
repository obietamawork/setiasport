<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pembayaran diverifikasi manual (KEP-023), dengan bukti yang diunggah di
 * website dan disimpan privat (KEP-029), bukan dikirim lewat WhatsApp. Yang
 * diuji: halaman pesanan memberi tahu ke mana uangnya dikirim dan di mana
 * buktinya diunggah, bukti tidak bocor ke pihak lain, dan admin tetap wajib
 * mencocokkan dengan mutasi sebelum menutup lingkarannya dari panel.
 */
class PembayaranManualTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);
    }

    private function buatPesanan(): Order
    {
        $bola = ProductVariant::where('sku', 'BOLA-MITRE-01')->firstOrFail();

        $this->post('/keranjang', ['variant_id' => $bola->id, 'qty' => 2]);
        $this->post('/checkout', [
            'nama' => 'Budi Santoso',
            'email' => 'budi@contoh.test',
            'no_hp' => '081234567890',
            'setuju' => '1',
        ]);

        return Order::latest('id')->firstOrFail();
    }

    public function test_halaman_pesanan_menampilkan_tujuan_transfer(): void
    {
        Setting::simpan('bank_nama', 'BCA');
        Setting::simpan('bank_rekening', '1234567890');
        Setting::simpan('bank_atas_nama', 'Setia Sport');

        $order = $this->buatPesanan();

        $this->get(route('lacak.token', $order->access_token))
            ->assertOk()
            ->assertSee('1234567890')
            ->assertSee('Setia Sport')
            ->assertSee('BCA');
    }

    public function test_dua_rekening_tampil_keduanya(): void
    {
        Setting::simpan('bank_nama', 'BCA');
        Setting::simpan('bank_rekening', '1111111111');
        Setting::simpan('bank_atas_nama', 'Setia Sport');
        Setting::simpan('bank2_nama', 'BRI');
        Setting::simpan('bank2_rekening', '2222222222');
        Setting::simpan('bank2_atas_nama', 'Setia Sport');

        $order = $this->buatPesanan();

        $this->get(route('lacak.token', $order->access_token))
            ->assertOk()
            ->assertSee('1111111111')
            ->assertSee('2222222222')
            ->assertSee('pilih salah satu');
    }

    public function test_rekening_kedua_yang_kosong_tidak_meninggalkan_baris_hampa(): void
    {
        Setting::simpan('bank_nama', 'BCA');
        Setting::simpan('bank_rekening', '1111111111');
        Setting::simpan('bank_atas_nama', 'Setia Sport');
        Setting::simpan('bank2_rekening', '');

        $order = $this->buatPesanan();

        $this->get(route('lacak.token', $order->access_token))
            ->assertOk()
            ->assertSee('1111111111')
            ->assertDontSee('pilih salah satu');
    }

    /**
     * Klien menetapkan tidak ada transaksi di luar website (KEP-029): di
     * WhatsApp-lah penipu menyamar sebagai toko. Halaman pembayaran tidak boleh
     * lagi mengajarkan kebiasaan "kirim bukti ke nomor WA".
     */
    public function test_halaman_pembayaran_tidak_mengarah_ke_whatsapp(): void
    {
        Setting::simpan('toko_telepon', '0812-3456-7890');

        $order = $this->buatPesanan();

        $this->get(route('lacak.token', $order->access_token))
            ->assertOk()
            ->assertDontSee('wa.me', false)
            ->assertSee('Hanya transfer ke rekening dan QRIS di halaman ini.');
    }

    public function test_nomor_whatsapp_dirapikan_ke_bentuk_internasional(): void
    {
        $this->assertSame('6281234567890', nomor_wa('0812-3456-7890'));
        $this->assertSame('6281234567890', nomor_wa('+62 812 3456 7890'));
        $this->assertNull(nomor_wa(''));
    }

    public function test_pelanggan_mengunggah_bukti_ke_tagihan_yang_benar(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $order = $this->buatPesanan();
        $tagihan = $order->payments->first();

        $this->post(route('lacak.bukti', $order->access_token), [
            'bukti' => UploadedFile::fake()->image('transfer.png', 700, 1400),
        ])->assertSessionHas('sukses');

        $tagihan->refresh();

        $this->assertNotNull($tagihan->bukti_path);

        // Disimpan di disk privat, bukan di folder yang bisa dibuka lewat URL.
        Storage::disk('local')->assertExists($tagihan->bukti_path);
        Storage::disk('public')->assertMissing($tagihan->bukti_path);

        // Bukti tidak memverifikasi dirinya sendiri.
        $this->assertSame(PaymentStatus::MENUNGGU, $tagihan->status);
    }

    public function test_bukti_hanya_bisa_dibuka_pemegang_tautan_pesanannya(): void
    {
        Storage::fake('local');

        $milikBudi = $this->buatPesanan();
        $this->post(route('lacak.bukti', $milikBudi->access_token), [
            'bukti' => UploadedFile::fake()->image('transfer.png'),
        ]);
        $tagihan = $milikBudi->payments->first()->refresh();

        $this->get(route('lacak.bukti.lihat', [$milikBudi->access_token, $tagihan]))->assertOk();

        // Pesanan lain tidak boleh bisa dipakai untuk membuka bukti Budi.
        $lain = $this->buatPesanan();
        $this->get(route('lacak.bukti.lihat', [$lain->access_token, $tagihan]))->assertNotFound();

        // Tamu tanpa akun staf tidak bisa lewat jalur panel.
        auth('web')->logout();
        $this->get(route('panel.pesanan.pembayaran.bukti', [$milikBudi, $tagihan]))
            ->assertRedirect(route('panel.login'));
    }

    public function test_hanya_gambar_yang_diterima_sebagai_bukti(): void
    {
        Storage::fake('local');

        $order = $this->buatPesanan();

        // PDF dan berkas lain tidak bisa di-encode ulang, jadi tidak diterima.
        $this->post(route('lacak.bukti', $order->access_token), [
            'bukti' => UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('bukti');

        $this->assertNull($order->payments->first()->refresh()->bukti_path);
    }

    public function test_bukti_ditolak_kalau_tidak_ada_tagihan_terbuka(): void
    {
        Storage::fake('local');

        $order = $this->buatPesanan();
        $admin = User::where('email', 'admin@setiasport.test')->firstOrFail();

        $this->actingAs($admin)->post(route('panel.pesanan.pembayaran.verifikasi', [$order, $order->payments->first()]), [
            'metode' => 'TRANSFER', 'cek_mutasi' => '1',
        ]);
        auth('web')->logout();

        $this->post(route('lacak.bukti', $order->access_token), [
            'bukti' => UploadedFile::fake()->image('transfer.png'),
        ])->assertSessionHasErrors();

        $this->assertSame(1, $order->payments()->count());
    }

    public function test_admin_mencatat_metode_dan_bukti_saat_verifikasi(): void
    {
        Storage::fake('local');

        $order = $this->buatPesanan();
        $tagihan = $order->payments->first();
        $admin = User::where('email', 'admin@setiasport.test')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('panel.pesanan.pembayaran.verifikasi', [$order, $tagihan]), [
                'metode' => 'QRIS',
                'bukti' => UploadedFile::fake()->image('bukti-dari-chat.jpg', 800, 1200),
                'cek_mutasi' => '1',
            ])
            ->assertSessionHas('sukses');

        $tagihan->refresh();

        $this->assertSame(PaymentStatus::LUNAS, $tagihan->status);
        $this->assertSame(PaymentMethod::QRIS, $tagihan->metode);
        $this->assertNotNull($tagihan->bukti_path, 'Bukti harus tersimpan untuk sengketa.');
        $this->assertSame($admin->id, $tagihan->diverifikasi_oleh);
        Storage::disk('local')->assertExists($tagihan->bukti_path);
    }

    /**
     * Tangkapan layar transfer bisa diedit dalam semenit. Yang membuktikan uang
     * masuk hanyalah mutasi rekening toko, jadi admin wajib menyatakan sudah
     * melihatnya, dan pernyataan itu tercatat atas namanya.
     */
    public function test_verifikasi_tanpa_pernyataan_cek_mutasi_ditolak(): void
    {
        $order = $this->buatPesanan();
        $admin = User::where('email', 'admin@setiasport.test')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('panel.pesanan.pembayaran.verifikasi', [$order, $order->payments->first()]), [
                'metode' => 'TRANSFER',
            ])
            ->assertSessionHasErrors('cek_mutasi');

        $this->assertSame(PaymentStatus::MENUNGGU, $order->payments->first()->refresh()->status);
    }

    public function test_verifikasi_tanpa_metode_ditolak(): void
    {
        $order = $this->buatPesanan();
        $admin = User::where('email', 'admin@setiasport.test')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('panel.pesanan.pembayaran.verifikasi', [$order, $order->payments->first()]), [])
            ->assertSessionHasErrors('metode');

        $this->assertSame(PaymentStatus::MENUNGGU, $order->payments->first()->refresh()->status);
    }

    /**
     * Seeder pengaturan dijalankan ulang setiap kali ada kunci baru. Dulu ia
     * menimpa NILAI juga, sehingga nomor rekening yang sudah diisi pemilik toko
     * diam-diam kembali kosong, dan pelanggan tidak tahu harus transfer ke mana.
     */
    public function test_seed_ulang_tidak_menimpa_isian_pemilik_toko(): void
    {
        Setting::simpan('bank_rekening', '0123456789');
        Setting::simpan('qris_path', 'qris/asli.webp');

        $this->seed(SettingSeeder::class);

        $this->assertSame('0123456789', Setting::ambil('bank_rekening'));
        $this->assertSame('qris/asli.webp', Setting::ambil('qris_path'));
    }

    public function test_gambar_qris_tidak_bisa_diubah_lewat_formulir_teks(): void
    {
        Setting::simpan('qris_path', 'qris/asli.webp');

        $owner = User::where('email', 'owner@setiasport.test')->firstOrFail();

        $this->actingAs($owner)
            ->put(route('panel.pengaturan.update'), [
                'setting' => ['qris_path' => 'qris/punya-orang-lain.webp'],
            ]);

        $this->assertSame('qris/asli.webp', Setting::ambil('qris_path'));
    }

    public function test_owner_mengunggah_gambar_qris(): void
    {
        Storage::fake('public');

        $owner = User::where('email', 'owner@setiasport.test')->firstOrFail();

        $this->actingAs($owner)
            ->post(route('panel.pengaturan.qris'), [
                'qris' => UploadedFile::fake()->image('qris.png', 600, 600),
            ])
            ->assertSessionHas('sukses');

        $path = Setting::ambil('qris_path');

        $this->assertNotEmpty($path);
        Storage::disk('public')->assertExists($path);
    }
}
