<?php

namespace App\Services;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;

/**
 * Satu kalimat yang menjawab "sekarang saya harus apa" di halaman pesanan.
 *
 * Halaman pesanan memuat sembilan bagian yang bobotnya sama rata: barang,
 * pembayaran, pengambilan, penyesuaian, riwayat, dan seterusnya. Admin yang
 * membukanya harus membaca semuanya dulu untuk tahu giliran siapa sekarang, dan
 * itu dilakukan puluhan kali sehari.
 *
 * Aturannya DITURUNKAN dari keadaan pesanan, bukan dari kolom baru yang harus
 * dijaga tetap benar: keadaan yang dicatat dua kali pasti berselisih suatu hari.
 *
 * Yang dikembalikan hanya kalimat dan tujuan, bukan tombol yang mengerjakan
 * sesuatu. Semua tindakan tetap lewat formulir yang sudah ada berikut seluruh
 * penjagaannya (KEP-006, KEP-019); kotak ini cuma menunjuk ke sana.
 */
class LangkahBerikut
{
    /**
     * @return array{nada:string, judul:string, isi:string, tuju:?string, label:?string}
     */
    public function untuk(Order $order): array
    {
        return $this->cari($order) + ['nada' => 'biasa', 'tuju' => null, 'label' => null];
    }

    /** @return array<string, mixed> */
    private function cari(Order $order): array
    {
        if ($order->status === OrderStatus::DIBATALKAN || $order->status === OrderStatus::KADALUARSA) {
            return [
                'nada' => 'mati',
                'judul' => 'Pesanan ini sudah tidak berjalan',
                'isi' => 'Tidak ada yang perlu dikerjakan. Halaman ini disimpan sebagai catatan.',
            ];
        }

        if ($order->status === OrderStatus::SELESAI) {
            return [
                'nada' => 'selesai',
                'judul' => 'Selesai',
                'isi' => 'Barang sudah diterima pelanggan dan pembayarannya lunas.',
            ];
        }

        // Pengajuan pembatalan didahulukan di atas segalanya (KEP-041): selama
        // menggantung, konveksi tetap memotong kain yang mungkin tidak dipakai.
        if (app(KebijakanPembatalan::class)->pengajuanTertunda($order) !== null) {
            return [
                'nada' => 'mendesak',
                'judul' => 'Ada pengajuan pembatalan yang belum diputuskan',
                'isi' => 'Pekerjaan tetap berjalan selama Anda belum memutuskan. Putuskan hari ini.',
                'tuju' => '#pembatalan',
                'label' => 'Lihat pengajuan',
            ];
        }

        // Bukti transfer yang menganggur menahan seluruh pesanan: produksi
        // tidak boleh mulai sebelum uangnya diverifikasi (KEP-019).
        $buktiMenunggu = $order->payments
            ->where('status', PaymentStatus::MENUNGGU)
            ->filter(fn ($p) => filled($p->bukti_path));

        if ($buktiMenunggu->isNotEmpty()) {
            return [
                'nada' => 'mendesak',
                'judul' => 'Bukti transfer menunggu diperiksa',
                'isi' => 'Pesanan ini belum dikerjakan sampai pembayarannya Anda verifikasi.',
                'tuju' => '#pembayaran',
                'label' => 'Periksa bukti',
            ];
        }

        if ($order->status === OrderStatus::MENUNGGU_PEMBAYARAN) {
            return [
                'judul' => 'Menunggu pembayaran pelanggan',
                'isi' => $order->kadaluarsa_pada
                    ? 'Batas bayar '.$order->kadaluarsa_pada->translatedFormat('j M Y, H:i')
                        .'. Kalau uangnya diterima di toko, catat di bagian Pembayaran.'
                    : 'Kalau uangnya sudah diterima di toko, catat di bagian Pembayaran.',
                'tuju' => '#pembayaran',
                'label' => 'Catat pembayaran',
            ];
        }

        // Desain yang menunggu persetujuan pelanggan: hanya pelanggan yang
        // boleh menyetujui (KEP-011), jadi yang bisa dilakukan toko cuma
        // mengingatkan. Disebut terang-terangan supaya tidak terlihat mandek.
        $menungguDesain = $order->items
            ->where('status_item', OrderItemStatus::MENUNGGU_PERSETUJUAN)
            ->count();

        if ($menungguDesain > 0) {
            return [
                'judul' => 'Menunggu pelanggan menyetujui desain',
                'isi' => $menungguDesain.' barang menunggu persetujuan. Hanya pelanggan yang boleh menyetujui; '
                    .'kalau sudah lama diam, ingatkan lewat WhatsApp.',
                'tuju' => '#pelanggan',
                'label' => 'Kabari pelanggan',
            ];
        }

        if ($order->status === OrderStatus::DIPROSES) {
            return [
                'judul' => 'Sedang dikerjakan konveksi',
                'isi' => 'Statusnya naik sendiri begitu pekerja menandai tahapnya selesai. '
                    .'Tidak ada yang perlu Anda tekan di sini.',
                'tuju' => route('panel.antrian.index'),
                'label' => 'Lihat antrian kerja',
            ];
        }

        if ($order->status === OrderStatus::MENUNGGU_PELUNASAN) {
            return [
                'nada' => 'mendesak',
                'judul' => 'Barang sudah jadi, tinggal pelunasan '.rupiah($order->sisa_tagihan),
                'isi' => 'Kode ambil baru terbit setelah lunas (KEP-028). Tagih lewat WhatsApp, '
                    .'atau catat pembayarannya kalau uangnya diterima di toko.',
                'tuju' => '#pembayaran',
                'label' => 'Catat pelunasan',
            ];
        }

        if ($order->status === OrderStatus::SIAP_DIAMBIL) {
            if ($order->sisa_tagihan > 0) {
                return [
                    'nada' => 'mendesak',
                    'judul' => 'Sisa tagihan '.rupiah($order->sisa_tagihan).' belum lunas',
                    'isi' => 'Barang tidak boleh keluar sebelum lunas (KEP-006).',
                    'tuju' => '#pembayaran',
                    'label' => 'Catat pembayaran',
                ];
            }

            if ($order->dikirim()) {
                return [
                    'judul' => 'Siap dikirim',
                    'isi' => $order->ongkir > 0
                        ? 'Antar ke konter, lalu catat nomor resinya di bagian Pengambilan.'
                        : 'Ongkirnya belum diisi. Timbang dulu paketnya, isi ongkir, baru catat resi.',
                    'tuju' => '#pengambilan',
                    'label' => 'Catat resi',
                ];
            }

            return [
                'judul' => 'Siap diambil di toko',
                'isi' => 'Kode ambil '.($order->kode_ambil ?: '-').'. Serah terimanya dikerjakan di layar kasir.',
                'tuju' => route('panel.serah-terima'),
                'label' => 'Buka serah terima',
            ];
        }

        if ($order->status === OrderStatus::DIKIRIM) {
            return [
                'judul' => 'Paket sedang di jalan',
                'isi' => 'Resi '.($order->no_resi ?: '-').'. Tandai diterima begitu pelanggan mengabari, '
                    .'supaya pesanan ini berhenti muncul di daftar aktif.',
                'tuju' => '#pengambilan',
                'label' => 'Tandai diterima',
            ];
        }

        return [
            'judul' => $order->status->label(),
            'isi' => 'Tidak ada tindakan yang menunggu.',
        ];
    }
}
