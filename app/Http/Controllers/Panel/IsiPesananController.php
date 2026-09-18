<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\UbahIsiPesanan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Menambah atau mengurangi isi pesanan yang sudah berjalan (KEP-044).
 * Pekerjaan admin, bukan pelanggan: yang berubah di sini nilai tagihan.
 */
class IsiPesananController extends Controller
{
    public function __construct(private UbahIsiPesanan $ubah) {}

    public function ubah(Request $request, Order $pesanan, OrderItem $item): RedirectResponse
    {
        abort_unless($item->order_id === $pesanan->id, 404);

        $item->load(['order', 'rosters', 'options', 'product']);

        if ($item->hargaPerUkuran()) {
            $data = $request->validate([
                'baris' => ['required', 'array', 'min:1'],
                'baris.*.kategori' => ['nullable', 'string', 'max:10'],
                'baris.*.ukuran' => ['required', 'string', 'max:10'],
                'baris.*.jumlah' => ['required', 'integer', 'min:0', 'max:5000'],
                'baris.*.nama_punggung' => ['nullable', 'string', 'max:60'],
                'baris.*.nomor' => ['nullable', 'string', 'max:5'],
            ]);

            $this->ubah->ubahUkuran($item, $data['baris'], auth('web')->id());
        } else {
            $data = $request->validate([
                'qty' => ['required', 'integer', 'min:1', 'max:5000'],
            ], [], ['qty' => 'jumlah']);

            $this->ubah->ubahQtyStok($item, (int) $data['qty'], auth('web')->id());
        }

        $pesanan->refresh()->load(['items.options', 'items.rosters', 'payments', 'adjustments']);

        $pesan = 'Jumlah '.$item->fresh()->nama_produk.' diperbarui. Tagihan sekarang '
            .rupiah($pesanan->total).'.';

        if ($pesanan->sudah_dibayar > $pesanan->total) {
            $pesan .= ' Pelanggan kelebihan bayar '
                .rupiah($pesanan->sudah_dibayar - $pesanan->total).', perlu dikembalikan.';
        }

        return back()->with('sukses', $pesan);
    }
}
