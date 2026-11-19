<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Services\AlurPesanan;
use Illuminate\Http\RedirectResponse;

/**
 * Pelanggan mengonfirmasi sendiri paketnya sudah diterima (KEP-131).
 *
 * Hanya lewat tombol di halaman pesanan (POST bertoken). Email "pesanan
 * dikirim" sekadar mengarahkan ke tombol ini: tautan di email dibuka otomatis
 * oleh pemindai keamanan, dan tautan yang langsung menutup pesanan akan
 * menandai paket diterima padahal belum sampai. Tombol staf "Paket sudah
 * diterima pelanggan" (KEP-061) tetap ada untuk pelanggan yang tidak menekan.
 */
class KonfirmasiDiterimaController extends Controller
{
    public function __construct(private AlurPesanan $alur) {}

    public function __invoke(string $token): RedirectResponse
    {
        $order = Order::where('access_token', $token)
            ->with(['items.options', 'items.rosters', 'payments', 'adjustments'])
            ->firstOrFail();

        $this->alur->tandaiDiterima($order, null, 'Paket dikonfirmasi diterima oleh pelanggan.');

        ActivityLog::catat('pesanan.diterima_pelanggan', $order, ['no_resi' => $order->no_resi]);

        return back()->with('sukses', 'Terima kasih. Pesanan Anda kami catat sudah diterima.');
    }
}
