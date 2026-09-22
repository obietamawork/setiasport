<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\LangkahBerikut;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Perombakan panel internal (KEP-059).
 *
 * Yang dijaga di sini bukan warna atau jaraknya, melainkan hal-hal yang kalau
 * hilang membuat panel kembali sulit dipakai: kalimat "sekarang harus apa" yang
 * benar di tiap keadaan, navigasi yang terjangkau jempol, label yang tidak
 * salah menyebut orang, dan lembar kerja yang bebas dari isi etalase.
 */
class TampilanPanelTest extends TestCase
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

    private function pesanan(array $ubah = []): Order
    {
        $order = Order::create(array_merge([
            'kode' => Order::kodeBaru(),
            'customer_id' => \App\Models\Customer::create([
                'nama' => 'SSB Garuda', 'email' => 'garuda@contoh.test', 'no_hp' => '081200000000',
            ])->id,
            'status' => OrderStatus::MENUNGGU_PEMBAYARAN,
            'access_token' => Order::tokenBaru(),
            'tipe_pembayaran' => 'LUNAS',
        ], $ubah));

        $order->items()->create([
            'product_id' => \App\Models\Product::where('sku', 'CUST-JRS-ATASAN')->value('id'),
            'nama_produk' => 'Jersey Futsal', 'fulfillment_type' => 'PREORDER',
            'qty' => 12, 'harga_satuan' => 95_000, 'status_item' => 'MENUNGGU_PEMBAYARAN',
        ]);

        return $order->fresh(['items', 'payments', 'adjustments']);
    }

    public function test_menu_samping_berikon_dan_ada_bilah_bawah_untuk_layar_sentuh(): void
    {
        $halaman = $this->actingAs($this->owner())->get(route('panel.dashboard'))->assertOk();

        // Bilah bawah: empat tujuan tersering, terjangkau jempol di HP.
        $halaman->assertSee('nav-bawah', false)
            ->assertSee('Navigasi cepat', false);

        // Ikon menemani teks, bukan menggantikannya.
        $halaman->assertSee('menu-teks', false)
            ->assertSeeInOrder(['menu-panel', 'Dashboard'], false);
    }

    public function test_halaman_pesanan_menyebut_langkah_berikutnya(): void
    {
        $order = $this->pesanan();

        $this->actingAs($this->owner())
            ->get(route('panel.pesanan.show', $order))
            ->assertOk()
            ->assertSee('Menunggu pembayaran pelanggan')
            ->assertSee('langkah-berikut', false);
    }

    public function test_bukti_transfer_menunggu_mendahului_langkah_lain(): void
    {
        $order = $this->pesanan();
        $order->payments()->create([
            'jenis' => 'FULL', 'metode' => 'TRANSFER', 'status' => PaymentStatus::MENUNGGU,
            'jumlah' => $order->total, 'bukti_path' => 'bukti/contoh.jpg',
        ]);

        $langkah = app(LangkahBerikut::class)->untuk($order->fresh(['items', 'payments', 'adjustments']));

        $this->assertSame('mendesak', $langkah['nada']);
        $this->assertStringContainsString('Bukti transfer', $langkah['judul']);
    }

    public function test_pesanan_kirim_yang_lunas_diarahkan_mencatat_resi(): void
    {
        $order = $this->pesanan([
            'status' => OrderStatus::SIAP_DIAMBIL,
            'metode_ambil' => \App\Enums\MetodeAmbil::KIRIM,
            'alamat_kirim' => 'Jl. Melati 5, Wonogiri',
            'ongkir' => 22_000,
        ]);
        $order->payments()->create([
            'jenis' => 'FULL', 'metode' => 'TUNAI', 'status' => PaymentStatus::LUNAS,
            'jumlah' => $order->total,
        ]);

        $langkah = app(LangkahBerikut::class)->untuk($order->fresh(['items', 'payments', 'adjustments']));

        $this->assertSame('Siap dikirim', $langkah['judul']);
        $this->assertSame('#pengambilan', $langkah['tuju']);
    }

    /** Pesanan yang sudah mati tidak boleh menyuruh siapa pun mengerjakan apa pun. */
    public function test_pesanan_batal_tidak_menyodorkan_tindakan(): void
    {
        $order = $this->pesanan(['status' => OrderStatus::DIBATALKAN]);

        $langkah = app(LangkahBerikut::class)->untuk($order);

        $this->assertSame('mati', $langkah['nada']);
        $this->assertNull($langkah['tuju']);
    }

    /**
     * "Menunggu Persetujuan Anda" ditulis untuk pelanggan. Di layar admin dan
     * di HP tukang jahit, "Anda" terbaca sebagai diri mereka sendiri.
     */
    public function test_layar_orang_dalam_tidak_menyebut_anda(): void
    {
        $this->assertSame(
            'Menunggu Persetujuan Pelanggan',
            \App\Enums\OrderItemStatus::MENUNGGU_PERSETUJUAN->labelStaf()
        );

        $this->assertSame(
            'Menunggu Persetujuan Anda',
            \App\Enums\OrderItemStatus::MENUNGGU_PERSETUJUAN->label()
        );
    }

    /** Warna status diputuskan sekali, supaya tidak berbeda-beda antar halaman. */
    public function test_setiap_status_punya_warna_dan_ikonnya(): void
    {
        foreach (OrderStatus::cases() as $status) {
            $this->assertStringStartsWith('chip-', $status->warna());
            $this->assertNotSame('', $status->ikon());
        }
    }

    /** Lembar kerja dibuka tukang jahit lewat QR, bukan oleh pembeli. */
    public function test_lembar_kerja_tanpa_keranjang_dan_tombol_masuk(): void
    {
        $item = OrderItem::whereNotNull('kerja_token')->first() ?? tap(
            $this->pesanan()->items()->first(),
            fn (OrderItem $i) => $i->update(['kerja_token' => \Illuminate\Support\Str::random(48)])
        );

        $this->get(route('kerja', $item->kerja_token))
            ->assertOk()
            ->assertDontSee('<span class="sr-only">Keranjang</span>', false)
            ->assertDontSee(route('masuk'), false)
            // Kaki halaman etalase memuat jam buka toko; tidak berguna di meja kerja.
            ->assertDontSee('Jam buka:', false);
    }

    public function test_antrian_menjawab_ada_kerjaan_atau_tidak(): void
    {
        $this->actingAs($this->owner())
            ->get(route('panel.antrian.index'))
            ->assertOk()
            ->assertSee('Semua antrian kosong');
    }
}
