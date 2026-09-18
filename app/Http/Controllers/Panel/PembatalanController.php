<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Pembatalan;
use App\Services\KebijakanPembatalan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Keputusan toko atas pengajuan pembatalan (KEP-041). Nominal pengembalian
 * diketik admin, bukan dihitung otomatis lalu dieksekusi: uang keluar adalah
 * keputusan toko.
 */
class PembatalanController extends Controller
{
    public function __construct(private KebijakanPembatalan $kebijakan) {}

    public function setujui(Request $request, Order $pesanan, Pembatalan $pembatalan): RedirectResponse
    {
        $this->pastikanMilikPesanan($pesanan, $pembatalan);

        $data = $request->validate([
            'refund_jumlah' => ['required', 'integer', 'min:0', 'max:'.$pembatalan->sudah_dibayar],
            'refund_metode' => ['nullable', 'in:TRANSFER,TUNAI'],
            'catatan_admin' => ['nullable', 'string', 'max:1000'],
        ], [
            'refund_jumlah.max' => 'Pengembalian tidak boleh melebihi '
                .rupiah($pembatalan->sudah_dibayar).' yang sudah dibayar pelanggan.',
        ]);

        $this->kebijakan->setujui(
            $pembatalan,
            (int) $data['refund_jumlah'],
            $data['refund_metode'] ?? null,
            $data['catatan_admin'] ?? null,
            auth('web')->id(),
        );

        return back()->with('sukses', $data['refund_jumlah'] > 0
            ? 'Pembatalan disetujui. Kirim pengembalian '.rupiah((int) $data['refund_jumlah'])
                .' ke pelanggan, lalu beri tahu lewat WhatsApp.'
            : 'Pembatalan disetujui tanpa pengembalian uang.');
    }

    public function tolak(Request $request, Order $pesanan, Pembatalan $pembatalan): RedirectResponse
    {
        $this->pastikanMilikPesanan($pesanan, $pembatalan);

        $data = $request->validate([
            'catatan_admin' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'catatan_admin.required' => 'Tuliskan alasan penolakannya; pelanggan membacanya di halaman pesanannya.',
        ]);

        $this->kebijakan->tolak($pembatalan, $data['catatan_admin'], auth('web')->id());

        return back()->with('sukses', 'Pengajuan pembatalan ditolak dan alasannya tercatat.');
    }

    private function pastikanMilikPesanan(Order $pesanan, Pembatalan $pembatalan): void
    {
        abort_unless($pembatalan->order_id === $pesanan->id, 404);
    }
}
