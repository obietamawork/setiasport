<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Ongkir\PenyediaOngkir;
use Illuminate\Support\Collection;

/**
 * Menanyakan posisi paket ke kurir dan menyimpannya di pesanan (KEP-061).
 *
 * Sebelum ini, pelanggan yang paketnya sudah berangkat hanya melihat nomor resi
 * dan harus menyalinnya ke situs kurir sendiri. Nomor resi bukan jawaban;
 * "sudah sampai Solo kemarin sore" baru jawaban.
 *
 * Yang TIDAK dilakukan di sini: menandai pesanan selesai sendiri. Kurir bilang
 * "delivered" pun, yang menutup pesanan tetap orang toko, karena diterima oleh
 * satpam sekolah dan diterima oleh pemesannya adalah dua hal berbeda. Statusnya
 * ditampilkan di panel sebagai ajakan menandai, bukan sebagai tindakan.
 */
class SinkronPaket
{
    public function __construct(private PenyediaOngkir $penyedia) {}

    /**
     * Pesanan yang paketnya masih di jalan dan resinya sudah tercatat.
     *
     * @return Collection<int, Order>
     */
    public function perluDisegarkan(int $batas = 20): Collection
    {
        return Order::query()
            ->where('status', OrderStatus::DIKIRIM)
            ->whereNotNull('no_resi')
            ->whereNotNull('jasa_kirim')
            // Yang paling lama tidak diperbarui didahulukan, supaya batas
            // jumlah per putaran tidak membuat satu pesanan terlewat terus.
            ->orderByRaw('lacak_pada is not null, lacak_pada')
            ->limit($batas)
            ->get();
    }

    /** @return int jumlah pesanan yang berhasil diperbarui */
    public function segarkanSemua(int $batas = 20): int
    {
        if (! $this->penyedia->aktif()) {
            return 0;
        }

        return $this->perluDisegarkan($batas)
            ->filter(fn (Order $order) => $this->segarkan($order))
            ->count();
    }

    /**
     * Menyegarkan satu pesanan. Mengembalikan false kalau kurir tidak menjawab,
     * dan dalam keadaan itu data lama sengaja DIBIARKAN: "sampai Solo kemarin"
     * yang basi sehari masih jauh lebih berguna daripada layar kosong.
     */
    public function segarkan(Order $order): bool
    {
        if (blank($order->no_resi) || blank($order->jasa_kirim)) {
            return false;
        }

        $status = $this->penyedia->lacak($order->no_resi, $this->kodeKurir($order->jasa_kirim));

        if ($status === null) {
            return false;
        }

        $order->update([
            'lacak_status' => $status->status,
            'lacak_catatan' => $status->keterangan,
            // Yang disimpan hanya beberapa kejadian terakhir. Riwayat penuh satu
            // paket bisa berisi puluhan baris gudang yang tidak berarti apa pun
            // bagi pemesan, dan ikut membengkakkan tiap baris pesanan.
            'lacak_riwayat' => array_slice(array_reverse($status->riwayat), 0, 5),
            'lacak_pada' => now(),
        ]);

        return true;
    }

    /**
     * Nama jasa kirim diketik orang toko ("JNE", "J&T Express"), sedangkan
     * penyedia pelacakan menunggu kode ("jne", "jnt"). Dicocokkan di sini
     * supaya orang di kasir tidak perlu menghafal kode siapa pun.
     */
    private function kodeKurir(string $jasa): string
    {
        $bersih = preg_replace('/[^a-z]/', '', mb_strtolower($jasa));

        return match (true) {
            str_contains($bersih, 'jnt'), str_contains($bersih, 'jt') => 'jnt',
            str_contains($bersih, 'jne') => 'jne',
            str_contains($bersih, 'sicepat') => 'sicepat',
            str_contains($bersih, 'anteraja') => 'anteraja',
            str_contains($bersih, 'pos') => 'pos',
            str_contains($bersih, 'tiki') => 'tiki',
            str_contains($bersih, 'ninja') => 'ninja',
            str_contains($bersih, 'sap') => 'sap',
            str_contains($bersih, 'wahana') => 'wahana',
            str_contains($bersih, 'lion') => 'lion',
            default => $bersih,
        };
    }
}
