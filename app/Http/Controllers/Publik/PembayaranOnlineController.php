<?php

namespace App\Http\Controllers\Publik;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Pembayaran\GerbangPembayaran;
use Illuminate\Http\RedirectResponse;
use Throwable;

class PembayaranOnlineController extends Controller
{
    public function __construct(private GerbangPembayaran $gerbang) {}

    /** Pelanggan memilih bayar online; kami buatkan transaksinya lalu antar ke sana. */
    public function mulai(string $token): RedirectResponse
    {
        abort_unless($this->gerbang->aktif(), 404);

        $order = Order::where('access_token', $token)->firstOrFail();

        $payment = $order->payments()
            ->where('status', PaymentStatus::MENUNGGU)
            ->latest('id')
            ->first();

        if (! $payment) {
            return back()->withErrors('Tidak ada tagihan yang menunggu pembayaran pada pesanan ini.');
        }

        try {
            return redirect()->away($this->gerbang->buatTransaksi($payment));
        } catch (Throwable $e) {
            report($e);

            // Transfer manual tetap tersedia, jadi kegagalan gateway bukan
            // jalan buntu bagi pelanggan.
            return back()->withErrors(
                'Pembayaran online sedang tidak bisa dibuka. Silakan transfer manual '
                .'dan unggah buktinya, atau coba lagi beberapa saat lagi.'
            );
        }
    }
}
