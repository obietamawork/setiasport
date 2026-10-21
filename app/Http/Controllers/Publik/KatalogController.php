<?php

namespace App\Http\Controllers\Publik;

use App\Enums\FulfillmentType;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class KatalogController extends Controller
{
    public function index(Request $request): View
    {
        $kategoriTerpilih = $request->filled('kategori')
            ? Category::where('slug', $request->string('kategori'))->first()
            : null;

        $jalur = $request->string('jalur')->toString();

        $produk = Product::query()
            ->aktif()
            ->with(['images', 'priceTiers', 'prices'])
            ->when($kategoriTerpilih, function ($q) use ($kategoriTerpilih) {
                // Memilih kategori induk ikut menampilkan seluruh anaknya.
                $ids = Category::where('parent_id', $kategoriTerpilih->id)
                    ->pluck('id')
                    ->push($kategoriTerpilih->id);

                $q->whereIn('category_id', $ids);
            })
            ->when($jalur === 'stok', fn ($q) => $q->whereIn('fulfillment_type', [
                FulfillmentType::READY_STOCK->value, FulfillmentType::BOTH->value,
            ]))
            ->when($jalur === 'kustom', fn ($q) => $q->whereIn('fulfillment_type', [
                FulfillmentType::PREORDER->value, FulfillmentType::BOTH->value,
            ]))
            ->when($request->filled('q'), function ($q) use ($request) {
                $cari = '%'.$request->string('q').'%';
                $q->where(fn ($w) => $w->where('nama', 'like', $cari)->orWhere('sku', 'like', $cari));
            })
            ->orderBy('nama')
            ->paginate(24);

        [$judul, $subjudul] = $this->judul($kategoriTerpilih, $jalur);

        return view('publik.katalog', [
            'produk' => $produk,
            'judul' => $judul,
            'judulTab' => $this->judulTab($request, $kategoriTerpilih, $jalur, $produk->currentPage()),
            'subjudul' => $subjudul,
            // Jumlah awal diambil dari MOQ terkecil, jadi angkanya masuk akal
            // sejak halaman dibuka dan tidak menampilkan peringatan minimum.
            'qtyAwal' => (int) (Product::query()
                ->aktif()
                ->whereIn('fulfillment_type', [
                    FulfillmentType::PREORDER->value, FulfillmentType::BOTH->value,
                ])
                ->min('moq') ?: 12),
            // Pembeda dua jalur pindah ke sini dari halaman depan (KEP-024).
            'leadTime' => Product::query()
                ->aktif()
                ->whereIn('fulfillment_type', [
                    FulfillmentType::PREORDER->value, FulfillmentType::BOTH->value,
                ])
                ->min('lead_time_hari'),
            'semuaKategori' => Category::with('parent')
                ->whereNotNull('parent_id')
                ->where('aktif', true)
                ->orderBy('nama')
                ->get(),
        ]);
    }

    public function show(Product $produk): View
    {
        abort_unless($produk->aktif, 404);

        $produk->load(['category.parent', 'images', 'variants', 'priceTiers', 'options.values.material', 'prices']);

        return view('publik.produk', compact('produk'));
    }

    /**
     * Judul tab dan hasil penelusuran, terpisah dari judul besar di halaman.
     *
     * Nama kategori anak tidak unik ("Futsal" ada di bawah Bola dan di bawah
     * Jersey), hasil pencarian tidak boleh berjudul "Katalog" yang sama dengan
     * katalog tanpa pencarian, dan halaman 2 tidak boleh kembar dengan
     * halaman 1. "Custom jersey" mengikuti label menu yang diklik pelanggan.
     */
    private function judulTab(Request $request, ?Category $kategori, string $jalur, int $halaman): string
    {
        $judul = match (true) {
            $request->filled('q') => 'Hasil pencarian "'.$request->string('q')->squish()->limit(40).'"',
            $kategori !== null => $kategori->parent
                ? $kategori->parent->nama.' › '.$kategori->nama
                : $kategori->nama,
            $jalur === 'kustom' => 'Custom jersey',
            $jalur === 'stok' => 'Stok toko',
            default => 'Katalog produk',
        };

        return $halaman > 1 ? $judul.' (halaman '.$halaman.')' : $judul;
    }

    /** @return array{0: string, 1: ?string} */
    private function judul(?Category $kategori, string $jalur): array
    {
        if ($kategori) {
            return [$kategori->nama, $kategori->deskripsi];
        }

        return match ($jalur) {
            'stok' => ['Stok toko', 'Barang yang sudah ada di rak. Bayar hari ini, ambil besok pada jam buka.'],
            'kustom' => ['Pesan kustom', 'Dibuat di workshop kami dengan desain, nama, dan nomor punggung tim Anda.'],
            default => ['Katalog', null],
        };
    }
}
