<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\OptionValue;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\AlurPesanan;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Lembar invoice dan penanda reseller (KEP-046).
 *
 * Yang dijaga di sini persis kesalahan yang ada di file Excel lama klien:
 * rekening yang tercetak harus rekening toko sendiri, dan angka totalnya harus
 * ikut memperhitungkan potongan.
 */
class InvoiceTest extends TestCase
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
        $produk = Product::where('sku', 'CUST-JRS-ATASAN')->firstOrFail();

        $opsi = OptionValue::whereIn('label', ['Serena', 'Oblong', 'Pendek', 'Tanpa Saku'])
            ->whereHas('option', fn ($q) => $q->where('product_id', $produk->id))
            ->get()
            ->mapWithKeys(fn (OptionValue $v) => [$v->product_option_id => $v->id])
            ->all();

        $this->post(route('kustom.simpan', $produk), [
            'opsi' => $opsi,
            'baris' => [
                ['kategori' => 'SD', 'ukuran' => 'L', 'jumlah' => 4],
                ['kategori' => 'DEWASA', 'ukuran' => 'XL', 'jumlah' => 2],
            ],
            'desain_sumber' => 'katalog',
        ]);

        $this->post('/checkout', [
            'nama' => 'SSB Garuda', 'email' => 'ssb@contoh.test',
            'no_hp' => '081299990000', 'tipe_pembayaran' => 'DP', 'setuju' => '1',
        ]);

        return Order::latest('id')->firstOrFail();
    }

    public function test_invoice_memuat_rincian_dan_rekening_toko(): void
    {
        $order = $this->pesanan();

        $this->actingAs($this->owner())
            ->get(route('panel.pesanan.invoice', $order))
            ->assertOk()
            ->assertSee('INVOICE')
            ->assertSee($order->kode)
            ->assertSee('SSB Garuda')
            // 4 x 45.000 SD + 2 x 60.000 dewasa
            ->assertSee(rupiah(300_000))
            // Rekening yang tercetak wajib rekening toko sendiri.
            ->assertSee(setting('bank_rekening'))
            ->assertSee(setting('bank_atas_nama'))
            ->assertDontSee('AR\'RAD WAHYU R');
    }

    /** Rumus di Excel lama tidak mengurangi diskon, jadi tagihannya kelebihan. */
    public function test_potongan_ikut_mengurangi_total(): void
    {
        $order = $this->pesanan();

        $this->actingAs($this->owner())
            ->post(route('panel.pesanan.penyesuaian.simpan', $order), [
                'deskripsi' => 'Potongan harga reseller',
                'jumlah' => -50_000,
            ])
            ->assertSessionHas('sukses');

        $this->actingAs($this->owner())
            ->get(route('panel.pesanan.invoice', $order))
            ->assertOk()
            ->assertSee('Potongan harga reseller')
            ->assertSee(rupiah(250_000));
    }

    public function test_cap_lunas_muncul_setelah_pembayaran_penuh(): void
    {
        $order = $this->pesanan();

        $this->actingAs($this->owner())
            ->get(route('panel.pesanan.invoice', $order))
            ->assertDontSee('LUNAS');

        app(AlurPesanan::class)->terimaPembayaran($order->payments->first());

        $order->payments()->create([
            'jenis' => PaymentType::PELUNASAN,
            'metode' => PaymentMethod::TRANSFER,
            'status' => PaymentStatus::LUNAS,
            'jumlah' => $order->fresh(['items.options', 'payments', 'adjustments'])->sisa_tagihan,
            'dibayar_pada' => now(),
        ]);

        $this->actingAs($this->owner())
            ->get(route('panel.pesanan.invoice', $order))
            ->assertOk()
            ->assertSee('LUNAS');
    }

    public function test_penanda_reseller_tersimpan_dan_terbaca(): void
    {
        $order = $this->pesanan();

        $this->actingAs($this->owner())
            ->post(route('panel.pesanan.reseller', $order), [
                'reseller' => 1,
                'catatan_reseller' => 'Potongan Rp5.000 per pcs di atas 50 pcs.',
            ])
            ->assertSessionHas('sukses');

        $this->assertTrue($order->customer->fresh()->reseller);

        $this->actingAs($this->owner())
            ->get(route('panel.pesanan.show', $order))
            ->assertSee('Reseller')
            ->assertSee('Potongan Rp5.000 per pcs');

        $this->actingAs($this->owner())
            ->get(route('panel.pesanan.invoice', $order))
            ->assertSee('Reseller');
    }

    public function test_invoice_tertutup_untuk_yang_tidak_berwenang(): void
    {
        $order = $this->pesanan();

        $this->get(route('panel.pesanan.invoice', $order))->assertRedirect(route('panel.login'));
    }
}
