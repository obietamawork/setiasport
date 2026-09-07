<?php

namespace App\Http\Controllers\Panel;

use App\Enums\FulfillmentType;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Contracts\View\View;

/**
 * Dashboard sebagai DAFTAR TINDAKAN, bukan grafik omzet (docs/04).
 *
 * Selama pesanan belum ada (fase 2), yang paling berguna tiap pagi adalah
 * kesehatan katalog: harga yang belum lengkap dan stok yang menipis.
 */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $jalurKustom = [FulfillmentType::PREORDER->value, FulfillmentType::BOTH->value];

        return view('panel.dashboard', [
            // Produk kustom tanpa tier tidak bisa dihitung harganya, jadi
            // tidak boleh tayang (KEP-010). Ini temuan paling mendesak.
            'tanpaTier' => Product::query()
                ->where('aktif', true)
                ->whereIn('fulfillment_type', $jalurKustom)
                ->whereDoesntHave('priceTiers')
                ->get(),

            'tanpaFoto' => Product::query()
                ->where('aktif', true)
                ->whereDoesntHave('images')
                ->count(),

            'stokHabis' => ProductVariant::query()
                ->with('product')
                ->whereColumn('stok', '<=', 'stok_dipesan')
                ->whereHas('product', fn ($q) => $q->where('aktif', true)
                    ->whereIn('fulfillment_type', [
                        FulfillmentType::READY_STOCK->value, FulfillmentType::BOTH->value,
                    ]))
                ->get(),

            'stokMenipis' => ProductVariant::query()
                ->with('product')
                ->whereRaw('stok - stok_dipesan between 1 and 3')
                ->whereHas('product', fn ($q) => $q->where('aktif', true))
                ->get(),

            'produkAktif' => Product::where('aktif', true)->count(),
            'produkNonaktif' => Product::where('aktif', false)->count(),
        ]);
    }
}
