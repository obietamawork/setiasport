<?php

namespace App\Services;

use App\Enums\FulfillmentType;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\ProductionStage;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

/**
 * Mengubah keranjang menjadi pesanan.
 *
 * Semua harga dihitung ulang di sini dari data master, nilai apa pun yang
 * datang dari browser tidak dipercaya (KEP-009). Seluruhnya berjalan dalam
 * satu transaksi supaya order tidak pernah tercipta setengah jadi dengan
 * reservasi stok yang salah.
 */
class Checkout
{
    public function __construct(
        private Keranjang $keranjang,
        private ReservasiStok $reservasi,
    ) {}

    /**
     * @param  array{nama:string, email:string, no_hp:string, catatan:?string}  $pelanggan
     */
    public function proses(array $pelanggan, string $tipePembayaran): Order
    {
        $isi = $this->keranjang->isi();

        abort_if($isi->isEmpty(), 422, 'Keranjang kosong.');

        return DB::transaction(function () use ($isi, $pelanggan, $tipePembayaran) {
            $customer = Customer::firstOrNew(['email' => $pelanggan['email']]);

            // Profil pelanggan yang akunnya sudah aktif tidak boleh ditimpa oleh
            // pemesan tamu yang kebetulan memakai alamat email yang sama (KEP-021).
            if (! $customer->exists || ! $customer->akunAktif()) {
                $customer->fill([
                    'nama' => $pelanggan['nama'],
                    'no_hp' => $pelanggan['no_hp'],
                ]);
            }

            $customer->save();

            $order = Order::create([
                'kode' => Order::kodeBaru(),
                'customer_id' => $customer->id,
                'status' => OrderStatus::MENUNGGU_PEMBAYARAN,
                'access_token' => Order::tokenBaru(),
                'tipe_pembayaran' => $tipePembayaran,
                'kadaluarsa_pada' => now()->addHours((int) Setting::ambil('batas_bayar_jam', 24)),
                'catatan' => $pelanggan['catatan'] ?? null,

                // Dibekukan di sini (KEP-048). Tarifnya boleh berubah nanti tanpa
                // mengubah tagihan pesanan yang sudah berjalan.
                'biaya_layanan' => (int) Setting::ambil('biaya_layanan', 0),
            ]);

            $diskon = 0;

            foreach ($isi as $baris) {
                abort_if($baris['masalah'] !== null, 422, $baris['masalah']);

                $item = $order->items()->create([
                    'product_id' => $baris['produk']->id,
                    'product_variant_id' => $baris['varian']?->id,
                    'nama_produk' => $baris['produk']->nama
                        .($baris['varian']?->label ? ': '.$baris['varian']->label : ''),
                    'fulfillment_type' => $baris['jalur'],
                    'qty' => $baris['qty'],
                    // Baris berharga per ukuran tidak punya satu harga satuan;
                    // yang disimpan di sini rata-ratanya, sekadar untuk dibaca.
                    // Totalnya dihitung dari harga tiap baris ukuran (KEP-039).
                    'harga_satuan' => $baris['rincian'] instanceof RincianPerUkuran
                        ? $baris['rincian']->hargaRataRata()
                        : $baris['rincian']->hargaSatuan,
                    'status_item' => OrderItemStatus::MENUNGGU_PEMBAYARAN,
                    'catatan' => $baris['catatan'] ?? null,
                ]);

                $this->simpanDaftarUkuran($item, $baris);

                // Snapshot opsi lengkap dengan basis-nya, agar rincian harga
                // tetap bisa dijelaskan setelah data master berubah.
                foreach ($baris['rincian']->komponenPerPcs as $k) {
                    $item->options()->create([
                        'option_nama' => $k['label'],
                        'option_value' => $k['nilai'],
                        'tambahan_harga' => $k['jumlah'],
                        'basis' => \App\Enums\PriceBasis::PER_PCS,
                    ]);
                }

                foreach ($baris['rincian']->komponenPerItem as $k) {
                    $item->options()->create([
                        'option_nama' => 'Biaya sekali',
                        'option_value' => $k['label'],
                        'tambahan_harga' => $k['jumlah'],
                        'basis' => \App\Enums\PriceBasis::PER_ITEM,
                    ]);
                }

                // Potongan borongan dicatat sebagai diskon pesanan (KEP-050),
                // bukan mengurangi harga tiap potong: pelanggan berhak melihat
                // harga penuhnya dan potongannya sebagai dua angka terpisah.
                if ($baris['rincian'] instanceof RincianPerUkuran) {
                    $diskon += $baris['rincian']->potongan;
                }

                $this->reservasi->pesan($item);
                $this->siapkanJobProduksi($item);
            }

            if ($diskon > 0) {
                $order->update(['diskon' => $diskon]);
            }

            $order->load(['items.options']);

            $order->payments()->create([
                'jenis' => $tipePembayaran === 'DP' ? PaymentType::DP : PaymentType::FULL,
                'metode' => PaymentMethod::TRANSFER,
                'status' => PaymentStatus::MENUNGGU,
                'jumlah' => $tipePembayaran === 'DP' ? $order->dpMinimum() : $order->total,
                'kadaluarsa_pada' => $order->kadaluarsa_pada,
            ]);

            OrderStatusLog::create([
                'order_id' => $order->id,
                'ke' => OrderStatus::MENUNGGU_PEMBAYARAN->value,
                'catatan' => 'Pesanan dibuat pelanggan.',
                'created_at' => now(),
            ]);

            $this->keranjang->bersihkan();

            return $order;
        });
    }

