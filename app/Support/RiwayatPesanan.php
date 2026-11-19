<?php

namespace App\Support;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusLog;
use Illuminate\Support\Carbon;

/**
 * Riwayat yang dibaca pelanggan di halaman lacak (KEP-127): satu baris per
 * kejadian dengan ikon, judul, dan satu kalimat, dari yang terlama.
 *
 * Hanya yang sudah terjadi; tahap yang belum datang sudah terlihat di deret
 * tahap di atasnya. Catatan staf (alasan mundur, catatan Perbarui progres,
 * alasan pembatalan dari toko) tidak pernah ikut: yang tampil hanya
 * perubahan tahap dan catatan perubahan isi pesanan yang memang ditulis
 * untuk pelanggan (KEP-044).
 */
final class RiwayatPesanan
{
    /**
     * Tahap barang: ikon, judul, kalimat. Status yang tidak ada di sini
     * (menunggu pembayaran, selesai) diwakili baris tingkat pesanan.
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    private const TAHAP_BARANG = [
        'DIPROSES' => ['produk', 'Pesanan disiapkan', 'Barang sedang disiapkan.'],
        'ANTRIAN_DESAIN' => ['gambar', 'Masuk antrean desain', 'Pesanan menunggu giliran desainer.'],
        'PROSES_DESAIN' => ['pensil', 'Desain sedang dibuat', 'Desainer sedang menyiapkan mockup.'],
        'MENUNGGU_PERSETUJUAN' => ['mata', 'Mockup siap diperiksa', 'Mockup menunggu persetujuan Anda.'],
        'ANTRIAN_PRODUKSI' => ['centang', 'Desain disetujui', 'Desain disetujui dan pesanan masuk antrean produksi.'],
        'PRINTING' => ['cetak', 'Proses printing', 'Desain sedang dicetak.'],
        'PRESS' => ['stok', 'Proses press / sublim', 'Hasil cetak sedang dipress ke kain.'],
        'POTONG' => ['kategori', 'Potong kain', 'Kain sedang dipotong sesuai pola.'],
        'JAHIT' => ['antrian', 'Proses jahit', 'Kain sedang dijahit.'],
        'QC' => ['centang', 'Finishing & packing', 'Jahitan dirapikan, diperiksa, lalu dikemas.'],
        'MENUNGGU_PELUNASAN' => ['dompet', 'Produksi selesai', 'Barang sudah jadi, tinggal pelunasan.'],
        'PACKING' => ['produk', 'Dikemas', 'Barang sedang dikemas.'],
        'SIAP' => ['toko', 'Barang siap', 'Barang sudah siap.'],
        'DIBATALKAN' => ['silang', 'Barang dibatalkan', 'Barang ini dibatalkan.'],
    ];

    /**
     * Tingkat pesanan: hanya yang tidak terwakili tahap barang.
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    private const TAHAP_PESANAN = [
        'DIPROSES' => ['uang', 'Pembayaran diterima', 'Pembayaran sudah kami terima, pesanan mulai diproses.'],
        'SIAP_DIAMBIL' => ['toko', 'Siap diambil', 'Pesanan sudah siap. Tunjukkan kode ambil di toko.'],
        'DIKIRIM' => ['kirim', 'Pesanan dikirim', 'Paket sudah diserahkan ke kurir.'],
        'SELESAI' => ['centang', 'Pesanan selesai', 'Pesanan sudah diterima. Terima kasih.'],
        'DIBATALKAN' => ['silang', 'Pesanan dibatalkan', 'Pesanan ini dibatalkan.'],
        'KADALUARSA' => ['jam', 'Pesanan kedaluwarsa', 'Batas pembayaran lewat, pesanan dibatalkan otomatis.'],
    ];

    /**
     * @return list<array{ikon: string, judul: string, kalimat: string, waktu: Carbon}>
     */
    public static function untuk(Order $order): array
    {
        $banyakBarang = $order->items->count() > 1;

        /** @var Carbon $dibuat */
        $dibuat = $order->created_at;

        $baris = [[
            'ikon' => 'centang',
            'judul' => 'Pesanan berhasil dibuat',
            'kalimat' => 'Pesanan dengan nomor '.$order->kode.' telah diterima.',
            'waktu' => $dibuat,
        ]];

        /** @var OrderStatusLog $log */
        foreach ($order->statusLogs as $log) {
            /** @var Carbon|null $waktu */
            $waktu = $log->created_at;

            if ($waktu === null) {
                continue;
            }

            $tingkatBarang = $log->order_item_id !== null;

            // Catatan perubahan isi pesanan (KEP-044): status tidak berpindah.
            if ($tingkatBarang && $log->dari === $log->ke && filled($log->catatan)) {
                $baris[] = ['ikon' => 'pensil', 'judul' => 'Pesanan diperbarui', 'kalimat' => (string) $log->catatan, 'waktu' => $waktu];

                continue;
            }

            $peta = $tingkatBarang ? self::TAHAP_BARANG : self::TAHAP_PESANAN;
            $isi = $peta[(string) $log->ke] ?? null;

            if ($isi === null) {
                continue;
            }

            [$ikon, $judul, $kalimat] = $isi;

            // Pesanan satu barang: "siap" sudah diwakili baris tingkat pesanan.
            if ($tingkatBarang && $log->ke === OrderItemStatus::SIAP->value && ! $banyakBarang) {
                continue;
            }

            if (! $tingkatBarang && $log->ke === OrderStatus::SIAP_DIAMBIL->value && $order->dikirim()) {
                [$judul, $kalimat] = ['Siap dikirim', 'Pesanan sudah siap dan sedang disiapkan untuk kurir.'];
            }

            /** @var OrderItem|null $barang */
            $barang = $log->orderItem;

            if ($tingkatBarang && $banyakBarang && $barang) {
                $kalimat = $barang->nama_produk.': '.lcfirst($kalimat);
            }

            $baris[] = ['ikon' => $ikon, 'judul' => $judul, 'kalimat' => $kalimat, 'waktu' => $waktu];
        }

        usort($baris, fn (array $a, array $b) => $a['waktu'] <=> $b['waktu']);

        return $baris;
    }
}
