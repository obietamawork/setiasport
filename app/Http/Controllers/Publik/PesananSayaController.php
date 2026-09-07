<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class PesananSayaController extends Controller
{
    public function __invoke(): View
    {
        $pelanggan = Auth::guard('pelanggan')->user();

        return view('publik.pesanan-saya', [
            'pelanggan' => $pelanggan,
            'pesanan' => $pelanggan->orders()
                ->with(['items.options', 'adjustments', 'payments'])
                ->latest('id')
                ->paginate(15),
        ]);
    }
}
