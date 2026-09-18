<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\KebijakanPembatalan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Pengajuan pembatalan dari halaman pesanan pelanggan (KEP-041).
 *
 * Yang terjadi di sini hanya pengajuan. Pesanannya belum batal, stoknya belum
 * dilepas, dan pekerjaan belum berhenti sampai toko memutuskan.
 */
class PembatalanController extends Controller
{
    public function __construct(private KebijakanPembatalan $kebijakan) {}

    public function ajukan(Request $request, string $token): RedirectResponse
    {
        $order = Order::where('access_token', $token)
            ->with(['items.options', 'payments', 'adjustments'])
            ->firstOrFail();

        $data = $request->validate([
            'alasan' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'alasan.required' => 'Tuliskan alasan pembatalannya supaya toko bisa menindaklanjuti.',
            'alasan.min' => 'Alasannya terlalu singkat. Tuliskan sedikit lebih jelas.',
        ]);

        $this->kebijakan->ajukan($order, $data['alasan']);

        return back()->with('sukses',
            'Pengajuan pembatalan terkirim. Toko akan mengabari keputusannya di halaman ini.');
    }
}
