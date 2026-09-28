<?php

namespace App\Services;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductionStage;
use App\Enums\ProductionStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusLog;
use App\Models\Payment;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

/**
 * Perpindahan status pesanan. Semua jalan lewat sini supaya log, email, dan
 * status agregat tidak pernah tertinggal di salah satu tempat pemanggilan.
 */
class AlurPesanan
{
    public function __construct(
        private OrderStatusResolver $resolver,
        private PengirimEmail $email,
        private ReservasiStok $reservasi,
    ) {}

    /** Kasir memverifikasi pembayaran masuk. Pekerjaan baru dimulai di sini. */
    public function terimaPembayaran(Payment $payment, ?int $olehUserId = null): void
    {
        DB::transaction(function () use ($payment, $olehUserId) {
            $payment->update([
                'status' => PaymentStatus::LUNAS,
                'dibayar_pada' => $payment->dibayar_pada ?? now(),
                'diverifikasi_oleh' => $olehUserId,
            ]);

            $order = $payment->order->load('items');

            foreach ($order->items as $item) {
                if ($item->status_item !== OrderItemStatus::MENUNGGU_PEMBAYARAN) {
                    continue;
                }

                $this->setStatusItem(
                    $item,
                    $item->kustom() ? OrderItemStatus::ANTRIAN_DESAIN : OrderItemStatus::DIPROSES,
                    'Pembayaran diverifikasi.',
                    $olehUserId
                );
            }

            $this->lepaskanPelunasan($order->fresh(['items.options', 'payments', 'adjustments']), $olehUserId);

            // Batas waktu bayar tidak lagi berlaku begitu uangnya masuk.
            $order->update(['kadaluarsa_pada' => null]);

            $this->sinkronkan($order->fresh(['items.options', 'payments', 'adjustments']));
        });

        // Pelunasan yang membuat pesanan siap sudah diwakili email "pesanan
        // siap". Mengirim dua email untuk satu kejadian hanya melatih
        // pelanggan mengabaikan email kita.
        $order = $payment->order->fresh();

        if ($order->status !== OrderStatus::SIAP_DIAMBIL) {
            $this->email->kirim($order, 'pembayaran-diterima');
        }
    }

    /**
     * Gerbang pelunasan terbuka dengan sendirinya begitu pesanan lunas
     * (KEP-028). Tidak ada tombol untuk ini di panel: satu-satunya cara
     * melewatinya adalah uangnya benar-benar tercatat masuk.
     */
    private function lepaskanPelunasan(Order $order, ?int $olehUserId): void
    {
        if (! $order->lunas()) {
            return;
        }

        foreach ($order->items as $item) {
            if ($item->status_item === OrderItemStatus::MENUNGGU_PELUNASAN) {
                $this->setStatusItem($item, OrderItemStatus::SIAP, 'Pelunasan diterima.', $olehUserId);
            }
        }
    }

    /** Operator menyelesaikan satu tahap; status pelanggan naik sebagai akibatnya. */
    public function majukanItem(OrderItem $item, ?int $olehUserId = null, ?int $pekerjaId = null): OrderItemStatus
    {
        $berikutnya = $item->status_item->berikutnya($item->fulfillment_type);

        abort_if($berikutnya === null, 422, 'Item ini sudah berada di tahap terakhir.');
        abort_if(
            $berikutnya === OrderItemStatus::SELESAI,
            422,
            'Item hanya berpindah ke Selesai lewat serah terima di kasir.'
        );

        /*
         * Gerbang pembayaran awal juga tidak punya tombol. Tanpa penjagaan ini
         * staf bisa mengetuk "maju" berkali-kali dan menerbitkan kode ambil
         * untuk barang yang uangnya belum pernah diverifikasi sama sekali.
         */
        abort_if(
            $item->status_item === OrderItemStatus::MENUNGGU_PEMBAYARAN,
            422,
            'Item ini menunggu pembayaran. Ia baru bisa diproses begitu pembayarannya dicatat atau diverifikasi.'
        );

        /*
         * Gerbang persetujuan tidak boleh dilewati dari sisi toko. Kalau staf
         * bisa memajukannya sendiri, catatan siapa yang menyetujui dan versi
         * mockup mana akan kosong, dan justru itulah yang menyelesaikan sengketa
         * berbulan-bulan kemudian (docs/02).
         */
        abort_if(
            $item->status_item === OrderItemStatus::MENUNGGU_PERSETUJUAN,
            422,
            'Hanya pelanggan yang bisa menyetujui desain, dari halaman pesanannya. '
            .'Kirim ulang tautannya kalau mereka belum merespons.'
        );

        /*
         * Gerbang pelunasan juga tidak punya tombol (KEP-028). Barang kustom
         * dikerjakan dengan modal DP; kalau staf bisa menaikkannya ke Siap,
         * kode ambil terbit dan barang bisa keluar sebelum uangnya masuk.
         */
        abort_if(
            $item->status_item === OrderItemStatus::MENUNGGU_PELUNASAN,
            422,
            'Item ini menunggu pelunasan. Ia naik ke Siap dengan sendirinya begitu '
            .'pembayarannya dicatat atau diverifikasi.'
        );

        // Pesanan yang sudah lunas di muka tidak perlu berhenti di gerbang.
        if ($berikutnya === OrderItemStatus::MENUNGGU_PELUNASAN
            && $item->order->fresh(['items.options', 'payments', 'adjustments'])->lunas()) {
            $berikutnya = OrderItemStatus::SIAP;
        }

        DB::transaction(function () use ($item, $berikutnya, $olehUserId, $pekerjaId) {
            // Tahap yang DITINGGALKAN-lah yang selesai; tahap yang dimasuki baru dimulai.
            $this->selesaikanJob($item, $item->status_item->tahapProduksi(), $olehUserId, $pekerjaId);
            $this->setStatusItem($item, $berikutnya, null, $olehUserId);
            $this->mulaiJob($item, $berikutnya->tahapProduksi(), $olehUserId);

            $this->sinkronkan($item->order->fresh(['items.options', 'payments', 'adjustments']));
        });

        return $berikutnya;
    }