    /**
     * Daftar ukuran dari keranjang disimpan sebagai daftar pemain berikut
     * harganya (KEP-039). Harga ikut dibekukan di sini karena pesanan adalah
     * dokumen historis: daftar harga boleh naik tahun depan tanpa mengubah
     * tagihan yang sudah disepakati hari ini.
     */
    private function simpanDaftarUkuran(\App\Models\OrderItem $item, array $baris): void
    {
        if (($baris['ukuran'] ?? null) === null) {
            return;
        }

        foreach ($baris['rincian']->baris as $urutan => $r) {
            $item->rosters()->create([
                'urutan' => $urutan + 1,
                'kategori' => $r['kategori'] !== '' ? $r['kategori'] : null,
                'nama_punggung' => $baris['ukuran'][$urutan]['nama_punggung'] ?? null,
                'nomor' => $baris['ukuran'][$urutan]['nomor'] ?? null,
                'ukuran' => $r['ukuran'],
                'jumlah' => $r['jumlah'],
                'harga_satuan' => $r['harga_satuan'],
            ]);
        }
    }

    /**
     * Item kustom langsung mendapat antrian pekerjaannya. Inilah yang membuat
     * status pelanggan bisa naik sebagai efek samping pekerjaan operator,
     * bukan sebagai input terpisah (docs/04).
     *
     * Daftarnya DITURUNKAN dari urutan status, bukan ditulis ulang di sini.
     * Dua daftar yang dijaga terpisah pasti berselisih suatu hari: begitu alur
     * kustom mendapat potong dan press (KEP-028), daftar tulisan tangan di sini
     * masih menyiapkan packing yang tidak lagi dilewati.
     */
    private function siapkanJobProduksi(\App\Models\OrderItem $item): void
    {
        $tahap = collect(OrderItemStatus::alur($item->fulfillment_type))
            ->map(fn (OrderItemStatus $s) => $s->tahapProduksi())
            ->filter()
            ->unique(fn (ProductionStage $t) => $t->value)
            ->values();

        foreach ($tahap as $urutan => $t) {
            $item->productionJobs()->create([
                'tahap' => $t,
                'urutan' => $urutan,
            ]);
        }
    }
}
