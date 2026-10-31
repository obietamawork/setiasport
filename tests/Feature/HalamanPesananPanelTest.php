<?php

namespace Tests\Feature;

use App\Enums\FulfillmentType;
use App\Enums\PriceBasis;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Bagian Pesanan di panel (KEP-099): pencarian di daftar, pesanan manual, dan
 * halaman detail, seperti yang benar-benar dipakai admin dari HP.
 */
class HalamanPesananPanelTest extends TestCase
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

    private function pesanan(string $nama, string $hp, ?string $email = null): Order
    {
        $varian = ProductVariant::where('sku', 'BOLA-MITRE-01')->firstOrFail();

        $this->actingAs($this->owner())
            ->post(route('panel.pesanan.baru.simpan'), [
                'nama' => $nama,
                'no_hp' => $hp,
                'email' => $email,
                'product_id' => $varian->product_id,
                'variant_id' => $varian->id,
                'qty' => 1,
                'tipe_pembayaran' => 'LUNAS',
                'metode_ambil' => 'AMBIL',
            ])
            ->assertSessionHasNoErrors();

        return Order::latest('id')->firstOrFail();
    }

    /**
     * Nomor disalin dari WhatsApp ("+62 812-3456-7890"), sementara pelanggan
     * mengetiknya dengan cara masing-masing saat memesan.
     */
    public function test_cari_nomor_hp_dalam_format_apa_pun(): void
    {
        $a = $this->pesanan('Pak Andi', '0812-3456-7890');
        $b = $this->pesanan('Bu Rina', '+62 813 1111 2222');
        $c = $this->pesanan('Mas Joko', '081299990000');

        $cari = fn (string $q) => $this->hasilCari($q);

        $this->assertSame([$a->kode], $cari('+62 812-3456-7890'));
        $this->assertSame([$b->kode], $cari('081311112222'));
        $this->assertSame([$c->kode], $cari('6281299990000'));

        // Sebagian kode pesanan tetap ditemukan walau bentuknya mirip nomor.
        $this->assertSame([$a->kode], $cari(substr($a->kode, 3)));
    }

    /** @return list<string> kode pesanan yang tampil di daftar */
    private function hasilCari(string $q): array
    {
        return $this->actingAs($this->owner())
            ->get(route('panel.pesanan.index', ['q' => $q]))
            ->assertOk()
            ->viewData('pesanan')
            ->pluck('kode')
            ->all();
    }

    /** "%" dan "_" adalah karakter biasa di kotak cari, bukan pola SQL. */
    public function test_tanda_persen_tidak_menampilkan_semua_pesanan(): void
    {
        $a = $this->pesanan('Pak Andi', '081234567890');

        $this->assertSame([], $this->hasilCari('%'));
        $this->assertSame([], $this->hasilCari('_'));
        $this->assertSame([$a->kode], $this->hasilCari('Andi'));
    }

    /**
     * Produk stok yang juga bisa dipesan kustom (Jersey Polos): formulir
     * manual dulu hanya menawarkan varian stok (KEP-099).
     */
    public function test_pesanan_manual_produk_stok_dan_kustom_bisa_lewat_jalur_kustom(): void
    {
        $produk = Product::where('sku', 'JRS-POLOS-01')->with('options.values')->firstOrFail();
        $pilihan = $produk->options->map(fn ($o) => $o->values->first());

        $this->actingAs($this->owner())
            ->get(route('panel.pesanan.baru'))
            ->assertOk()
            ->assertSee('Pesan kustom');

        $this->actingAs($this->owner())
            ->post(route('panel.pesanan.baru.simpan'), [
                'nama' => 'Tim Polos',
                'no_hp' => '081234567890',
                'product_id' => $produk->id,
                'qty' => 24,
                'opsi' => $pilihan->mapWithKeys(fn ($v) => [$v->product_option_id => $v->id])->all(),
                'tipe_pembayaran' => 'DP',
                'metode_ambil' => 'AMBIL',
            ])
            ->assertSessionHasNoErrors();

        $item = Order::latest('id')->firstOrFail()->items->first();

        $this->assertSame(FulfillmentType::PREORDER, $item->fulfillment_type);
        $this->assertSame(24, $item->qty);
        // Harga tier kustom 12-47 pcs, bukan harga stok Rp65.000.
        $tambahan = $pilihan->where('basis', PriceBasis::PER_PCS)->sum('tambahan_harga');
        $this->assertSame(58_000 + $tambahan, $item->harga_satuan);
    }

    /** Pembeli tanpa email tidak menerima kabar otomatis apa pun. */
    public function test_kartu_whatsapp_tidak_mengaku_email_terkirim_untuk_pelanggan_tanpa_email(): void
    {
        $tanpa = $this->pesanan('Pak Andi', '081234567890');
        $dengan = $this->pesanan('Bu Rina', '081311112222', 'rina@contoh.test');

        $this->actingAs($this->owner())->get(route('panel.pesanan.show', $tanpa))
            ->assertDontSee('Email otomatis sudah dikirim')
            ->assertSee('tidak punya email');

        $this->actingAs($this->owner())->get(route('panel.pesanan.show', $dengan))
            ->assertSee('Email otomatis sudah dikirim');
    }

    /** Ongkir dari kurir jarang bulat ribuan (Rp12.500). */
    public function test_ongkir_tidak_harus_kelipatan_seribu(): void
    {
        $order = $this->pesanan('Pak Andi', '081234567890');

        $this->actingAs($this->owner())
            ->get(route('panel.pesanan.show', $order))
            ->assertDontSee('step="1000"', false);

        $this->actingAs($this->owner())
            ->post(route('panel.pesanan.pengiriman.atur', $order), [
                'metode_ambil' => 'KIRIM',
                'alamat_kirim' => 'Jl. Uji No. 1, Wonogiri',
                'ongkir' => 12_500,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(12_500, $order->fresh()->ongkir);
    }
}
