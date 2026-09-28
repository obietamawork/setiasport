<?php

namespace App\Http\Controllers\Publik;

use App\Enums\FulfillmentType;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Portofolio;
use App\Models\Product;
use App\Models\Testimoni;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

/**
 * Halaman depan, disusun ulang mengikuti rancangan klien (KEP-062).
 *
 * Susunan lamanya profil dulu, katalog belakangan (KEP-024): masuk akal untuk
 * pengurus sekolah yang sedang memilih konveksi. Klien mengirim rancangan
 * tampilan yang mendahulukan belanja, dan ia benar untuk mayoritas pengunjung
 * hariannya: orang Wonogiri yang mencari bola, sepatu, atau kaos, bukan pemesan
 * seragam satu sekolah.
 *
 * Buktinya tidak dibuang, hanya dipindah ke bawah dengan grid yang lebih kecil:
 * yang mencari bukti akan menggulir, yang mencari bola tidak perlu melewatinya.
 */
class BerandaController extends Controller
{
    public function __invoke(): View
    {
        return view('publik.beranda', [
            'kategori' => $this->kategori(),
            'unggulan' => $this->unggulan(),

            // Bukti pekerjaan tetap ada, tetapi lebih ringkas dan lebih bawah.
            'portofolio' => Portofolio::tampilan()->take(6)->get(),
            'testimoni' => Testimoni::tampilan()->take(3)->get(),

            'leadTime' => Product::query()
                ->aktif()
                ->whereIn('fulfillment_type', [
                    FulfillmentType::PREORDER->value, FulfillmentType::BOTH->value,
                ])
                ->min('lead_time_hari'),
        ]);
    }

    /**
     * Kategori utama, masing-masing memakai foto salah satu produknya.
     *
     * Kategori tidak punya gambar sendiri dan tidak perlu punya: menambah kolom
     * gambar berarti satu lagi yang harus diurus pemilik toko, dan foto produk
     * di dalamnya sudah mewakili isinya. Kategori yang belum punya produk
     * berfoto tetap tampil dengan kotak polos, bukan disembunyikan, karena
     * pengunjung tetap perlu tahu toko ini menjual apa saja.
     *
     * @return Collection<int, Category>
     */
    private function kategori(): Collection
    {
        $kategori = Category::query()
            ->where('aktif', true)
            ->whereNull('parent_id')
            ->withCount('products')
            ->orderBy('urutan')
            ->orderBy('nama')
            ->take(6)
            ->get();

        $idAnak = Category::whereIn('parent_id', $kategori->pluck('id'))->pluck('id', 'parent_id');

        foreach ($kategori as $k) {
            $k->setAttribute('gambar', Product::query()
                ->aktif()
                ->whereHas('images')
                ->where(fn ($q) => $q->where('category_id', $k->id)
                    ->orWhereIn('category_id', Category::where('parent_id', $k->id)->select('id')))
                ->with('images')
                ->first()?->images->first());
        }

        return $kategori;
    }

    /**
     * Produk unggulan, diurutkan dari yang paling banyak benar-benar terjual.
     *
     * "Terlaris" yang ditentukan sendiri oleh pemilik toko akan basi dalam
     * sebulan dan berubah jadi daftar produk kesayangan. Yang dipakai di sini
     * jumlah terjual sungguhan; selama belum ada penjualan, yang tampil produk
     * terbaru, bukan kotak kosong.
     *
     * @return Collection<int, Product>
     */
    private function unggulan(): Collection
    {
        $terlaris = OrderItem::query()
            ->selectRaw('product_id, sum(qty) as jumlah')
            ->whereNotNull('product_id')
            ->whereHas('order', fn ($q) => $q->whereNotIn('status', ['DIBATALKAN', 'KADALUARSA']))
            ->groupBy('product_id')
            ->orderByDesc('jumlah')
            ->take(6)
            ->pluck('product_id');

        $produk = Product::query()
            ->aktif()
            ->with(['images', 'priceTiers', 'prices'])
            ->when($terlaris->isNotEmpty(), fn ($q) => $q->whereIn('id', $terlaris))
            ->get()
            ->sortBy(fn (Product $p) => $terlaris->search($p->id) === false ? 99 : $terlaris->search($p->id))
            ->values();

        // Digenapkan dengan produk terbaru kalau yang terjual belum sampai enam.
        if ($produk->count() < 6) {
            $produk = $produk->concat(
                Product::query()
                    ->aktif()
                    ->with(['images', 'priceTiers', 'prices'])
                    ->whereNotIn('id', $produk->pluck('id'))
                    ->latest('id')
                    ->take(6 - $produk->count())
                    ->get()
            );
        }

        return $produk->take(6);
    }
}