    /**
     * Operator mengambil pekerjaan dari antrian divisinya.
     *
     * Status item menyatakan divisi mana yang sedang memegang barang; status
     * job menyatakan apa yang sedang divisi itu lakukan. Pemisahan ini membuat
     * "antri" dan "sedang dikerjakan" tetap jujur (docs/02).
     */
    public function mulaiKerja(OrderItem $item, int $olehUserId): void
    {
        $tahap = $item->status_item->tahapProduksi();

        abort_if($tahap === null, 422, 'Tahap ini tidak punya pekerjaan yang bisa dimulai.');

        $this->mulaiJob($item, $tahap, $olehUserId);
    }

    /** Serah terima di kasir: pelunasan dan pengambilan adalah satu tindakan (KEP-006). */
    public function serahkan(Order $order, string $diambilOleh, ?int $olehUserId = null): void
    {
        $order->load(['items.options', 'payments', 'adjustments']);

        abort_unless(
            $order->bolehDiserahkan(),
            422,
            'Pesanan belum bisa diserahkan: masih ada sisa tagihan atau belum siap.'
        );

        DB::transaction(function () use ($order, $diambilOleh, $olehUserId) {
            foreach ($order->items as $item) {
                if ($item->status_item === OrderItemStatus::SIAP) {
                    $this->setStatusItem($item, OrderItemStatus::SELESAI, 'Diserahkan ke pelanggan.', $olehUserId);
                }
            }

            $this->reservasi->keluarkan($order);

            $order->update([
                'diambil_pada' => now(),
                'diambil_oleh' => $diambilOleh,
                'diserahkan_oleh' => $olehUserId,
            ]);

            $this->sinkronkan($order->fresh(['items.options', 'payments', 'adjustments']));
        });
    }

    /**
     * Padanan serah terima untuk pesanan yang dikirim (KEP-026). Barangnya
     * keluar dari stok di sini, sama seperti saat diserahkan di kasir: yang
     * berbeda cuma siapa yang membawanya pergi.
     *
     * Statusnya berhenti di DIKIRIM, bukan SELESAI, karena paket yang sudah
     * berangkat belum tentu sampai, dan pesanan yang nyangkut di kurir harus
     * tetap terlihat di daftar aktif.
     */
    public function kirimkan(Order $order, string $jasaKirim, string $noResi, ?int $olehUserId = null): void
    {
        $order->load(['items.options', 'payments', 'adjustments']);

        abort_unless(
            $order->bolehDikirim(),
            422,
            $order->alasanBelumBisaDikirim() ?? 'Pesanan ini belum bisa dikirim.'
        );

        DB::transaction(function () use ($order, $jasaKirim, $noResi, $olehUserId) {
            foreach ($order->items as $item) {
                if ($item->status_item === OrderItemStatus::SIAP) {
                    $this->setStatusItem($item, OrderItemStatus::SELESAI, 'Diserahkan ke jasa kirim.', $olehUserId);
                }
            }

            $this->reservasi->keluarkan($order);

            $order->update([
                'jasa_kirim' => $jasaKirim,
                'no_resi' => $noResi,
                'dikirim_pada' => now(),
                'diserahkan_oleh' => $olehUserId,
            ]);

            $this->sinkronkan($order->fresh(['items.options', 'payments', 'adjustments']));
        });

        // Nomor resi tidak ada gunanya kalau pelanggan harus bertanya dulu untuk
        // mendapatkannya. Dikirim di luar transaksi supaya kegagalan email tidak
        // membatalkan pencatatan paket yang sudah benar-benar berangkat.
        $this->email->kirim($order->fresh(), 'pesanan-dikirim');
    }

