<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\View\View;

class BerandaController extends Controller
{
    public function __invoke(): View
    {
        $kategori = Category::query()
            ->whereNull('parent_id')
            ->where('aktif', true)
            ->withCount(['products' => fn ($q) => $q->where('aktif', true)])
            ->orderBy('urutan')
            ->get();

        // Kategori induk tidak memuat produk secara langsung; hitung lewat anaknya.
        $kategori->each(function (Category $k) {
            $k->products_count = Product::query()
                ->where('aktif', true)
                ->whereIn('category_id', Category::where('parent_id', $k->id)->pluck('id')->push($k->id))
                ->count();
        });

        $produk = Product::query()
            ->aktif()
            ->with(['images', 'priceTiers'])
            ->latest('id')
            ->take(6)
            ->get();

        return view('publik.beranda', compact('kategori', 'produk'));
    }
}
