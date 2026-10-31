<?php

namespace Tests\Feature;

use App\Enums\DesignAssetType;
use App\Enums\EmailStatus;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductionStage;
use App\Enums\ProductionStatus;
use App\Enums\StatusPembatalan;
use App\Mail\EmailPesanan;
use App\Models\Customer;
use App\Models\EmailLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Pekerja;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\AlurPesanan;
use App\Services\OrderStatusResolver;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Audit alur internal setelah login (KEP-098): dari pesanan masuk sampai
 * selesai, termasuk kendala produksi, masalah dengan pelanggan, dan sistem.
 */
class AlurInternalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');
        Storage::fake('public');
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);
    }

    private function owner(): User
    {
        return User::where('email', 'owner@setiasport.test')->firstOrFail();
    }

    /** Pesanan kustom dengan DP, lewat jalur yang sama dengan pelanggan. */
    private function pesananDp(): Order
    {
        $produk = Product::where('sku', 'CUST-JRS-ATASAN')->firstOrFail();

        $this->post(route('kustom.simpan', $produk), [
            'opsi' => $this->opsiLengkap($produk),
            'baris' => [['kategori' => 'SD', 'ukuran' => 'L', 'jumlah' => 6]],
            'desain_sumber' => 'katalog',
        ])->assertSessionHasNoErrors();

        $this->post('/checkout', [
            'nama' => 'Budi Santoso', 'email' => 'budi@contoh.test', 'no_hp' => '081234567890',
            'tipe_pembayaran' => 'DP', 'setuju' => '1',
        ])->assertRedirect();

        return Order::latest('id')->firstOrFail();
    }

    private function pesananStok(): Order
    {
        $bola = ProductVariant::where('sku', 'BOLA-MITRE-01')->firstOrFail();
        $this->post('/keranjang', ['variant_id' => $bola->id, 'qty' => 2]);

        $this->post('/checkout', [
            'nama' => 'Budi Santoso', 'email' => 'budi@contoh.test', 'no_hp' => '081234567890', 'setuju' => '1',
        ])->assertRedirect();

        return Order::latest('id')->firstOrFail();
    }

    private function segar(Order $order): Order
    {
        return $order->fresh(['items.options', 'items.rosters', 'payments', 'adjustments', 'pembatalans']);
    }

    private function unggahBukti(Order $order): void
    {
        $this->post(route('lacak.bukti', $order->access_token), [
            'bukti' => UploadedFile::fake()->image('transfer.jpg', 700, 1400),
        ])->assertSessionHas('sukses');
    }

    private function bayarAwal(Order $order): void
    {
        app(AlurPesanan::class)->terimaPembayaran($order->payments()->firstOrFail(), $this->owner()->id);
    }

    private function jalankan(string $nama): void
    {
        collect(app(Schedule::class)->events())
            ->firstOrFail(fn ($e) => $e->description === $nama)
            ->run($this->app);
    }

    /** Item dipindah langsung ke satu tahap, status pesanan ikut disinkronkan. */
    private function keTahap(OrderItem $item, OrderItemStatus $status): OrderItem
    {
        $item->update(['status_item' => $status]);
        app(OrderStatusResolver::class)->sinkronkan($item->order->fresh('items'));

        return $item->fresh();
    }

    // ── Pembayaran ───────────────────────────────────────────────────────

    /**
     * Pelanggan yang membayar Sabtu malam dulu kehilangan pesanannya Minggu
     * malam karena toko tutup dan buktinya belum sempat diperiksa.
     */
    public function test_pesanan_yang_buktinya_sudah_terkirim_tidak_dibatalkan_penjadwal(): void
    {
        $dibayar = $this->pesananDp();
        $this->unggahBukti($dibayar);
        $diam = $this->pesananDp();

        $this->travel(25)->hours();
        $this->jalankan('kadaluarsa-order');

        $this->assertSame(OrderStatus::MENUNGGU_PEMBAYARAN, $dibayar->fresh()->status);
        $this->assertSame(OrderStatus::KADALUARSA, $diam->fresh()->status);

        $this->get(route('lacak.token', $dibayar->access_token))
            ->assertOk()
            ->assertSee('pesanan ini tidak dibatalkan walau batas pembayarannya lewat');
    }

    /** DP yang dibayar tunai di kasir dulu meninggalkan tagihan DP online. */
    public function test_dp_tunai_di_kasir_menutup_tagihan_online(): void
    {
        $order = $this->segar($this->pesananDp());
        $dp = $order->payments->first();

        $this->actingAs($this->owner())
            ->post(route('panel.pesanan.pembayaran.catat', $order), [
                'jumlah' => $dp->jumlah, 'metode' => 'TUNAI', 'jenis' => 'DP',
            ])->assertSessionHas('sukses');

        $this->assertSame(PaymentStatus::KADALUARSA, $dp->fresh()->status);
        $this->assertSame(OrderStatus::DIPROSES, $order->fresh()->status);

        auth('web')->logout();
        $this->get(route('lacak.token', $order->access_token))
            ->assertOk()
            ->assertDontSee('Yang perlu dibayar sekarang');
    }

    /**
     * Potongan reseller sebelum pelanggan membayar dulu membuat tagihan lebih
     * besar dari sisa tagihan, dan verifikasinya ditolak selamanya.
     */
    public function test_potongan_sebelum_bayar_ikut_mengubah_tagihan(): void
    {
        $order = $this->segar($this->pesananStok());
        $total = $order->total;
        $tagihan = $order->payments->first();

        $this->actingAs($this->owner())
            ->post(route('panel.pesanan.penyesuaian.simpan', $order), [
                'deskripsi' => 'Harga reseller', 'jumlah' => -10000,
            ])->assertSessionHas('sukses');

        $this->assertSame($total - 10000, $tagihan->fresh()->jumlah);

        $this->post(route('panel.pesanan.pembayaran.verifikasi', [$order, $tagihan]), [
            'metode' => 'TRANSFER', 'cek_mutasi' => '1',
        ])->assertSessionHas('sukses');

        $this->assertSame(OrderStatus::DIPROSES, $order->fresh()->status);
        $this->assertTrue($this->segar($order)->lunas());
    }

    /**
     * Bukti yang sudah diunggah tidak diubah nominalnya diam-diam; admin
     * mencatat yang benar-benar terlihat di mutasi.
     */
    public function test_admin_mencatat_nominal_yang_benar_benar_masuk(): void
    {
        $order = $this->pesananStok();
        $this->unggahBukti($order);
        $tagihan = $order->payments()->firstOrFail();
        $semula = $tagihan->jumlah;

        $this->actingAs($this->owner())
            ->post(route('panel.pesanan.penyesuaian.simpan', $order), [
                'deskripsi' => 'Harga reseller', 'jumlah' => -10000,
            ]);

        $this->assertSame($semula, $tagihan->fresh()->jumlah);

        $this->post(route('panel.pesanan.pembayaran.verifikasi', [$order, $tagihan]), [
            'metode' => 'TRANSFER', 'cek_mutasi' => '1', 'jumlah' => $semula,
        ])->assertSessionHasErrors('jumlah');

        $this->post(route('panel.pesanan.pembayaran.verifikasi', [$order, $tagihan]), [
            'metode' => 'TRANSFER', 'cek_mutasi' => '1', 'jumlah' => $semula - 10000,
        ])->assertSessionHas('sukses');

        $this->assertSame(PaymentStatus::LUNAS, $tagihan->fresh()->status);
        $this->assertSame($semula - 10000, $tagihan->fresh()->jumlah);
        $this->assertTrue($this->segar($order)->lunas());
    }

    /** Potongan yang melunasi dulu meninggalkan barang di Menunggu Pelunasan selamanya. */
    public function test_potongan_yang_melunasi_membuka_gerbang_pelunasan(): void
    {
        $order = $this->pesananDp();
        $this->bayarAwal($order);
        $this->keTahap($order->items()->firstOrFail(), OrderItemStatus::MENUNGGU_PELUNASAN);
        $sisa = $this->segar($order)->sisa_tagihan;

        $this->actingAs($this->owner())
            ->post(route('panel.pesanan.penyesuaian.simpan', $order), [
                'deskripsi' => 'Kompensasi keterlambatan', 'jumlah' => -$sisa,
            ])->assertSessionHas('sukses');

        $order = $this->segar($order);
        $this->assertSame(OrderStatus::SIAP_DIAMBIL, $order->status);
        $this->assertSame(OrderItemStatus::SIAP, $order->items->first()->status_item);
        $this->assertNotNull($order->kode_ambil);
        Mail::assertQueued(EmailPesanan::class, fn (EmailPesanan $m) => $m->template === 'pesanan-siap');
    }

    /** Halaman panel yang lama terbuka tidak boleh mencatat uang di pesanan batal. */
    public function test_tagihan_pesanan_yang_sudah_batal_tidak_bisa_diverifikasi(): void
    {
        $order = $this->pesananDp();
        $tagihan = $order->payments()->firstOrFail();
        $this->travel(25)->hours();
        $this->jalankan('kadaluarsa-order');

        // Tagihan yang tertinggal Menunggu, seperti halaman yang dibuka sebelum
        // penjadwal berjalan: yang tersisa sebagai penjaga hanya status pesanan.
        $tagihan->fresh()->update(['status' => PaymentStatus::MENUNGGU]);

        $this->actingAs($this->owner())
            ->from(route('panel.pesanan.show', $order))
            ->post(route('panel.pesanan.pembayaran.verifikasi', [$order, $tagihan]), [
                'metode' => 'TRANSFER', 'cek_mutasi' => '1',
            ])
            ->assertRedirect(route('panel.pesanan.show', $order))
            ->assertSessionHasErrors('alur');

        $this->assertStringContainsString('sudah kedaluwarsa', session('errors')->first('alur'));

        $this->assertSame(PaymentStatus::MENUNGGU, $tagihan->fresh()->status);
        $this->assertSame(OrderStatus::KADALUARSA, $order->fresh()->status);
    }

    /** Pesanan dikirim yang bersisa (ongkir menyusul) dulu tidak bisa dibayar online. */
    public function test_pesanan_kirim_yang_masih_bersisa_bisa_dilunasi_dari_halaman_pesanan(): void
    {
        $order = $this->pesananStok();
        $order->update(['metode_ambil' => 'KIRIM', 'alamat_kirim' => 'Jl. Mawar 1, Wonogiri']);
        $this->bayarAwal($order);
        $this->keTahap($order->items()->firstOrFail(), OrderItemStatus::SIAP);

        $this->actingAs($this->owner())
            ->post(route('panel.pesanan.pengiriman.atur', $order), [
                'metode_ambil' => 'KIRIM', 'alamat_kirim' => 'Jl. Mawar 1, Wonogiri', 'ongkir' => 15000,
            ])->assertSessionHas('sukses');
        auth('web')->logout();

        $this->get(route('lacak.token', $order->access_token))
            ->assertOk()
            ->assertSee('Pelunasan')
            ->assertSee(rupiah(15000));

        $this->unggahBukti($order);
        $pelunasan = $order->payments()->where('status', PaymentStatus::MENUNGGU)->firstOrFail();
        $this->assertSame(15000, $pelunasan->jumlah);
    }

    // ── Produksi ─────────────────────────────────────────────────────────

    /** Ketukan kedua pada Selesai dulu melompati satu tahap. */
    public function test_ketukan_kedua_tidak_melompati_tahap(): void
    {
        $order = $this->pesananDp();
        $this->bayarAwal($order);
        $item = $this->keTahap($order->items()->firstOrFail(), OrderItemStatus::PRINTING);

        $this->actingAs($this->owner());
        $this->post(route('panel.antrian.selesai', $item), ['dari' => 'PRINTING'])->assertSessionHas('sukses');
        $this->post(route('panel.antrian.selesai', $item), ['dari' => 'PRINTING'])->assertSessionHas('peringatan');

        $this->assertSame(OrderItemStatus::PRESS, $item->fresh()->status_item);

        $this->post(route('panel.pesanan.item.maju', [$order, $item]), ['dari' => 'PRINTING'])
            ->assertSessionHas('peringatan');
        $this->assertSame(OrderItemStatus::PRESS, $item->fresh()->status_item);
    }

    /** Halaman lembar kerja yang dibuka sejak pagi tidak menandai tahap orang lain. */
    public function test_lembar_kerja_lama_tidak_menandai_tahap_yang_sudah_berpindah(): void
    {
        Pekerja::create([
            'nama' => 'Sri', 'pin_hash' => Pekerja::hashPin('2468'), 'aktif' => true,
            'tahap' => [ProductionStage::PRINT->value, ProductionStage::PRESS->value],
        ]);

        $order = $this->pesananDp();
        $this->bayarAwal($order);
        $item = $this->keTahap($order->items()->firstOrFail(), OrderItemStatus::PRESS);

        $this->post(route('kerja.selesai', $item->tokenKerja()), ['pin' => '2468', 'dari' => 'PRINTING'])
            ->assertSessionHasErrors('pin');

        $this->assertSame(OrderItemStatus::PRESS, $item->fresh()->status_item);
    }

    /** Barang yang harus dikerjakan ulang dulu tidak bisa dikembalikan sama sekali. */
    public function test_barang_bisa_dikembalikan_satu_tahap_untuk_dikerjakan_ulang(): void
    {
        $order = $this->pesananDp();
        $this->bayarAwal($order);
        $item = $this->keTahap($order->items()->firstOrFail(), OrderItemStatus::ANTRIAN_PRODUKSI);

        $this->actingAs($this->owner());
        $this->post(route('panel.antrian.mulai', $item), ['dari' => 'ANTRIAN_PRODUKSI']);
        $this->post(route('panel.antrian.selesai', $item), ['dari' => 'PRINTING']);
        $this->assertSame(OrderItemStatus::PRESS, $item->fresh()->status_item);

        $this->post(route('panel.pesanan.item.mundur', [$order, $item]), ['dari' => 'PRESS'])
            ->assertSessionHasErrors('alasan');

        $this->post(route('panel.pesanan.item.mundur', [$order, $item]), [
            'dari' => 'PRESS', 'alasan' => 'Sablon luntur, cetak ulang',
        ])->assertSessionHas('sukses');

        $item = $item->fresh('productionJobs');
        $this->assertSame(OrderItemStatus::PRINTING, $item->status_item);
        $this->assertSame(ProductionStatus::DIKERJAKAN, $item->productionJobs->firstWhere('tahap', ProductionStage::PRINT)->status);
        $this->assertSame(ProductionStatus::ANTRI, $item->productionJobs->firstWhere('tahap', ProductionStage::PRESS)->status);

        // Gerbang persetujuan tidak dibuka lagi dari sini.
        $lain = $this->pesananDp();
        $this->bayarAwal($lain);
        $menunggu = $this->keTahap($lain->items()->firstOrFail(), OrderItemStatus::MENUNGGU_PERSETUJUAN);

        $this->post(route('panel.pesanan.item.mundur', [$lain, $menunggu]), ['alasan' => 'Coba'])
            ->assertSessionHasErrors('alur');
        $this->assertSame(OrderItemStatus::MENUNGGU_PERSETUJUAN, $menunggu->fresh()->status_item);
    }

    /** Barang siap yang ditarik lagi tidak membawa tanggal siapnya yang lama. */
    public function test_barang_siap_yang_dikembalikan_kehilangan_tanggal_siap(): void
    {
        $order = $this->pesananStok();
        $this->bayarAwal($order);
        $item = $this->keTahap($order->items()->firstOrFail(), OrderItemStatus::SIAP);
        $this->assertNotNull($order->fresh()->siap_pada);

        $this->actingAs($this->owner())
            ->post(route('panel.pesanan.item.mundur', [$order, $item]), [
                'dari' => 'SIAP', 'alasan' => 'Kemasan sobek, dipacking ulang',
            ])->assertSessionHas('sukses');

        $order = $order->fresh();
        $this->assertSame(OrderItemStatus::PACKING, $item->fresh()->status_item);
        $this->assertSame(OrderStatus::DIPROSES, $order->status);
        $this->assertNull($order->siap_pada);
    }

    // ── Desain ───────────────────────────────────────────────────────────

    /**
     * Pelanggan dulu tidak dikabari apa pun saat mockup siap, dan mockup
     * versi baru tidak bisa dikirim selama menunggu persetujuan.
     */
    public function test_mockup_dikirim_ke_pelanggan_dan_versi_baru_tetap_bisa_dikirim(): void
    {
        $order = $this->pesananDp();
        $this->bayarAwal($order);
        $item = $this->keTahap($order->items()->firstOrFail(), OrderItemStatus::PROSES_DESAIN);

        $this->actingAs($this->owner())
            ->post(route('panel.mockup.simpan', $item), ['tautan' => 'https://contoh.test/v1'])
            ->assertSessionHas('sukses');

        Mail::assertQueued(EmailPesanan::class, fn (EmailPesanan $m) => $m->template === 'mockup-siap');

        $item->update(['jumlah_revisi' => 3]);

        $this->post(route('panel.mockup.simpan', $item), ['tautan' => 'https://contoh.test/v2'])
            ->assertSessionHas('sukses');

        $item = $item->fresh();
        $this->assertSame(OrderItemStatus::MENUNGGU_PERSETUJUAN, $item->status_item);
        $this->assertEquals(2, $item->designAssets()->where('tipe', DesignAssetType::MOCKUP)->max('versi'));
        $this->assertSame(3, $item->jumlah_revisi);

        $this->get(route('panel.pesanan.show', $order))
            ->assertOk()
            ->assertSee('desain pesanan '.$order->kode.' sudah jadi', false);

        // Yang sudah disetujui tidak bisa diganti lagi.
        $this->keTahap($item, OrderItemStatus::ANTRIAN_PRODUKSI);
        $this->post(route('panel.mockup.simpan', $item), ['tautan' => 'https://contoh.test/v3'])
            ->assertSessionHasErrors('alur');
    }

    // ── Pembatalan ───────────────────────────────────────────────────────

    /** Pembatalan dari toko atas pesanan berbayar dulu tidak meninggalkan jejak uangnya. */
    public function test_pembatalan_toko_atas_pesanan_berbayar_mencatat_pengembalian(): void
    {
        $order = $this->pesananDp();
        $this->bayarAwal($order);
        $dibayar = $this->segar($order)->sudah_dibayar;

        $this->actingAs($this->owner())
            ->post(route('panel.pesanan.batal', $order), ['alasan' => 'Pelanggan menelepon, acara batal'])
            ->assertSessionHasErrors('refund_jumlah');
        $this->assertSame(OrderStatus::DIPROSES, $order->fresh()->status);

        $this->post(route('panel.pesanan.batal', $order), [
            'alasan' => 'Pelanggan menelepon, acara batal', 'refund_jumlah' => $dibayar, 'refund_metode' => 'TRANSFER',
        ])->assertSessionHas('sukses');

        $order = $this->segar($order);
        $this->assertSame(OrderStatus::DIBATALKAN, $order->status);
        $batal = $order->pembatalans->first();
        $this->assertSame(StatusPembatalan::DISETUJUI, $batal->status);
        $this->assertSame($dibayar, $batal->refund_jumlah);

        auth('web')->logout();
        $this->get(route('lacak.token', $order->access_token))
            ->assertSee('Pengembalian sebesar')
            ->assertSee(rupiah($dibayar));

        $this->actingAs($this->owner())->get(route('panel.laporan'))
            ->assertSee('Dikembalikan karena pembatalan');
    }

    /** Pengajuan pelanggan yang belum diputuskan dulu tertinggal selamanya. */
    public function test_pengajuan_pembatalan_tidak_lagi_tertinggal(): void
    {
        $order = $this->pesananDp();
        $this->bayarAwal($order);
        $this->post(route('lacak.pembatalan', $order->access_token), ['alasan' => 'Tim kami bubar, mohon dibatalkan.']);

        $this->actingAs($this->owner())
            ->post(route('panel.pesanan.batal', $order), ['alasan' => 'Ditelepon', 'refund_jumlah' => 0])
            ->assertSessionHasErrors();
        $this->assertSame(OrderStatus::DIPROSES, $order->fresh()->status);

        // Serah terima juga menunggu keputusannya.
        $this->keTahap($order->items()->firstOrFail(), OrderItemStatus::SIAP);
        app(AlurPesanan::class)->terimaPembayaran(
            $order->payments()->create([
                'jenis' => 'PELUNASAN', 'metode' => 'TUNAI', 'status' => 'MENUNGGU',
                'jumlah' => $this->segar($order)->sisa_tagihan,
            ]),
            $this->owner()->id,
        );
        $this->post(route('panel.pesanan.serah-terima', $order), ['diambil_oleh' => 'Budi'])
            ->assertSessionHasErrors();
        $this->assertSame(OrderStatus::SIAP_DIAMBIL, $order->fresh()->status);

        // Pesanan yang belum dibayar lalu kedaluwarsa menutup pengajuannya sendiri.
        $diam = $this->pesananDp();
        $this->post(route('lacak.pembatalan', $diam->access_token), ['alasan' => 'Salah pesan ukuran, mohon batal.']);
        $this->travel(25)->hours();
        $this->jalankan('kadaluarsa-order');

        $this->assertSame(OrderStatus::KADALUARSA, $diam->fresh()->status);
        $this->assertSame(StatusPembatalan::DISETUJUI, $diam->pembatalans()->first()->status);
    }

    // ── Pengiriman ───────────────────────────────────────────────────────

    /** Yang belum memberangkatkan paket itu toko, bukan pelanggan. */
    public function test_pesanan_kirim_tidak_diingatkan_ambil_dan_tidak_bisa_hangus(): void
    {
        $order = $this->pesananStok();
        $order->update(['metode_ambil' => 'KIRIM', 'alamat_kirim' => 'Jl. Mawar 1, Wonogiri']);
        $this->bayarAwal($order);
        $this->keTahap($order->items()->firstOrFail(), OrderItemStatus::SIAP);

        $this->travel(31)->days();
        $this->jalankan('pengingat-ambil');

        $this->assertFalse($order->emailLogs()->where('template', 'pengingat-ambil')->exists());
        $this->assertFalse($order->fresh()->bolehDihanguskan());

        $this->actingAs($this->owner())->get(route('panel.dashboard'))
            ->assertSee('Siap dikirim')
            ->assertDontSee('Lewat batas pengambilan');

        // Kurir pilihan pelanggan dipilih lebih dulu di form resi.
        $order->update(['layanan_kirim' => 'J&T EZ']);
        $this->get(route('panel.pesanan.show', $order))
            ->assertSee('<option value="J&amp;T Express" selected', false);
    }

    // ── Masalah dengan pelanggan dan sistem ─────────────────────────────

    /** Email yang terpental karena salah ketik dulu tidak bisa diperbaiki dari panel. */
    public function test_kontak_dibetulkan_lalu_email_dikirim_ulang(): void
    {
        $order = $this->pesananDp();
        $log = EmailLog::where('order_id', $order->id)->firstOrFail();
        $log->update(['status' => EmailStatus::BOUNCED]);

        $this->actingAs($this->owner())->get(route('panel.dashboard'))->assertSee($order->kode);

        $this->put(route('panel.pesanan.pelanggan.kontak', $order), [
            'nama' => 'Budi Santoso', 'no_hp' => '081234567899', 'email' => 'budi.santoso@contoh.test',
        ])->assertSessionHas('sukses');

        $this->assertSame('budi.santoso@contoh.test', $order->customer->fresh()->email);

        $this->post(route('panel.pesanan.email.ulang', [$order, $log]))->assertSessionHas('sukses');

        $this->assertNotNull($log->fresh()->ditindaklanjuti_pada);
        $this->assertTrue(EmailLog::where('order_id', $order->id)->where('ke', 'budi.santoso@contoh.test')->exists());
        $this->get(route('panel.dashboard'))->assertDontSee('Email tidak sampai');

        // Email milik pelanggan lain tidak bisa dipasang.
        Customer::create(['nama' => 'Ani', 'email' => 'ani@contoh.test', 'no_hp' => '0811']);
        $this->put(route('panel.pesanan.pelanggan.kontak', $order), [
            'nama' => 'Budi Santoso', 'no_hp' => '081234567899', 'email' => 'ani@contoh.test',
        ])->assertSessionHasErrors('email');
    }

    public function test_email_gagal_bisa_ditandai_sudah_dikabari(): void
    {
        $order = $this->pesananDp();
        $log = EmailLog::where('order_id', $order->id)->firstOrFail();
        $log->update(['status' => EmailStatus::GAGAL]);

        $this->actingAs($this->owner())
            ->post(route('panel.pesanan.email.selesai', [$order, $log]))
            ->assertSessionHas('sukses');

        $this->get(route('panel.dashboard'))->assertDontSee('Email tidak sampai');
    }

    /**
     * Di produksi, kalimat penjelasan dari penjaga alur dulu tidak pernah
     * terbaca: yang tampil halaman bawaan "Unprocessable Content".
     */
    public function test_penolakan_penjaga_alur_tampil_sebagai_pesan_di_halaman_asal(): void
    {
        config(['app.debug' => false]);

        $order = $this->pesananDp();
        $this->bayarAwal($order);
        $item = $this->keTahap($order->items()->firstOrFail(), OrderItemStatus::MENUNGGU_PERSETUJUAN);

        $this->actingAs($this->owner())
            ->from(route('panel.pesanan.show', $order))
            ->post(route('panel.pesanan.item.maju', [$order, $item]))
            ->assertRedirect(route('panel.pesanan.show', $order))
            ->assertSessionHasErrors(['alur' => 'Hanya pelanggan yang bisa menyetujui desain, dari halaman pesanannya. '
                .'Kirim ulang tautannya kalau mereka belum merespons.']);
    }

    public function test_dashboard_mengingatkan_desain_yang_lama_menunggu_dan_paket_yang_sampai(): void
    {
        $desain = $this->pesananDp();
        $this->bayarAwal($desain);
        $item = $this->keTahap($desain->items()->firstOrFail(), OrderItemStatus::MENUNGGU_PERSETUJUAN);
        $item->designAssets()->create(['tipe' => DesignAssetType::MOCKUP, 'versi' => 1, 'external_url' => 'https://contoh.test/a']);

        $paket = $this->pesananStok();
        $paket->update(['metode_ambil' => 'KIRIM', 'alamat_kirim' => 'Jl. Mawar 1']);
        $this->bayarAwal($paket);
        $this->keTahap($paket->items()->firstOrFail(), OrderItemStatus::SIAP);
        app(AlurPesanan::class)->kirimkan($paket->fresh(), 'JNE', 'JNE123', $this->owner()->id);
        $paket->update(['lacak_status' => 'delivered']);

        $this->actingAs($this->owner());
        $this->get(route('panel.dashboard'))->assertDontSee('Desain menunggu persetujuan lebih dari 2 hari');

        $this->travel(3)->days();
        $this->get(route('panel.dashboard'))
            ->assertSee('Desain menunggu persetujuan lebih dari 2 hari')
            ->assertSee($desain->kode)
            ->assertSee('Paket perlu dipastikan')
            ->assertSee($paket->kode);
    }

    /** Stok yang turun di bawah pesanan aktif diberitahukan saat itu juga. */
    public function test_stok_di_bawah_jumlah_dipesan_memberi_peringatan(): void
    {
        $this->pesananStok();
        $bola = ProductVariant::where('sku', 'BOLA-MITRE-01')->firstOrFail();

        $this->actingAs($this->owner())
            ->put(route('panel.stok.update'), ['stok' => [$bola->id => 1], 'alasan' => 'Opname, dua bola kempes'])
            ->assertSessionHas('peringatan');
    }
}
