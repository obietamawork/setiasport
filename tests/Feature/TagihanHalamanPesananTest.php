<?php

namespace Tests\Feature;

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
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Instruksi pembayaran di halaman pesanan pelanggan (KEP-062).
 *
 * Yang dijaga: nomor rekening dan QRIS hanya tampil selama masih ada yang harus
 * dibayar. Pesanan yang sudah lunas tetapi masih memampangkan tujuan transfer
 * adalah cara tercepat membuat pelanggan membayar dua kali.
 */
class TagihanHalamanPesananTest extends TestCase
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

    private function pesan(): Order
    {
        $varian = ProductVariant::where('sku', 'BOLA-MITRE-01')->firstOrFail();

        $this->post(route('keranjang.tambah'), ['variant_id' => $varian->id, 'qty' => 1]);
        $this->post('/checkout', [
            'nama' => 'Budi', 'email' => 'budi@contoh.test', 'no_hp' => '081234567890', 'setuju' => '1',
        ]);

        return Order::latest('id')->firstOrFail();
    }

    public function test_selama_belum_dibayar_rekening_dan_qris_ditampilkan(): void
    {
        $order = $this->pesan();

        $this->get(route('lacak.token', $order->access_token))
            ->assertOk()
            ->assertSee('1234567890')
            ->assertSee('Yang perlu dibayar sekarang');
    }

    /** Inti perbaikannya: begitu lunas, tujuan transfer hilang dari halaman. */
    public function test_setelah_pembayaran_diverifikasi_rekening_tidak_lagi_tampil(): void
    {
        $order = $this->pesan();
        $bayar = $order->payments()->firstOrFail();

        $this->actingAs(User::where('email', 'owner@setiasport.test')->firstOrFail())
            ->post(route('panel.pesanan.pembayaran.verifikasi', [$order, $bayar]), [
                'metode' => 'TRANSFER', 'cek_mutasi' => '1',
            ])->assertRedirect();

        $this->assertSame(0, $order->fresh(['items', 'payments', 'adjustments'])->sisa_tagihan);

        $this->get(route('lacak.token', $order->access_token))
            ->assertOk()
            ->assertDontSee('1234567890')
            ->assertDontSee('Yang perlu dibayar sekarang');
    }

    /**
     * Uang yang diterima tunai di kasir dicatat sebagai pembayaran lain,
     * sehingga baris tagihan lamanya tetap berstatus menunggu. Yang menentukan
     * tetap sisa tagihannya, bukan status baris itu.
     */
    public function test_lunas_lewat_kasir_juga_menyembunyikan_rekening(): void
    {
        $order = $this->pesan();

        $order->payments()->create([
            'jenis' => 'PELUNASAN',
            'metode' => 'TUNAI',
            'status' => PaymentStatus::LUNAS,
            'jumlah' => $order->total,
            'dibayar_pada' => now(),
        ]);

        $this->get(route('lacak.token', $order->access_token))
            ->assertOk()
            ->assertDontSee('1234567890');
    }

    /**
     * Bukti sudah dikirim, tinggal menunggu diperiksa: tujuan transfernya
     * ditutup di balik ringkasan supaya tidak terbaca sebagai "belum bayar".
     */
    public function test_setelah_bukti_dikirim_tujuan_transfer_tidak_lagi_terpampang(): void
    {
        $order = $this->pesan();
        $order->payments()->firstOrFail()->update(['bukti_path' => 'bukti/contoh.jpg']);

        $halaman = $this->get(route('lacak.token', $order->access_token))->assertOk();

        $halaman->assertSee('Transfer sudah Anda kirim');
        $halaman->assertSee('Perlu melihat tujuan transfernya lagi?');
        $halaman->assertSee('sedang dicocokkan dengan mutasi rekening');
    }
}
