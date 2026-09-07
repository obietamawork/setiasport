<?php

namespace App\Http\Controllers\Panel;

use App\Enums\FulfillmentType;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StokController extends Controller
{
    public function index(Request $request)
    {
        $varian = ProductVariant::query()
            ->with('product')
            ->whereHas('product', fn ($q) => $q->whereIn('fulfillment_type', [
                FulfillmentType::READY_STOCK->value, FulfillmentType::BOTH->value,
            ]))
            ->when($request->filled('q'), function ($q) use ($request) {
                $cari = '%'.$request->string('q').'%';
                $q->where(fn ($w) => $w->where('sku', 'like', $cari)
                    ->orWhereHas('product', fn ($p) => $p->where('nama', 'like', $cari)));
            })
            ->when($request->string('saring')->toString() === 'habis',
                fn ($q) => $q->whereColumn('stok', '<=', 'stok_dipesan'))
            ->get()
            ->sortBy(fn (ProductVariant $v) => $v->product->nama)
            ->groupBy('product_id')
            ->map(function ($grup) {
                // Ukuran diurutkan seperti orang membacanya (S, M, L, XL), bukan alfabetis.
                $urutan = urutkan_ukuran($grup->pluck('ukuran'));

                return $grup->sortBy(fn (ProductVariant $v) => [
                    array_search((string) $v->ukuran, $urutan, true) === false
                        ? 999
                        : array_search((string) $v->ukuran, $urutan, true),
                    (string) $v->warna,
                ])->values();
            });

        return view('panel.stok.index', ['varian' => $varian]);
    }

    /**
     * Penyesuaian stok manual. Selalu tercatat beserta alasannya — stok yang
     * berubah tanpa jejak adalah sumber perselisihan internal (docs/04).
     */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'stok' => ['required', 'array'],
            'stok.*' => ['required', 'integer', 'min:0', 'max:999999'],
            'alasan' => ['required', 'string', 'max:120'],
        ], [
            'alasan.required' => 'Tulis alasan penyesuaian, misalnya "opname 7 September" atau "barang rusak".',
        ], ['alasan' => 'alasan penyesuaian']);

        $perubahan = [];

        foreach ($data['stok'] as $id => $jumlah) {
            $varian = ProductVariant::find($id);

            if (! $varian || $varian->stok === (int) $jumlah) {
                continue;
            }

            $perubahan[] = [
                'sku' => $varian->sku,
                'dari' => $varian->stok,
                'ke' => (int) $jumlah,
            ];

            $varian->update(['stok' => (int) $jumlah]);
        }

        if ($perubahan === []) {
            return back()->with('sukses', 'Tidak ada angka stok yang berubah.');
        }

        ActivityLog::catat('stok.disesuaikan', null, [
            'alasan' => $data['alasan'],
            'perubahan' => $perubahan,
        ]);

        return back()->with('sukses', count($perubahan).' varian diperbarui dan dicatat.');
    }
}
