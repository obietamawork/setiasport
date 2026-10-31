<?php

namespace App\Services;

use App\Enums\DesignAssetType;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\ProductionStage;
use App\Enums\ProductionStatus;
use App\Enums\StatusPembatalan;
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

    /**
     * Kasir memverifikasi pembayaran masuk. Pekerjaan baru dimulai di sini.
     *
     * $tambahan diisi field Payment lain yang pemanggil perlu ubah bersamaan
     * (metode, bukti_path, atau jumlah kalau baru ditetapkan saat ini, seperti
     * di pembayaran manual kasir) — SELALU lewat sini, tidak pernah lewat
     * `$payment->update()` terpisah sebelum memanggil method ini. Kalau
     * diubah terpisah, perubahan itu sudah tersimpan duluan (bukan di dalam
     * transaksi terkunci di bawah) dan tidak ikut batal ketika pemeriksaan
     * sisa tagihan menolak permintaannya — bukti lama bisa sudah terbuang,
     * atau baris pembayaran baru (dari kasir) tertinggal dengan field yang
     * sudah berubah padahal status tetap Menunggu.
     */
    public function terimaPembayaran(Payment $payment, ?int $olehUserId = null, array $tambahan = []): void
    {
        DB::transaction(function () use ($payment, $olehUserId, $tambahan) {
            /*
             * Baris pesanan dikunci SEBELUM sisa tagihan dibaca. Tanpa ini,
             * dua pembayaran untuk pesanan yang sama diverifikasi hampir
             * bersamaan bisa sama-sama membaca sisa tagihan yang belum
             * diperbarui satu sama lain, dan keduanya lolos pemeriksaan
             * padahal digabung melebihi total pesanan (race condition /
             * TOCTOU). lockForUpdate membuat permintaan kedua menunggu
             * sampai transaksi pertama selesai, baru membaca angka yang
             * sudah diperbarui.
             */
            $order = Order::whereKey($payment->order_id)->lockForUpdate()->firstOrFail()
                ->load(['items.options', 'payments', 'adjustments']);

            /*
             * Dibaca ulang SETELAH pesanan terkunci (KEP-098). Halaman panel
             * yang terbuka sejak pagi masih menampilkan tombol Verifikasi untuk
             * tagihan yang sudah dibatalkan penjadwal, dan menekannya dulu
             * mencatat uang lunas di pesanan yang stoknya sudah dilepas.
             */
            $payment->refresh();

            abort_unless($payment->status === PaymentStatus::MENUNGGU, 422,
                'Tagihan ini sudah tidak menunggu pembayaran. Muat ulang halaman pesanannya.');

            abort_if($order->status->final(), 422,
                'Pesanan ini sudah '.mb_strtolower($order->status->label()).', jadi pembayarannya tidak bisa '
                .'dicatat lagi. Kalau uangnya memang masuk, kembalikan ke pelanggan atau buatkan pesanan baru.');

            // Kalau $tambahan menetapkan jumlah baru (pembayaran manual
            // kasir), itulah yang diperiksa -- bukan jumlah lama di memori.
            $jumlah = $tambahan['jumlah'] ?? $payment->jumlah;

            abort_if($jumlah > $order->sisa_tagihan, 422,
                'Nominal tagihan ini ('.rupiah($jumlah).') melebihi sisa tagihan pesanan saat ini '
                .'('.rupiah($order->sisa_tagihan).'). Pesanan ini sepertinya sudah dibayar atau berkurang '
                .'lewat jalur lain sejak tagihan ini dibuat. Periksa riwayat pembayaran dan penyesuaian '
                .'sebelum melanjutkan.');

            // $tambahan ditulis LEBIH DULU, status/diverifikasi_oleh SESUDAH:
            // pemanggil tidak boleh menimpa keduanya lewat $tambahan.
            $payment->update([
                ...$tambahan,
                'status' => PaymentStatus::LUNAS,
                'diverifikasi_oleh' => $olehUserId,
                'dibayar_pada' => $tambahan['dibayar_pada'] ?? $payment->dibayar_pada ?? now(),
            ]);

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

            // Sesudah status disinkronkan: tagihan awal yang lain baru bisa
            // dikenali basi setelah pesanan keluar dari Menunggu Pembayaran.
            $this->rapikanTagihan($order->fresh(['items.options', 'payments', 'adjustments']));
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
     * Bukti pembayaran tidak cocok dengan mutasi (KEP-097).
     *
     * Dulu hanya ditandai gagal: alasannya tersimpan di log staf, tagihan untuk
     * pesanan baru tidak ada lagi, form unggah di halaman pesanan hilang, dan
     * pesanan dibatalkan otomatis begitu batas bayarnya lewat. Satu salah
     * transfer berakhir jadi pesanan hangus. Kini alasannya disimpan dan
     * dikirim ke pelanggan, dan tagihan awal yang ditolak diganti tagihan baru
     * dengan nominal sama serta batas bayar yang dimulai ulang.
     *
     * Pelunasan tidak butuh tagihan pengganti: halaman pesanan membacanya
     * langsung dari sisa tagihan, dan unggahan berikutnya membuat barisnya.
     */
    public function tolakPembayaran(Payment $payment, string $alasan, ?int $olehUserId = null): void
    {
        DB::transaction(function () use ($payment, $alasan, $olehUserId) {
            $order = Order::whereKey($payment->order_id)->lockForUpdate()->firstOrFail();
            $payment->refresh();

            abort_unless($payment->status === PaymentStatus::MENUNGGU, 422,
                'Pembayaran ini sudah tidak menunggu verifikasi.');

            $payment->update([
                'status' => PaymentStatus::GAGAL,
                'alasan_tolak' => $alasan,
                'diverifikasi_oleh' => $olehUserId,
            ]);

            if ($order->status === OrderStatus::MENUNGGU_PEMBAYARAN && $payment->jenis !== PaymentType::PELUNASAN) {
                // Pesanan yang diketik admin tidak punya batas bayar (KEP-054).
                $batas = $order->kadaluarsa_pada
                    ? now()->addHours((int) Setting::ambil('batas_bayar_jam', 24))
                    : null;

                $order->update(['kadaluarsa_pada' => $batas]);

                $order->payments()->create([
                    'jenis' => $payment->jenis,
                    'metode' => PaymentMethod::TRANSFER,
                    'status' => PaymentStatus::MENUNGGU,
                    'jumlah' => $payment->jumlah,
                    'kadaluarsa_pada' => $batas,
                ]);

                // Nominal lama bisa sudah basi kalau tagihannya berubah selama
                // bukti itu menunggu diperiksa.
                $this->rapikanTagihan($order->fresh(['items.options', 'payments', 'adjustments']));
            }
        });

        $this->email->kirim(Order::findOrFail($payment->order_id), 'pembayaran-ditolak');
    }

    /**
     * Tagihan yang masih Menunggu diselaraskan dengan pesanan saat ini
     * (KEP-067 lanjutan, KEP-098). Halaman pesanan pelanggan menampilkan
     * nominal tagihan apa adanya, jadi angka yang basi langsung berubah jadi
     * transfer yang salah:
     *
     * - Begitu sisa tagihan nol, semua tagihan ditutup.
     * - Tagihan awal yang tujuannya sudah tercapai lewat jalur lain, misalnya
     *   DP yang dibayar tunai di kasir, ditutup. Dulu halaman pesanan tetap
     *   meminta DP yang sudah dibayar, lengkap dengan rekening dan QRIS.
     * - Nominalnya mengikuti tagihan terkini: potongan reseller, ongkir yang
     *   menyusul, atau jumlah yang diubah. Dulu potongan sebelum pelanggan
     *   membayar membuat tagihan lebih besar dari sisa tagihan, dan
     *   verifikasinya ditolak selamanya.
     *
     * Tagihan yang buktinya sudah diunggah tidak diubah nominalnya: pelanggan
     * sudah mentransfer angka yang tertulis, dan admin mencatat nominal yang
     * benar-benar masuk saat memverifikasi.
     */
    public function rapikanTagihan(Order $order): void
    {
        $order->loadMissing(['items.options', 'items.rosters', 'payments', 'adjustments']);

        /** @var Payment $tagihan */
        foreach ($order->payments->where('status', PaymentStatus::MENUNGGU) as $tagihan) {
            $adaBukti = filled($tagihan->bukti_path);

            $basi = $order->sisa_tagihan < 1
                || (! $adaBukti
                    && $tagihan->jenis !== PaymentType::PELUNASAN
                    && $order->status !== OrderStatus::MENUNGGU_PEMBAYARAN);

            if ($basi) {
                $tagihan->update(['status' => PaymentStatus::KADALUARSA]);

                continue;
            }

            if ($adaBukti) {
                continue;
            }

            $seharusnya = $tagihan->jenis === PaymentType::DP
                ? min($order->dpMinimum(), $order->sisa_tagihan)
                : $order->sisa_tagihan;

            if ($seharusnya !== (int) $tagihan->jumlah) {
                $tagihan->update(['jumlah' => $seharusnya]);
            }
        }
    }

    /**
     * Total pesanan berubah setelah dibuat: penyesuaian biaya, jumlah barang,
     * atau ongkir (KEP-098).
     *
     * Gerbang pelunasan ikut diperiksa, karena potongan yang membuat sisa
     * tagihan nol juga melunasi pesanan. Dulu barang yang menunggu pelunasan
     * tertahan selamanya: gerbangnya hanya terbuka lewat pembayaran, dan
     * pembayaran nol rupiah tidak bisa dicatat.
     */
    public function totalBerubah(Order $order, ?int $olehUserId = null): void
    {
        DB::transaction(function () use ($order, $olehUserId) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            $this->lepaskanPelunasan($order->fresh(['items.options', 'payments', 'adjustments']), $olehUserId);
            $this->sinkronkan($order->fresh(['items.options', 'payments', 'adjustments']));
            $this->rapikanTagihan($order->fresh(['items.options', 'payments', 'adjustments']));
        });
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

    /**
     * Operator menyelesaikan satu tahap; status pelanggan naik sebagai akibatnya.
     *
     * $dari adalah tahap yang dilihat orang itu di layarnya saat menekan
     * tombol (KEP-098). Tanpa pembanding ini, ketukan kedua pada tombol
     * Selesai, atau dua orang yang menandai barang yang sama, menaikkan barang
     * satu tahap lagi: dari Printing langsung ke Potong, melompati Press.
     */
    public function majukanItem(
        OrderItem $item,
        ?int $olehUserId = null,
        ?int $pekerjaId = null,
        ?OrderItemStatus $dari = null,
    ): OrderItemStatus {
        abort_if($dari !== null && $item->status_item !== $dari, 409, $this->sudahBerpindah($item));

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

        /*
         * Tahap Desain tidak boleh "selesai" lewat tombol generik (QR/PIN atau
         * tombol Antrian) tanpa mockup yang benar-benar terunggah. Tanpa
         * penjagaan ini, item lompat ke Menunggu Persetujuan Pelanggan dengan
         * kotak persetujuan yang kosong -- pelanggan diminta menyetujui desain
         * yang belum pernah mereka lihat sama sekali. Mengirim mockup (lewat
         * MockupController) adalah SATU-SATUNYA jalan sah memajukan tahap ini.
         */
        abort_if(
            $item->status_item === OrderItemStatus::PROSES_DESAIN
                && $item->designAssets()->where('tipe', DesignAssetType::MOCKUP)->doesntExist(),
            422,
            'Belum ada mockup yang diunggah. Kirim mockupnya dulu lewat formulir "Kirim untuk disetujui" '
            .'di Antrian Desain -- tombol ini tidak memajukan tahap Desain.'
        );

        // Pesanan yang sudah lunas di muka tidak perlu berhenti di gerbang.
        if ($berikutnya === OrderItemStatus::MENUNGGU_PELUNASAN
            && $item->order->fresh(['items.options', 'payments', 'adjustments'])->lunas()) {
            $berikutnya = OrderItemStatus::SIAP;
        }

        DB::transaction(function () use ($item, $berikutnya, $olehUserId, $pekerjaId) {
            // Dua ketukan yang tiba bersamaan sama-sama lolos pembanding di
            // atas; baris yang terkunci membuat yang kedua melihat hasil yang
            // pertama.
            $this->pastikanMasihDi($item);

            // Tahap yang DITINGGALKAN-lah yang selesai; tahap yang dimasuki baru dimulai.
            $this->selesaikanJob($item, $item->status_item->tahapProduksi(), $olehUserId, $pekerjaId);
            $this->setStatusItem($item, $berikutnya, null, $olehUserId);
            $this->mulaiJob($item, $berikutnya->tahapProduksi(), $olehUserId);

            $this->sinkronkan($item->order->fresh(['items.options', 'payments', 'adjustments']));
        });

        return $berikutnya;
    }

    /**
     * Barang dikembalikan satu tahap (KEP-098): sablon luntur ketahuan di
     * Finishing, kain salah potong, atau tombol Selesai yang tertekan untuk
     * barang yang salah. Dulu tidak ada jalan mundur sama sekali, jadi barang
     * yang dikerjakan ulang tercatat sudah lewat tahap yang sedang diulang.
     *
     * Tidak pernah melintasi gerbang: desain yang sudah disetujui pelanggan
     * tidak dibuka lagi dari sini, dan gerbang pelunasan dilewati karena
     * yang diulang adalah pekerjaannya, bukan pembayarannya. Alasannya
     * disimpan untuk staf; pelanggan hanya melihat tahapnya kembali.
     */
    public function mundurkanItem(
        OrderItem $item,
        string $alasan,
        ?int $olehUserId = null,
        ?OrderItemStatus $dari = null,
    ): OrderItemStatus {
        abort_if($dari !== null && $item->status_item !== $dari, 409, $this->sudahBerpindah($item));
        abort_if($item->order->status->final() || $item->order->status === OrderStatus::DIKIRIM, 422,
            'Pesanan ini sudah keluar dari toko atau dibatalkan.');

        $sebelumnya = $item->status_item->sebelumnya($item->fulfillment_type);

        abort_if($sebelumnya === null, 422, 'Barang di tahap '.$item->status_item->labelStaf()
            .' tidak bisa dikembalikan dari sini.');

        DB::transaction(function () use ($item, $sebelumnya, $alasan, $olehUserId) {
            $this->pastikanMasihDi($item);

            // Tahap yang ditinggalkan belum dikerjakan; tahap tujuan dibuka lagi.
            if ($kini = $item->status_item->tahapProduksi()) {
                $item->productionJobs()->where('tahap', $kini->value)->update([
                    'status' => ProductionStatus::ANTRI->value,
                    'ditugaskan_ke' => null,
                    'dimulai_pada' => null,
                ]);
            }

            if ($tujuan = $sebelumnya->tahapProduksi()) {
                $item->productionJobs()->where('tahap', $tujuan->value)->update([
                    'status' => ProductionStatus::DIKERJAKAN->value,
                    'diselesaikan_oleh' => null,
                    'pekerja_id' => null,
                    'selesai_pada' => null,
                ]);
            }

            $this->setStatusItem($item, $sebelumnya, 'Dikembalikan: '.$alasan, $olehUserId);

            // Barang yang ditarik dari rak siap tidak boleh membawa tanggal
            // siapnya yang lama: pengingat ambil dan batas hangus dihitung
            // dari situ, dan email "pesanan siap" harus terkirim lagi.
            $order = Order::with(['items.options', 'payments', 'adjustments'])->findOrFail($item->order_id);

            if ($order->status === OrderStatus::SIAP_DIAMBIL) {
                $order->siap_pada = null;
            }

            $this->sinkronkan($order);
        });

        return $sebelumnya;
    }

    /** Pembanding $dari di majukanItem() dan mundurkanItem(). */
    private function sudahBerpindah(OrderItem $item): string
    {
        return $item->nama_produk.' sudah berada di tahap '.$item->status_item->labelStaf()
            .'. Mungkin tombolnya tertekan dua kali, atau orang lain lebih dulu menandainya.';
    }

    private function pastikanMasihDi(OrderItem $item): void
    {
        $diDb = OrderItem::whereKey($item->id)->lockForUpdate()->firstOrFail();

        abort_if($diDb->status_item !== $item->status_item, 409, $this->sudahBerpindah($diDb));
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
            $this->kunci($order, fn (Order $o) => $o->status === OrderStatus::SIAP_DIAMBIL,
                'Pesanan ini sudah diserahkan. Muat ulang halamannya.');

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
            $this->kunci($order, fn (Order $o) => $o->status === OrderStatus::SIAP_DIAMBIL,
                'Resi pesanan ini sudah dicatat. Muat ulang halamannya.');

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
            $this->kunci($order, fn (Order $o) => ! $o->status->final(),
                'Pesanan ini sudah selesai atau dibatalkan.');

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
     * Pesanan yang tidak dibayar sampai batas waktunya (dipanggil penjadwal).
     *
     * Pesanan yang buktinya sudah diunggah TIDAK dibatalkan (KEP-098): uangnya
     * sudah dikirim tepat waktu, yang belum hanya pemeriksaan dari toko. Dulu
     * pelanggan yang membayar Sabtu malam kehilangan pesanannya Minggu malam
     * karena toko tutup, dan stoknya ikut dilepas ke pembeli lain. Kalau
     * buktinya kemudian ditolak, batas bayarnya dimulai ulang
     * (tolakPembayaran()).
     *
     * Semua syaratnya dibaca ulang di bawah kunci: verifikasi yang ditekan
     * admin tepat saat penjadwal berjalan tidak boleh berakhir dengan pesanan
     * lunas yang dibatalkan.
     *
     * @return bool apakah pesanan ini benar-benar dikadaluarsakan
     */
    public function kadaluarsakan(Order $order): bool
    {
        return DB::transaction(function () use ($order) {
            $terkini = Order::whereKey($order->id)->lockForUpdate()->with('payments')->firstOrFail();

            $masihBerlaku = $terkini->status !== OrderStatus::MENUNGGU_PEMBAYARAN
                || $terkini->kadaluarsa_pada === null
                || $terkini->kadaluarsa_pada->isFuture()
                || $terkini->payments->contains(fn (Payment $p) => $p->status === PaymentStatus::MENUNGGU
                    && filled($p->bukti_path));

            if ($masihBerlaku) {
                return false;
            }

            $this->batalkan($terkini, 'Kadaluarsa: tidak dibayar sampai batas waktu.');
            $this->tutupPengajuanPembatalan($terkini, 'Pesanan dibatalkan otomatis karena tidak dibayar.');
            $terkini->update(['status' => OrderStatus::KADALUARSA]);

            return true;
        });
    }

    /**
     * Pengajuan pembatalan yang tidak sempat diputuskan karena pesanannya
     * berakhir lewat jalan lain. Dulu ia tertinggal berstatus Diajukan dan
     * tampil di dashboard selamanya, padahal tombol Setujui-nya pasti gagal.
     */
    private function tutupPengajuanPembatalan(Order $order, string $catatan): void
    {
        $order->pembatalans()
            ->where('status', StatusPembatalan::DIAJUKAN->value)
            ->update([
                'status' => StatusPembatalan::DISETUJUI->value,
                'refund_jumlah' => 0,
                'catatan_admin' => $catatan,
                'diputuskan_pada' => now(),
            ]);
    }

    /**
     * Baris pesanan dikunci lalu keadaannya dibaca ulang (KEP-098). Dua kasir
     * yang menekan Serahkan untuk pesanan yang sama hampir bersamaan dulu
     * sama-sama lolos pemeriksaan yang dibaca sebelum transaksi, dan stoknya
     * keluar dua kali.
     *
     * @param  callable(Order): bool  $syarat
     */
    private function kunci(Order $order, callable $syarat, string $pesan): void
    {
        $terkini = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

        abort_unless($syarat($terkini), 409, $pesan);
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
        abort_unless($order->status === OrderStatus::SIAP_DIAMBIL && ! $order->dikirim(), 422,
            'Hanya pesanan yang sudah siap diambil di toko yang bisa dihanguskan.');

        $hari = (int) Setting::ambil('hangus_hari', 30);

        $this->batalkan(
            $order,
            "Barang tidak diambil lebih dari {$hari} hari, dinyatakan hangus.",
            $olehUserId,
        );

        $this->tutupPengajuanPembatalan($order, 'Barang tidak diambil dan dinyatakan hangus.');

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
