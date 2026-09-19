<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Contracts\View\View;

/**
 * Lembar tagihan yang bisa dicetak atau disimpan sebagai PDF (KEP-046).
 *
 * Dibuat dari data pesanan, bukan diketik ulang di Excel seperti sebelumnya.
 * File Excel lama klien menyimpan sisa template toko lain, termasuk nomor
 * rekening milik orang lain, dan rumus totalnya tidak mengurangi diskon.
 *
 * Tidak memakai pustaka PDF: peramban sudah bisa menyimpan halaman ini sebagai
 * PDF, dan menambah pustaka berarti menambah beban di shared hosting untuk
 * pekerjaan yang sudah ada alatnya.
 */
class InvoiceController extends Controller
{
    public function __invoke(Order $pesanan): View
    {
        $pesanan->load([
            'customer', 'items.options', 'items.rosters', 'payments', 'adjustments',
        ]);

        return view('panel.invoice', ['pesanan' => $pesanan]);
    }
}
