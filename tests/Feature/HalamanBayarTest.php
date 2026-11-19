<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Setting;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Halaman menunggu pembayaran dari revisi klien (KEP-121): ringkasan
 * pesanan, total yang harus dibayar, batas waktu dengan sisa waktunya,
 * pilihan QRIS atau transfer, cara bayar, dan unggah bukti.
 */
class HalamanBayarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);

        Setting::simpan('bank_nama', 'BRI');
        Setting::simpan('bank_rekening', '1234567890');
        Setting::simpan('bank_atas_nama', 'Setia Sport');
    }

    private function pesan(int $qty = 2): Order
    {
        $varian = ProductVariant::where('sku', 'BOLA-MITRE-01')->firstOrFail();

        $this->post(route('keranjang.tambah'), ['variant_id' => $varian->id, 'qty' => $qty]);
        $this->post('/checkout', [
            'nama' => 'Budi', 'email' => 'budi@contoh.test', 'no_hp' => '081234567890', 'setuju' => '1',
        ]);

        return Order::latest('id')->firstOrFail();
    }

    public function test_ringkasan_total_dan_batas_waktu_tampil(): void
    {
        $order = $this->pesan(2);
        $tagihan = $order->payments()->where('status', PaymentStatus::MENUNGGU)->value('jumlah');

        $this->get(route('lacak.token', $order->access_token))
            ->assertOk()
            ->assertSee('Selesaikan pembayaran untuk melanjutkan proses pesanan Anda.')
            ->assertSee('Bola Futsal Mitre Ultimatch')
            ->assertSee('Jumlah pesanan')
            ->assertSee('2 pcs')
            ->assertSee('Total pembayaran')
            ->assertSee(rupiah($tagihan))
            ->assertSee('Batas pembayaran')
            ->assertSee('Sisa waktu');
    }

    public function test_tab_qris_dan_transfer_muncul_bila_keduanya_diatur(): void
    {
        Setting::simpan('qris_path', 'qris/toko.png');
        $order = $this->pesan();

        $this->get(route('lacak.token', $order->access_token))
            ->assertOk()
            ->assertSee('Pilih metode pembayaran')
            ->assertSee('Cara bayar dengan QRIS')
            ->assertSee('Cara bayar lewat transfer bank')
            ->assertSee('1234567890');
    }

    public function test_tanpa_qris_hanya_transfer_yang_ditawarkan(): void
    {
        Setting::simpan('qris_path', '');
        $order = $this->pesan();

        $this->get(route('lacak.token', $order->access_token))
            ->assertOk()
            ->assertDontSee('Cara bayar dengan QRIS')
            ->assertSee('Cara bayar lewat transfer bank');
    }

    /** Batas yang sudah lewat tidak boleh terbaca sebagai hitungan mundur negatif. */
    public function test_batas_yang_sudah_lewat_tidak_menghitung_mundur_negatif(): void
    {
        $order = $this->pesan();
        $order->forceFill(['kadaluarsa_pada' => now()->subHour()])->save();

        $this->get(route('lacak.token', $order->access_token))
            ->assertOk()
            ->assertSee('Batas sudah lewat')
            ->assertDontSee('-1 jam');
    }

    /**
     * Pilihan Transfer / QRIS dan Tunai di toko seperti gambar klien (KEP-134).
     * Tunai berarti datang ke kasir sebelum batas waktu; tidak ada bukti yang
     * diunggah.
     */
    public function test_pilihan_tunai_di_toko_menyebut_alamat_kode_dan_batas(): void
    {
        Setting::simpan('toko_alamat', 'Jl. Manyar II No.2, Wonogiri');
        $order = $this->pesan();

        $this->get(route('lacak.token', $order->access_token))
            ->assertOk()
            ->assertSeeInOrder(['Transfer / QRIS', 'Tunai di toko'])
            ->assertSee('Jl. Manyar II No.2, Wonogiri')
            ->assertSeeInOrder(['Sebutkan kode pesanan', $order->kode])
            ->assertSee('Bayar sebelum');
    }

    public function test_tombol_unggah_bukti_dan_catatan_penting_ada(): void
    {
        $order = $this->pesan();

        $this->get(route('lacak.token', $order->access_token))
            ->assertOk()
            ->assertSee('Unggah bukti pembayaran')
            ->assertSee('Unggah bukti')
            ->assertSee('Catatan penting')
            ->assertSee('Hanya transfer ke rekening dan QRIS di halaman ini.');
    }
}