    /** Konfirmasi paket sampai. Sampai ini ditekan, pesanan tetap DIKIRIM. */
    public function tandaiDiterima(Order $order, ?int $olehUserId = null): void
    {
        abort_unless($order->status === OrderStatus::DIKIRIM, 422, 'Pesanan ini tidak sedang dalam pengiriman.');

        DB::transaction(function () use ($order, $olehUserId) {
            $order->update(['diterima_pada' => now()]);

            $this->sinkronkan(
                $order->fresh(['items.options', 'payments', 'adjustments']),
                'Paket dikonfirmasi sudah diterima pelanggan.'
            );

            unset($olehUserId);
        });
    }

    public function batalkan(Order $order, string $alasan, ?int $olehUserId = null): void
    {
        abort_if($order->status->final(), 422, 'Pesanan ini sudah selesai atau dibatalkan.');

        DB::transaction(function () use ($order, $alasan, $olehUserId) {
            $order->load('items');

            foreach ($order->items as $item) {
                if ($item->status_item !== OrderItemStatus::DIBATALKAN) {
                    $this->setStatusItem($item, OrderItemStatus::DIBATALKAN, $alasan, $olehUserId);
                }
            }

            $this->reservasi->lepaskan($order);

            $order->payments()
                ->where('status', PaymentStatus::MENUNGGU->value)
                ->update(['status' => PaymentStatus::KADALUARSA->value]);

            $this->sinkronkan($order->fresh(['items.options', 'payments', 'adjustments']), $alasan);
        });
    }

    /**
     * Barang yang tidak pernah diambil (KEP-043).
     *
     * Sengaja BUKAN pekerjaan penjadwal: menghanguskan uang pelanggan secara
     * otomatis di tengah malam adalah keputusan yang tidak bisa ditarik lagi,
     * dan alasannya sering ada di luar sistem (pelanggan sakit, sudah menelepon,
     * barang sudah diambil tapi lupa dicatat). Sistem menandai, admin memutuskan.
     */
    public function hanguskan(Order $order, ?int $olehUserId = null): void
    {
        abort_unless($order->status === OrderStatus::SIAP_DIAMBIL, 422,
            'Hanya pesanan yang sudah siap diambil yang bisa dihanguskan.');

        $hari = (int) Setting::ambil('hangus_hari', 30);

        $this->batalkan(
            $order,
            "Barang tidak diambil lebih dari {$hari} hari, dinyatakan hangus.",
            $olehUserId,
        );

        $order->update(['status' => OrderStatus::KADALUARSA]);
    }

    private function setStatusItem(OrderItem $item, OrderItemStatus $ke, ?string $catatan, ?int $oleh): void
    {
        $dari = $item->status_item;
        $item->update(['status_item' => $ke]);

        OrderStatusLog::create([
            'order_id' => $item->order_id,
            'order_item_id' => $item->id,
            'dari' => $dari->value,
            'ke' => $ke->value,
            'oleh' => $oleh,
            'catatan' => $catatan,
            'created_at' => now(),
        ]);
    }

    private function selesaikanJob(
        OrderItem $item,
        ?ProductionStage $tahap,
        ?int $oleh,
        ?int $pekerjaId = null,
    ): void {
        if (! $tahap) {
            return;
        }

        $item->productionJobs()
            ->where('tahap', $tahap->value)
            ->where('status', '!=', ProductionStatus::SELESAI->value)
            ->update([
                'status' => ProductionStatus::SELESAI->value,
                'diselesaikan_oleh' => $oleh,
                // Pekerja konveksi bukan pengguna panel (KEP-047), jadi yang
                // menandai lewat pindai QR tercatat di kolomnya sendiri.
                'pekerja_id' => $pekerjaId,
                'selesai_pada' => now(),
            ]);
    }

    private function mulaiJob(OrderItem $item, ?ProductionStage $tahap, ?int $oleh): void
    {
        if (! $tahap) {
            return;
        }

        $item->productionJobs()
            ->where('tahap', $tahap->value)
            ->where('status', ProductionStatus::ANTRI->value)
            ->update([
                'status' => ProductionStatus::DIKERJAKAN->value,
                'ditugaskan_ke' => $oleh,
                'dimulai_pada' => now(),
            ]);
    }

    private function sinkronkan(Order $order, ?string $catatan = null): void
    {
        $sebelum = $order->status;
        $sesudah = $this->resolver->sinkronkan($order, $catatan);

        if ($sesudah === OrderStatus::SIAP_DIAMBIL && $sebelum !== OrderStatus::SIAP_DIAMBIL) {
            $this->email->kirim($order->fresh(), 'pesanan-siap');
        }

        // Tanpa kabar ini pelanggan tidak tahu bahwa kini gilirannya membayar.
        if ($sesudah === OrderStatus::MENUNGGU_PELUNASAN && $sebelum !== OrderStatus::MENUNGGU_PELUNASAN) {
            $this->email->kirim($order->fresh(), 'menunggu-pelunasan');
        }
    }
}
