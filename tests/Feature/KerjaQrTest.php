<?php

namespace Tests\Feature;

use App\Enums\OrderItemStatus;
use App\Enums\ProductionStage;
use App\Models\OptionValue;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Pekerja;
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
 * Menandai tahap produksi lewat pindai QR di lembar kerja (KEP-047).
 *
 * Yang dijaga di sini: membuka tautan tidak mengubah apa pun, tiap orang hanya
 * bisa menandai mejanya sendiri, halaman kerja tidak pernah membocorkan harga
 * atau data pelanggan, dan siapa yang menandai ikut tercatat.
 */
class KerjaQrTest extends TestCase
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

    private function pekerja(string $nama, string $pin, array $tahap): Pekerja
    {
        return Pekerja::create([
            'nama' => $nama,
            'pin_hash' => Pekerja::hashPin($pin),
            'tahap' => $tahap,
            'aktif' => true,
        ]);
    }

    /** Item kustom yang sudah masuk antrian produksi. */
    private function itemProduksi(): OrderItem
    {
        $produk = Product::where('sku', 'CUST-JRS-ATASAN')->firstOrFail();

        $opsi = OptionValue::whereIn('label', ['Serena', 'Oblong', 'Pendek', 'Tanpa Saku'])
            ->whereHas('option', fn ($q) => $q->where('product_id', $produk->id))
            ->get()
            ->mapWithKeys(fn (OptionValue $v) => [$v->product_option_id => $v->id])
            ->all();

        $this->post(route('kustom.simpan', $produk), [
            'opsi' => $opsi,
            'baris' => [['kategori' => 'SD', 'ukuran' => 'L', 'jumlah' => 6, 'nama_punggung' => 'ANDI', 'nomor' => '10']],
            'desain_sumber' => 'katalog',
        ]);

        $this->post('/checkout', [
            'nama' => 'SSB Garuda', 'email' => 'ssb@contoh.test',
            'no_hp' => '081299990000', 'tipe_pembayaran' => 'DP', 'setuju' => '1',
        ]);

        $order = Order::latest('id')->firstOrFail();
        app(AlurPesanan::class)->terimaPembayaran($order->payments->first());

        $item = $order->items->first();
        $item->update(['status_item' => OrderItemStatus::PRINTING]);

        return $item->fresh();
    }

    public function test_lembar_kerja_memuat_qr_dan_tautannya(): void
    {
        $item = $this->itemProduksi();

        $html = $this->actingAs($this->owner())
            ->get(route('panel.pesanan.worksheet', $item->order))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('<svg', $html);
        $this->assertStringContainsString(route('kerja', $item->fresh()->kerja_token), $html);
    }

    /**
     * Baris roster tanpa nama (tempel dari Excel, atau pesanan manual) boleh
     * mewakili banyak potong sekaligus -- lembar cetaknya WAJIB menunjukkan
     * jumlahnya, atau operator di meja potong/jahit bisa mengerjakan satu
     * potong lalu menganggap barisnya selesai, menyisakan sisanya tak terbuat.
     */
    public function test_worksheet_menunjukkan_jumlah_per_baris_roster(): void
    {
        $item = $this->itemProduksi();
        $item->rosters()->create([
            'urutan' => 99, 'kategori' => 'SD', 'nama_punggung' => null, 'nomor' => null,
            'ukuran' => 'L', 'jumlah' => 5, 'harga_satuan' => 45_000,
        ]);

        $html = $this->actingAs($this->owner())
            ->get(route('panel.pesanan.worksheet', $item->order))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Jumlah', $html);
        // Baris "ANDI" dari itemProduksi() berjumlah 6, baris tambahan di atas 5.
        $this->assertMatchesRegularExpression('/<td class="nomor"><strong>6<\/strong><\/td>/', $html);
        $this->assertMatchesRegularExpression('/<td class="nomor"><strong>5<\/strong><\/td>/', $html);
    }

    /** Pratinjau tautan di grup WhatsApp membuka halaman ini sendiri. */
    public function test_membuka_halaman_tidak_mengubah_tahap(): void
    {
        $item = $this->itemProduksi();

        $this->get(route('kerja', $item->tokenKerja()))
            ->assertOk()
            ->assertSee('Printing Jersey')
            ->assertSee('6 pcs');

        $this->assertSame(OrderItemStatus::PRINTING, $item->fresh()->status_item);
    }

    public function test_pin_yang_benar_memajukan_tahap_dan_mencatat_pekerjanya(): void
    {
        $item = $this->itemProduksi();
        $pekerja = $this->pekerja('Budi', '1234', [ProductionStage::PRINT->value]);

        $this->post(route('kerja.selesai', $item->tokenKerja()), ['pin' => '1234'])
            ->assertSessionHas('sukses');

        $item->refresh();

        $this->assertSame(OrderItemStatus::PRESS, $item->status_item);
        $this->assertSame(
            $pekerja->id,
            $item->productionJobs()->where('tahap', ProductionStage::PRINT->value)->first()->pekerja_id
        );
    }

    /**
     * PIN cuma empat angka. Batas per IP di rute tidak menghentikan penebak yang
     * berganti IP, jadi lembarnya sendiri dikunci setelah lima PIN salah (KEP-088).
     */
    public function test_pin_salah_berulang_mengunci_lembarnya(): void
    {
        $item = $this->itemProduksi();
        $this->pekerja('Budi', '1234', [ProductionStage::PRINT->value]);

        foreach (range(1, Pekerja::BATAS_SALAH_PIN) as $i) {
            $this->post(route('kerja.selesai', $item->tokenKerja()), ['pin' => '000'.$i])
                ->assertSessionHasErrors(['pin' => 'PIN tidak dikenali. Pastikan Anda memakai PIN Anda sendiri.']);
        }

        // PIN yang benar pun ditahan selama terkunci.
        $this->post(route('kerja.selesai', $item->tokenKerja()), ['pin' => '1234'])
            ->assertSessionHasErrors('pin');
        $this->assertStringContainsString('Terlalu banyak PIN salah', session('errors')->first('pin'));
        $this->assertSame(OrderItemStatus::PRINTING, $item->fresh()->status_item);
    }

    /** Salah ketik sekali dua kali lalu benar tetap jalan, dan hitungannya kembali nol. */
    public function test_pin_benar_setelah_salah_ketik_tetap_diterima(): void
    {
        $item = $this->itemProduksi();
        $this->pekerja('Budi', '1234', [ProductionStage::PRINT->value]);

        foreach (range(1, Pekerja::BATAS_SALAH_PIN - 1) as $i) {
            $this->post(route('kerja.selesai', $item->tokenKerja()), ['pin' => '000'.$i]);
        }

        $this->post(route('kerja.selesai', $item->tokenKerja()), ['pin' => '1234'])
            ->assertSessionHas('sukses');
        $this->assertSame(OrderItemStatus::PRESS, $item->fresh()->status_item);
    }

    /**
     * Tandai Desain selesai lewat QR tidak boleh memindahkan item ke Menunggu
     * Persetujuan Pelanggan tanpa mockup -- pelanggan akan diminta menyetujui
     * desain yang belum pernah mereka lihat sama sekali.
     */
    public function test_tandai_desain_selesai_tanpa_mockup_ditolak(): void
    {
        $item = $this->itemProduksi();
        $item->update(['status_item' => OrderItemStatus::PROSES_DESAIN]);
        $this->pekerja('Budi', '1234', [ProductionStage::DESAIN->value]);

        // Bukan halaman galat Laravel: pesannya muncul inline di sebelah kotak PIN,
        // karena yang membuka cuma pekerja dengan HP, bukan staf yang tahu cara
        // membaca halaman debug.
        $this->post(route('kerja.selesai', $item->tokenKerja()), ['pin' => '1234'])
            ->assertSessionHasErrors('pin');

        $this->assertSame(OrderItemStatus::PROSES_DESAIN, $item->fresh()->status_item);
    }

    /** Begitu mockup-nya ada, menandai selesai tetap berjalan normal. */
    public function test_tandai_desain_selesai_dengan_mockup_berhasil(): void
    {
        $item = $this->itemProduksi();
        $item->update(['status_item' => OrderItemStatus::PROSES_DESAIN]);
        $item->designAssets()->create([
            'tipe' => \App\Enums\DesignAssetType::MOCKUP, 'versi' => 1, 'external_url' => 'https://drive.google.com/x',
        ]);
        $this->pekerja('Budi', '1234', [ProductionStage::DESAIN->value]);

        $this->post(route('kerja.selesai', $item->tokenKerja()), ['pin' => '1234'])
            ->assertSessionHas('sukses');

        $this->assertSame(OrderItemStatus::MENUNGGU_PERSETUJUAN, $item->fresh()->status_item);
    }

    /** Satu PIN tidak boleh bisa menyelesaikan seluruh meja sekaligus. */
    public function test_pekerja_hanya_bisa_menandai_bagiannya_sendiri(): void
    {
        $item = $this->itemProduksi();
        $this->pekerja('Sri', '4321', [ProductionStage::JAHIT->value]);

        $this->post(route('kerja.selesai', $item->tokenKerja()), ['pin' => '4321'])
            ->assertSessionHasErrors('pin');

        $this->assertSame(OrderItemStatus::PRINTING, $item->fresh()->status_item);
    }

    public function test_pin_yang_tidak_dikenali_ditolak(): void
    {
        $item = $this->itemProduksi();
        $this->pekerja('Budi', '1234', [ProductionStage::PRINT->value]);

        $this->post(route('kerja.selesai', $item->tokenKerja()), ['pin' => '9999'])
            ->assertSessionHasErrors('pin');

        $this->assertSame(OrderItemStatus::PRINTING, $item->fresh()->status_item);
    }

    public function test_pekerja_nonaktif_tidak_bisa_menandai_lagi(): void
    {
        $item = $this->itemProduksi();
        $pekerja = $this->pekerja('Budi', '1234', [ProductionStage::PRINT->value]);
        $pekerja->update(['aktif' => false]);

        $this->post(route('kerja.selesai', $item->tokenKerja()), ['pin' => '1234'])
            ->assertSessionHasErrors('pin');

        $this->assertSame(OrderItemStatus::PRINTING, $item->fresh()->status_item);
    }

    /**
     * Tautannya beredar di grup WhatsApp. Yang ikut terbawa hanya keterangan
     * produksi; harga dan data pelanggan tidak pernah ada di halaman ini.
     */
    public function test_halaman_kerja_tidak_memuat_harga_dan_data_pelanggan(): void
    {
        $item = $this->itemProduksi();

        $this->get(route('kerja', $item->tokenKerja()))
            ->assertOk()
            ->assertSee('ANDI')
            ->assertDontSee('SSB Garuda')
            ->assertDontSee('081299990000')
            ->assertDontSee(rupiah(270_000));
    }

    public function test_token_asal_ditolak(): void
    {
        $this->get(route('kerja', 'token-karangan'))->assertNotFound();
        $this->post(route('kerja.selesai', 'token-karangan'), ['pin' => '1234'])->assertNotFound();
    }

    public function test_admin_mendaftarkan_pekerja_dengan_pin_unik(): void
    {
        $this->actingAs($this->owner())
            ->post(route('panel.pekerja.simpan'), [
                'nama' => 'Budi', 'pin' => '1234', 'tahap' => [ProductionStage::PRINT->value],
            ])
            ->assertSessionHas('sukses');

        // PIN kembar membuat catatan siapa mengerjakan apa jadi tebakan.
        $this->actingAs($this->owner())
            ->post(route('panel.pekerja.simpan'), [
                'nama' => 'Sri', 'pin' => '1234', 'tahap' => [ProductionStage::JAHIT->value],
            ])
            ->assertStatus(422);

        $this->assertSame(1, Pekerja::count());
    }

    /** PIN tersimpan sebagai hash, jadi tidak bisa dibaca ulang dari panel. */
    public function test_pin_tidak_pernah_tersimpan_apa_adanya(): void
    {
        $this->actingAs($this->owner())->post(route('panel.pekerja.simpan'), [
            'nama' => 'Budi', 'pin' => '4567', 'tahap' => [ProductionStage::PRESS->value],
        ]);

        $pekerja = Pekerja::firstOrFail();

        $this->assertNotSame('4567', $pekerja->pin_hash);
        $this->assertSame(Pekerja::hashPin('4567'), $pekerja->pin_hash);

        $this->actingAs($this->owner())
            ->get(route('panel.pekerja.index'))
            ->assertOk()
            ->assertDontSee('4567');
    }
}
