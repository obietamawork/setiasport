<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Contracts\View\View;

/**
 * Selembar kertas per pesanan yang menempel di kain saat dikerjakan: kode,
 * nama tim, mockup kecil, dan tabel roster.
 *
 * Nilainya sangat tinggi dengan biaya sangat rendah — cukup HTML dengan CSS
 * print (docs/04). Operator produksi tidak membawa layar ke meja jahit.
 */
class WorksheetController extends Controller
{
    public function __invoke(Order $pesanan): View
    {
        $pesanan->load([
            'customer',
            'items.rosters',
            'items.options',
            'items.approvedDesign',
            'items.product',
        ]);

        return view('panel.worksheet', ['pesanan' => $pesanan]);
    }
}
