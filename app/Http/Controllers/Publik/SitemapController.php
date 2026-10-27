<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Response;

/**
 * Peta situs dan robots.txt untuk mesin pencari (KEP-089).
 *
 * Keduanya dibuat aplikasi, bukan berkas statis di public/: alamat sitemap di
 * robots.txt harus memuat domain, dan domain baru diketahui saat deploy.
 */
class SitemapController extends Controller
{
    public function peta(): Response
    {
        $alamat = collect([
            ['loc' => route('beranda')],
            ['loc' => route('katalog')],
            ['loc' => route('katalog', ['jalur' => 'kustom'])],
        ]);

        Category::where('aktif', true)->orderBy('urutan')->get(['slug', 'updated_at'])
            ->each(fn (Category $k) => $alamat->push([
                'loc' => route('katalog', ['kategori' => $k->slug]),
                'lastmod' => $k->updated_at,
            ]));

        Product::aktif()->orderBy('nama')->get(['id', 'slug', 'updated_at'])
            ->each(fn (Product $p) => $alamat->push([
                'loc' => route('produk', $p),
                'lastmod' => $p->updated_at,
            ]));

        foreach (['kebijakan-privasi', 'syarat-ketentuan'] as $rute) {
            $alamat->push(['loc' => route($rute)]);
        }

        return response()
            ->view('publik.sitemap', ['alamat' => $alamat])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots(): Response
    {
        // Selain produksi (staging, lokal) tidak boleh masuk hasil pencarian.
        if (! app()->isProduction()) {
            return response("User-agent: *\nDisallow: /\n")->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        // Halaman bertoken dan panel tidak perlu dirayapi sama sekali: tautan
        // pesanan pelanggan yang sempat tersebar tidak boleh ikut terindeks.
        $tutup = ['/panel', '/lacak/', '/kerja/', '/roll/', '/keranjang', '/checkout', '/pesanan-saya', '/cs/'];

        $isi = "User-agent: *\n"
            .collect($tutup)->map(fn ($j) => 'Disallow: '.$j)->implode("\n")
            ."\n\nSitemap: ".route('sitemap')."\n";

        return response($isi)->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
