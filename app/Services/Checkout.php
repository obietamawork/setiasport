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
            ]);

            foreach ($isi as $baris) {
                abort_if($baris['masalah'] !== null, 422, $baris['masalah']);

                $item = $order->items()->create([
                    'product_id' => $baris['produk']->id,
                    'product_variant_id' => $baris['varian']?->id,
                    'nama_produk' => $baris['produk']->nama
                        .($baris['varian']?->label ? ': '.$baris['varian']->label : ''),
                    'fulfillment_type' => $baris['jalur'],
                    'qty' => $baris['qty'],
                    'harga_satuan' => $baris['rincian']->hargaSatuan,
                    'status_item' => OrderItemStatus::MENUNGGU_PEMBAYARAN,
                ]);

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

                $this->reservasi->pesan($item);
                $this->siapkanJobProduksi($item);
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
     * Item kustom langsung mendapat antrian pekerjaannya. Inilah yang membuat
     * status pelanggan bisa naik sebagai efek samping pekerjaan operator,
     * bukan sebagai input terpisah (docs/04).
     */
    private function siapkanJobProduksi(\App\Models\OrderItem $item): void
    {
        $tahap = $item->fulfillment_type === FulfillmentType::PREORDER
            ? [ProductionStage::DESAIN, ProductionStage::PRINT, ProductionStage::JAHIT,
                ProductionStage::QC, ProductionStage::PACKING]
            : [ProductionStage::PACKING];

        foreach ($tahap as $urutan => $t) {
            $item->productionJobs()->create([
                'tahap' => $t,
                'urutan' => $urutan,
            ]);
        }
    }
}
