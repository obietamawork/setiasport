<?php

namespace Tests\Feature;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentType;
use App\Models\OptionValue;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\AlurPesanan;
use App\Support\LinimasaItem;
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

    public function test_opsi_yang_tidak_dipakai_tidak_menggagalkan_checkout(): void
    {
        // Opsi tidak wajib mengirim nilai kosong saat pelanggan memilih
        // "Tidak dipakai". Itu berarti tidak memilih, bukan pilihan tidak sah.
        $this->post(route('keranjang.kustom', $this->jersey()), [
            'qty' => 12,
            'opsi' => [1 => $this->opsi('Milano')[0], 6 => ''],
        ])->assertSessionHasNoErrors()->assertRedirect(route('keranjang'));

        $this->assertSame(1, app(\App\Services\Keranjang::class)->jumlahBaris());
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

    public function test_item_kustom_mendapat_enam_tahap_produksi(): void
    {
        $order = $this->buatPesanan();

        // Desain, printing, press, potong, jahit, finishing & packing (KEP-037).
        // Packing terpisah tidak lagi dilewati barang kustom.
        $this->assertSame(
            ['DESAIN', 'PRINT', 'PRESS', 'POTONG', 'JAHIT', 'QC'],
            $order->items->first()->productionJobs()->orderBy('urutan')->pluck('tahap')->map->value->all()
        );

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

    /**
     * Batasnya keras, bukan sekadar batas gratis (KEP-038). Klien memilih tidak
     * menjual revisi tambahan, jadi permintaan keempat harus ditolak, bukan
     * diterima lalu ditagihkan.
     */
    public function test_revisi_melebihi_batas_ditolak(): void
    {
        $item = $this->siapkanMenungguPersetujuan();
        $item->update(['jumlah_revisi' => (int) setting('revisi_gratis_maks', 3)]);

        $this->post(route('lacak.item.revisi', [$item->order->access_token, $item]), [
            'catatan' => 'Sekali lagi tolong warnanya diubah.',
        ])->assertStatus(422);

        $item->refresh();

        $this->assertSame(OrderItemStatus::MENUNGGU_PERSETUJUAN, $item->status_item);
        $this->assertSame(3, $item->jumlah_revisi);
    }

    /** Formulir yang pasti ditolak tidak boleh tetap dipajang. */
    public function test_formulir_revisi_hilang_setelah_jatahnya_habis(): void
    {
        $item = $this->siapkanMenungguPersetujuan();

        $this->get(route('lacak.token', $item->order->access_token))
            ->assertSee('Minta revisi');

        $item->update(['jumlah_revisi' => (int) setting('revisi_gratis_maks', 3)]);

        $this->get(route('lacak.token', $item->order->access_token))
            ->assertDontSee('Minta revisi')
            ->assertSee('sudah terpakai');
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

    /** Menjalankan antrian produksi sampai item meninggalkan Finishing & Packing. */
    private function kerjakanProduksi(OrderItem $item): void
    {
        $operator = $this->staf('admin@setiasport.test');

        // ANTRIAN_PRODUKSI -> PRINTING -> PRESS -> POTONG -> JAHIT -> QC -> (gerbang)
        foreach (range(1, 6) as $_) {
            $this->actingAs($operator)
                ->post(route('panel.antrian.selesai', $item->fresh()))
                ->assertSessionHasNoErrors();
        }

        auth()->logout();
    }

    private function setujui(OrderItem $item): void
    {
        $this->post(route('lacak.item.setujui', [$item->order->access_token, $item]), [
            'nama' => 'Budi Santoso', 'paham' => '1',
        ])->assertSessionHas('sukses');
    }

    public function test_produksi_melewati_lima_subtahap_lalu_menunggu_pelunasan(): void
    {
        $item = $this->siapkanMenungguPersetujuan();
        $order = $item->order;

        $this->setujui($item);
        $this->kerjakanProduksi($item);

        // Pesanan DP: barang jadi, tapi uangnya belum (KEP-028).
        $this->assertSame(OrderItemStatus::MENUNGGU_PELUNASAN, $item->fresh()->status_item);
        $this->assertSame(OrderStatus::MENUNGGU_PELUNASAN, $order->fresh()->status);

        // Kode ambil belum boleh terbit: itu yang membuat barang bisa keluar.
        $this->assertNull($order->fresh()->kode_ambil);

        // Desain, printing, potong, press, jahit, finishing: enam tahap tercatat
        // selesai sebagai akibat pekerjaan operator, bukan diketik terpisah.
        $this->assertSame(6, $item->productionJobs()->where('status', 'SELESAI')->count());

        // Tanpa kabar ini pelanggan tidak tahu bahwa kini gilirannya membayar.
        $this->assertTrue($order->emailLogs()->where('template', 'menunggu-pelunasan')->exists());
    }

    public function test_staf_tidak_bisa_melewati_gerbang_pelunasan(): void
    {
        $item = $this->siapkanMenungguPersetujuan();
        $this->setujui($item);
        $this->kerjakanProduksi($item);

        $this->actingAs($this->staf('owner@setiasport.test'))
            ->post(route('panel.pesanan.item.maju', [$item->order, $item->fresh()]))
            ->assertStatus(422);

        $this->assertSame(OrderItemStatus::MENUNGGU_PELUNASAN, $item->fresh()->status_item);
        $this->assertNull($item->order->fresh()->kode_ambil);
    }

    public function test_pelunasan_di_kasir_menaikkan_ke_siap_diambil(): void
    {
        $item = $this->siapkanMenungguPersetujuan();
        $order = $item->order;

        $this->setujui($item);
        $this->kerjakanProduksi($item);

        $sisa = $order->fresh(['items.options', 'payments', 'adjustments'])->sisa_tagihan;

        $this->actingAs($this->staf('admin@setiasport.test'))
            ->post(route('panel.pesanan.pembayaran.catat', $order), [
                'jumlah' => $sisa,
                'metode' => 'TUNAI',
                'jenis' => 'PELUNASAN',
            ])
            ->assertSessionHas('sukses');

        $this->assertSame(OrderItemStatus::SIAP, $item->fresh()->status_item);
        $this->assertSame(OrderStatus::SIAP_DIAMBIL, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->kode_ambil);
    }

    public function test_pelunasan_lewat_bukti_unggahan_pelanggan(): void
    {
        Storage::fake('local');

        $item = $this->siapkanMenungguPersetujuan();
        $order = $item->order;

        $this->setujui($item);
        $this->kerjakanProduksi($item);

        $sisa = $order->fresh(['items.options', 'payments', 'adjustments'])->sisa_tagihan;

        // Pelanggan menyatakan sudah membayar dengan mengunggah bukti.
        $this->post(route('lacak.bukti', $order->access_token), [
            'bukti' => UploadedFile::fake()->image('transfer.jpg', 700, 1400),
        ])->assertSessionHas('sukses');

        $tagihan = $order->payments()->where('jenis', 'PELUNASAN')->firstOrFail();

        // Nominalnya diambil dari sisa tagihan SAAT ITU, bukan disimpan jauh hari.
        $this->assertSame($sisa, $tagihan->jumlah);
        $this->assertNotNull($tagihan->bukti_path);

        // Bukti bukan verifikasi: barang tetap tertahan sampai admin memeriksa mutasi.
        $this->assertSame(OrderItemStatus::MENUNGGU_PELUNASAN, $item->fresh()->status_item);

        $this->actingAs($this->staf('admin@setiasport.test'))
            ->post(route('panel.pesanan.pembayaran.verifikasi', [$order, $tagihan]), [
                'metode' => 'TRANSFER',
                'cek_mutasi' => '1',
            ])
            ->assertSessionHas('sukses');

        $this->assertSame(OrderStatus::SIAP_DIAMBIL, $order->fresh()->status);
    }

    public function test_pesanan_lunas_di_muka_tidak_berhenti_di_pelunasan(): void
    {
        $order = $this->buatPesanan(12, 'LUNAS');
        app(AlurPesanan::class)->terimaPembayaran($order->payments->first());

        $item = $order->items()->first();
        $this->isiRoster($order, $item, 12);

        $this->actingAs($this->staf('admin@setiasport.test'))
            ->post(route('panel.mockup.simpan', $item), [
                'preview' => UploadedFile::fake()->image('mockup.jpg'),
            ])->assertSessionHasNoErrors();
        auth()->logout();

        $this->setujui($item->fresh(['order']));
        $this->kerjakanProduksi($item);

        $this->assertSame(OrderItemStatus::SIAP, $item->fresh()->status_item);
        $this->assertSame(OrderStatus::SIAP_DIAMBIL, $order->fresh()->status);
    }

    public function test_timeline_pelanggan_hanya_membuka_subprogres_produksi(): void
    {
        $item = $this->siapkanMenungguPersetujuan();
        $this->setujui($item);

        $operator = $this->staf('admin@setiasport.test');

        // ANTRIAN_PRODUKSI -> PRINTING -> PRESS -> POTONG -> JAHIT
        foreach (range(1, 4) as $_) {
            $this->actingAs($operator)->post(route('panel.antrian.selesai', $item->fresh()));
        }

        $order = $item->order->fresh(['items.options', 'payments', 'adjustments', 'statusLogs']);
        $linimasa = LinimasaItem::untuk($item->fresh(), $order);

        $fase = collect($linimasa['fase'])->keyBy(fn ($f) => $f['fase']->value);

        // Tahap besar persis seperti yang diminta klien.
        $this->assertSame(
            ['Pesanan Masuk', 'Pembayaran', 'Desain & Data', 'Produksi', 'Pelunasan', 'Siap Diambil', 'Selesai'],
            collect($linimasa['fase'])->pluck('label')->all()
        );

        $this->assertSame('kini', $fase['PRODUKSI']['keadaan']);
        $this->assertSame('lewat', $fase['DESAIN']['keadaan']);
        $this->assertSame('nanti', $fase['PELUNASAN']['keadaan']);

        $sub = collect($fase['PRODUKSI']['subtahap'])->pluck('keadaan', 'label')->all();

        $this->assertSame([
            'Printing Jersey' => 'lewat',
            'Press / Sublime' => 'lewat',
            'Potong Kain' => 'lewat',
            'Jahit' => 'kini',
            'Finishing & Packing' => 'nanti',
        ], $sub);

        // Fase lain tidak membuka rinciannya.
        $this->assertSame([], $fase['DESAIN']['subtahap']);
    }

    public function test_halaman_pesanan_menampilkan_subprogres_produksi(): void
    {
        $item = $this->siapkanMenungguPersetujuan();
        $this->setujui($item);

        $this->actingAs($this->staf('admin@setiasport.test'))
            ->post(route('panel.antrian.selesai', $item->fresh())); // -> PRINTING
        auth()->logout();

        $this->get(route('lacak.token', $item->order->access_token))
            ->assertOk()
            ->assertSee('Desain &amp; Data', false)
            ->assertSee('Potong Kain')
            ->assertSee('Press / Sublime')
            ->assertSee('sedang dikerjakan');
    }

    public function test_toko_tidak_bisa_melewati_gerbang_persetujuan(): void
    {
        $item = $this->siapkanMenungguPersetujuan();

        // Kalau staf bisa memajukannya sendiri, catatan siapa yang menyetujui
        // dan versi mockup mana akan kosong, dan justru itu yang menyelesaikan
        // sengketa berbulan-bulan kemudian.
        $this->actingAs($this->staf('owner@setiasport.test'))
            ->post(route('panel.pesanan.item.maju', [$item->order, $item]))
            ->assertStatus(422);

        $item->refresh();

        $this->assertSame(OrderItemStatus::MENUNGGU_PERSETUJUAN, $item->status_item);
        $this->assertNull($item->approved_at);
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
