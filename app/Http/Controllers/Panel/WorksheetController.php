<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\KodeQr;
use Illuminate\Contracts\View\View;

/**
 * Selembar kertas per pesanan yang menempel di kain saat dikerjakan: kode,
 * nama tim, mockup kecil, dan tabel roster.
 *
 * Nilainya sangat tinggi dengan biaya sangat rendah, cukup HTML dengan CSS
 * print (docs/04). Operator produksi tidak membawa layar ke meja jahit.
 */
class WorksheetController extends Controller
{
    public function __construct(private KodeQr $qr) {}

    public function __invoke(Order $pesanan): View
    {
        $pesanan->load([
            'customer',
            'items.rosters',
            'items.options',
            'items.approvedDesign',
            'items.product',
            'items.productionJobs',
        ]);

        /*
         * Satu QR per baris pesanan (KEP-047), bukan per pesanan: yang berjalan
         * dari meja ke meja adalah barangnya, dan satu pesanan bisa memuat dua
         * barang yang tahapnya berbeda.
         */
        $qr = $pesanan->items
            ->filter(fn (\App\Models\OrderItem $i) => $i->productionJobs->isNotEmpty())
            ->mapWithKeys(fn (\App\Models\OrderItem $i) => [
                $i->id => [
                    'tautan' => route('kerja', $i->tokenKerja()),
                    'svg' => $this->qr->svg(route('kerja', $i->tokenKerja()), 150),
                ],
            ]);

        return view('panel.worksheet', ['pesanan' => $pesanan, 'qr' => $qr]);
    }
}
