<?php

namespace App\Http\Controllers\Publik;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PesananSayaController extends Controller
{
    /** Saringan status di Pesanan saya (KEP-177), dari papan kanvas Kit Tim. */
    private const SARINGAN = ['berjalan', 'selesai', 'batal'];

    public function __invoke(Request $request): View
    {
        $pelanggan = Auth::guard('pelanggan')->user();
        $saringan = in_array($request->query('status'), self::SARINGAN, true) ? $request->query('status') : '';

        $perStatus = $pelanggan->orders()->toBase()
            ->selectRaw('status, COUNT(*) AS jumlah')
            ->groupBy('status')
            ->pluck('jumlah', 'status');
        $hitung = fn (OrderStatus ...$status) => (int) collect($status)->sum(fn (OrderStatus $s) => $perStatus[$s->value] ?? 0);
        $semua = (int) $perStatus->sum();
        $selesai = $hitung(OrderStatus::SELESAI);
        $batal = $hitung(OrderStatus::DIBATALKAN, OrderStatus::KADALUARSA);

        return view('publik.pesanan-saya', [
            'pelanggan' => $pelanggan,
            'saringan' => $saringan,
            'jumlah' => ['' => $semua, 'berjalan' => $semua - $selesai - $batal, 'selesai' => $selesai, 'batal' => $batal],
            'pesanan' => $pelanggan->orders()
                // Foto di kartu pesanan (KEP-176) dan tombol aksinya (KEP-177).
                ->with(['items.options', 'items.rosters', 'items.approvedDesign', 'items.product.images', 'adjustments', 'payments'])
                ->when($saringan === 'berjalan', fn ($q) => $q->aktif())
                ->when($saringan === 'selesai', fn ($q) => $q->where('status', OrderStatus::SELESAI))
                ->when($saringan === 'batal', fn ($q) => $q->whereIn('status', [OrderStatus::DIBATALKAN, OrderStatus::KADALUARSA]))
                ->latest('id')
                ->paginate(15)
                ->withQueryString(),
        ]);
    }
}
