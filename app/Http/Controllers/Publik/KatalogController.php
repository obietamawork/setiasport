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
                // % dan _ dibaca sebagai huruf biasa, bukan pola (KEP-105, seperti pesanan di KEP-099).
                $cari = '%'.addcslashes($request->string('q')->toString(), '%_\\').'%';
                $q->where(fn ($w) => $w->where('nama', 'like', $cari)->orWhere('sku', 'like', $cari));
            })
            ->orderBy('nama')
            ->paginate(24);

        [$judul, $keterangan] = $this->judul($request, $kategoriTerpilih, $jalur);

        return view('publik.katalog', [
            'produk' => $produk,
            'judul' => $judul,
            // Judul besar kategori anak cukup namanya; induknya tampil sebagai
            // jejak tepat di atasnya.
            'judulBesar' => $request->filled('q') || ! $kategoriTerpilih ? $judul : $kategoriTerpilih->nama,
            'induk' => $request->filled('q') ? null : $kategoriTerpilih?->parent,
            'judulTab' => $produk->currentPage() > 1 ? $judul.' (halaman '.$produk->currentPage().')' : $judul,
            'keterangan' => $keterangan,
            // Jumlah awal diambil dari MOQ terkecil, jadi angkanya masuk akal
            // sejak halaman dibuka dan tidak menampilkan peringatan minimum.
            'qtyAwal' => (int) (Product::query()
                ->aktif()
                ->whereIn('fulfillment_type', [
                    FulfillmentType::PREORDER->value, FulfillmentType::BOTH->value,
                ])
                ->min('moq') ?: 12),
            // Pembeda dua jalur pindah ke sini dari halaman depan (KEP-024).
            'waktuKustom' => Product::rentangWaktuKustom(),
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
     * Satu judul untuk tab, judul besar, dan judul yang menempel di HP
     * (KEP-101). Dulu ketiganya berbeda: tab "Bola › Futsal" dengan judul
     * besar "Futsal", tab "Custom jersey" dengan judul besar "Pesan kustom",
     * dan bilah yang menempel di HP selalu "Katalog" apa pun yang dibuka.
     *
     * Nama kategori anak tidak unik ("Futsal" ada di bawah Bola dan di bawah
     * Jersey, "Custom" di bawah Celana dan Jaket), jadi induknya ikut. Hasil
     * pencarian tidak boleh berjudul sama dengan katalog tanpa pencarian.
     * "Custom jersey" mengikuti label menu yang diklik pelanggan.
     *
     * @return array{0: string, 1: ?string} judul dan keterangan di bawahnya
     */
    private function judul(Request $request, ?Category $kategori, string $jalur): array
    {
        if ($request->filled('q')) {
            return ['Hasil pencarian "'.$request->string('q')->squish()->limit(40).'"', null];
        }

        if ($kategori) {
            return [
                $kategori->parent ? $kategori->parent->nama.' › '.$kategori->nama : $kategori->nama,
                $kategori->deskripsi,
            ];
        }

        return match ($jalur) {
            'stok' => ['Stok toko', 'Barang yang sudah ada di rak. '.(siap_stok() === 'hari ini'
                ? 'Bayar dan ambil hari itu juga pada jam buka.'
                : 'Bayar hari ini, ambil '.siap_stok().' pada jam buka.')],
            'kustom' => ['Custom jersey', 'Dibuat di workshop kami dengan desain, nama, dan nomor punggung tim Anda.'],
            default => ['Katalog produk', null],
        };
    }
}
