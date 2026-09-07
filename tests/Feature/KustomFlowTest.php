<?php

namespace Tests\Feature;

use App\Enums\OrderItemStatus;
use App\Enums\PaymentType;
use App\Models\OptionValue;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\AlurPesanan;
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
 * Alur PreOrder: konfigurator, roster, gerbang persetujuan desain, dan antrian
 * produksi per divisi.
 */
class KustomFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('public');
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);
    }

    private function jersey(): Product
    {
        return Product::with(['priceTiers', 'options.values'])->where('sku', 'JRS-FUTSAL-FP')->firstOrFail();
    }

    private function opsi(string ...$label): array
    {
        return OptionValue::whereIn('label', $label)->pluck('id')->all();
    }

    private function staf(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function buatPesanan(int $qty = 18, string $tipe = 'DP'): Order
    {
        $this->post(route('keranjang.kustom', $this->jersey()), [
            'qty' => $qty,
            'opsi' => $this->opsi('Milano', 'Panjang', 'Nama & Nomor', 'Setup Desain Kustom'),
        ])->assertRedirect(route('keranjang'));

        $this->post('/checkout', [
            'nama' => 'Budi Santoso',
            'email' => 'budi@contoh.test',
            'no_hp' => '081234567890',
            'tipe_pembayaran' => $tipe,
            'setuju' => '1',
        ])->assertRedirect();

        return Order::latest('id')->firstOrFail();
    }

    private function isiRoster(Order $order, OrderItem $item, int $jumlah): void
    {
        $ukuran = ['S', 'M', 'L', 'XL'];

        $baris = collect(range(1, $jumlah))->map(fn ($i) => [
            'nama_punggung' => 'PEMAIN '.$i,
            'nomor' => (string) $i,
            'ukuran' => $ukuran[$i % 4],
        ])->all();

        $this->post(route('lacak.item.roster', [$order->access_token, $item]), ['roster' => $baris])
            ->assertSessionHasNoErrors();
    }

    public function test_konfigurator_menegakkan_moq_dan_menghitung_harga_di_server(): void
    {
        $this->post(route('keranjang.kustom', $this->jersey()), ['qty' => 11])
            ->assertSessionHasErrors();

        $order = $this->buatPesanan(18);
        $order->load(['items.options', 'adjustments', 'payments']);

        // Angka yang sama dengan contoh resmi KEP-009.
        $this->assertSame(120_000, $order->items->first()->harga_satuan);
        $this->assertSame(2_235_000, $order->total);
    }

    public function test_dp_minimum_lima_puluh_persen_dipakai_sebagai_tagihan_pertama(): void
    {
        $order = $this->buatPesanan(18, 'DP')->load(['items.options', 'adjustments', 'payments']);

        $bayar = $order->payments->first();

        $this->assertSame(PaymentType::DP, $bayar->jenis);
        $this->assertSame(1_117_500, $bayar->jumlah);
        $this->assertSame(2_235_000, $order->sisa_tagihan);
    }

    public function test_item_kustom_mendapat_lima_tahap_produksi(): void
    {
        $order = $this->buatPesanan();

        $this->assertSame(5, $order->items->first()->productionJobs()->count());

        app(AlurPesanan::class)->terimaPembayaran($order->payments->first());

        $this->assertSame(OrderItemStatus::ANTRIAN_DESAIN, $order->items()->first()->status_item);
    }

    public function test_nomor_punggung_ganda_ditolak(): void
    {
        $order = $this->buatPesanan(12);
        app(AlurPesanan::class)->terimaPembayaran($order->payments->first());
        $item = $order->items()->first();

        $this->post(route('lacak.item.roster', [$order->access_token, $item]), [
            'roster' => [
                ['nama_punggung' => 'RIZKY', 'nomor' => '10', 'ukuran' => 'M'],
                ['nama_punggung' => 'ANDI', 'nomor' => '10', 'ukuran' => 'L'],
            ],
        ])->assertSessionHasErrors();

        $this->assertSame(0, $item->rosters()->count());
    }

    public function test_roster_tidak_boleh_melebihi_jumlah_pesanan(): void
    {
        $order = $this->buatPesanan(12);
        app(AlurPesanan::class)->terimaPembayaran($order->payments->first());
        $item = $order->items()->first();

        $baris = collect(range(1, 13))->map(fn ($i) => [
            'nama_punggung' => 'P'.$i, 'nomor' => (string) $i, 'ukuran' => 'M',
        ])->all();

        $this->post(route('lacak.item.roster', [$order->access_token, $item]), ['roster' => $baris])
            ->assertSessionHasErrors('roster');
    }

    public function test_mockup_ditolak_selama_daftar_pemain_belum_lengkap(): void
    {
        $order = $this->buatPesanan(12);
        app(AlurPesanan::class)->terimaPembayaran($order->payments->first());
        $item = $order->items()->first();

        $this->isiRoster($order, $item, 5);

        // Lebih murah dihentikan di sini daripada di meja printing.
        $this->actingAs($this->staf('admin@setiasport.test'))
            ->post(route('panel.mockup.simpan', $item), [
                'preview' => UploadedFile::fake()->image('mockup.jpg'),
            ])->assertSessionHasErrors();

        $this->assertSame(OrderItemStatus::ANTRIAN_DESAIN, $item->fresh()->status_item);
    }

    public function test_mockup_lengkap_membawa_item_ke_gerbang_persetujuan(): void
    {
        $item = $this->siapkanMenungguPersetujuan();

        $this->assertSame(OrderItemStatus::MENUNGGU_PERSETUJUAN, $item->status_item);
        $this->assertSame(1, $item->designAssets()->where('tipe', 'MOCKUP')->count());
    }

    public function test_persetujuan_menolak_tanpa_nama_dan_pernyataan(): void
    {
        $item = $this->siapkanMenungguPersetujuan();

        $this->post(route('lacak.item.setujui', [$item->order->access_token, $item]), [])
            ->assertSessionHasErrors(['nama', 'paham']);

        $this->assertNull($item->fresh()->approved_at);
    }

    public function test_permintaan_revisi_mengembalikan_item_ke_desainer(): void
    {
        $item = $this->siapkanMenungguPersetujuan();

        $this->post(route('lacak.item.revisi', [$item->order->access_token, $item]), [
            'catatan' => 'Warna kerah terlalu gelap, tolong diterangkan.',
        ])->assertSessionHas('sukses');

        $item->refresh();

        $this->assertSame(OrderItemStatus::PROSES_DESAIN, $item->status_item);
        $this->assertSame(1, $item->jumlah_revisi);
        $this->assertNull($item->approved_at);
    }

    private function siapkanMenungguPersetujuan(): OrderItem
    {
        $order = $this->buatPesanan(12);
        app(AlurPesanan::class)->terimaPembayaran($order->payments->first());

        $item = $order->items()->first();
        $this->isiRoster($order, $item, 12);

        $this->actingAs($this->staf('admin@setiasport.test'))
            ->post(route('panel.mockup.simpan', $item), [
                'preview' => UploadedFile::fake()->image('mockup.jpg'),
                'catatan' => 'Versi pertama.',
            ])->assertSessionHasNoErrors();

        // Kembali sebagai tamu: pelanggan tidak punya akun.
        auth()->logout();

        return $item->fresh(['order', 'product', 'rosters', 'designAssets']);
    }

    public function test_persetujuan_mencatat_siapa_dan_versi_mockup_mana(): void
    {
        $item = $this->siapkanMenungguPersetujuan();
        $order = $item->order;

        $this->post(route('lacak.item.setujui', [$order->access_token, $item]), [
            'nama' => 'Budi Santoso', 'paham' => '1',
        ])->assertSessionHas('sukses');

        $item->refresh();

        $this->assertSame(OrderItemStatus::ANTRIAN_PRODUKSI, $item->status_item);
        $this->assertSame('Budi Santoso', $item->approved_by_name);
        $this->assertNotNull($item->approved_at);
        $this->assertNotNull($item->approved_design_asset_id);

        // Jam produksi baru berjalan sekarang, bukan sejak pesanan masuk.
        $this->assertNotNull($order->fresh()->target_siap_pada);

        // Roster ikut terkunci bersama desainnya.
        $this->assertTrue($item->rosterTerkunci());
        $this->post(route('lacak.item.roster', [$order->access_token, $item]), [
            'roster' => [['nama_punggung' => 'BARU', 'nomor' => '99', 'ukuran' => 'M']],
        ])->assertStatus(422);
    }

    public function test_admin_memegang_seluruh_antrian_produksi(): void
    {
        $admin = $this->staf('admin@setiasport.test');

        // Susunan dua peran: Admin menjalankan seluruh operasi harian, termasuk
        // kelima divisi produksi (KEP-017).
        foreach (['desain', 'print', 'jahit', 'qc', 'packing'] as $tahap) {
            $this->actingAs($admin)->get("/panel/antrian/{$tahap}")->assertOk();
        }
    }

    public function test_item_di_luar_antrian_produksi_tidak_bisa_dimajukan(): void
    {
        $item = $this->siapkanMenungguPersetujuan();

        // Bolanya sedang di tangan pelanggan, bukan di divisi mana pun.
        $this->actingAs($this->staf('admin@setiasport.test'))
            ->post(route('panel.antrian.selesai', $item))
            ->assertStatus(422);

        $this->assertSame(OrderItemStatus::MENUNGGU_PERSETUJUAN, $item->fresh()->status_item);
    }

    public function test_antrian_produksi_membawa_item_sampai_siap_diambil(): void
    {
        $item = $this->siapkanMenungguPersetujuan();
        $order = $item->order;

        $this->post(route('lacak.item.setujui', [$order->access_token, $item]), [
            'nama' => 'Budi Santoso', 'paham' => '1',
        ]);

        $operator = $this->staf('admin@setiasport.test');
        $gudang = $this->staf('admin@setiasport.test');

        $this->actingAs($operator)->post(route('panel.antrian.selesai', $item->fresh())); // -> PRINTING
        $this->actingAs($operator)->post(route('panel.antrian.selesai', $item->fresh())); // -> JAHIT
        $this->actingAs($operator)->post(route('panel.antrian.selesai', $item->fresh())); // -> QC
        $this->actingAs($gudang)->post(route('panel.antrian.selesai', $item->fresh()));   // -> PACKING
        $this->actingAs($gudang)->post(route('panel.antrian.selesai', $item->fresh()));   // -> SIAP

        $this->assertSame(OrderItemStatus::SIAP, $item->fresh()->status_item);
        $this->assertNotNull($order->fresh()->kode_ambil);

        // Setiap tahap tercatat selesai sebagai akibat pekerjaan operator.
        $this->assertSame(5, $item->productionJobs()->where('status', 'SELESAI')->count());
    }

    public function test_worksheet_memuat_daftar_pemain(): void
    {
        $item = $this->siapkanMenungguPersetujuan();

        $this->actingAs($this->staf('admin@setiasport.test'))
            ->get(route('panel.pesanan.worksheet', $item->order))
            ->assertOk()
            ->assertSee('PEMAIN 1')
            ->assertSee($item->order->kode);
    }
}
