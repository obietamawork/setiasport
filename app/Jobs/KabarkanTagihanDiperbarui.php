<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\PengirimEmail;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Kabar "tagihan pesanan diperbarui" (KEP-158) setelah staf menambah
 * penyesuaian, mengisi ongkir, atau mengubah isi pesanan.
 *
 * Dijadwalkan dengan jeda dan unik per pesanan: perubahan beruntun dalam
 * jeda itu menumpang pada kabar yang sudah antre, dan isinya dibaca saat
 * dikirim, jadi pelanggan menerima satu email berisi keadaan terakhir.
 */
class KabarkanTagihanDiperbarui implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public const JEDA_MENIT = 10;

    public function __construct(public int $orderId) {}

    public function uniqueId(): string
    {
        return (string) $this->orderId;
    }

    public function handle(PengirimEmail $email): void
    {
        $order = Order::with(['customer', 'items.options', 'items.rosters', 'payments', 'adjustments'])->find($this->orderId);

        // Pesanan yang sudah selesai atau batal tidak punya tagihan untuk dikabarkan,
        // dan total yang sama dengan kabar terakhir bukan berita.
        if ($order === null || $order->status->final() || $order->total_dikabarkan === $order->total) {
            return;
        }

        $email->kirim($order, 'tagihan-diperbarui');
        $order->forceFill(['total_dikabarkan' => $order->total])->save();
    }
}
