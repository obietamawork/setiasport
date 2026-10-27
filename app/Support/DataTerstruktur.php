<?php

namespace App\Support;

use App\Models\Product;

/**
 * Data terstruktur schema.org (JSON-LD) untuk mesin pencari (KEP-089).
 *
 * Isinya diambil dari pengaturan dan katalog yang sama dengan yang tampil di
 * halaman, tidak ada yang dikarang: alamat dipakai utuh seperti diketik toko,
 * bukan dipecah-pecah menebak kecamatan dan kode pos.
 */
class DataTerstruktur
{
    /** @return array<string, mixed> */
    public static function toko(): array
    {
        $nama = setting('toko_nama', 'Setia Sport');

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'SportingGoodsStore',
            'name' => $nama,
            'url' => route('beranda'),
            'logo' => asset('logo.png'),
            'image' => asset('logo-lengkap.webp'),
            'telephone' => setting('toko_telepon') ?: null,
            'address' => filled(setting('toko_alamat')) ? [
                '@type' => 'PostalAddress',
                'streetAddress' => setting('toko_alamat'),
                'addressCountry' => 'ID',
            ] : null,
            'sameAs' => array_values(array_filter([
                setting('sosial_instagram'), setting('sosial_tiktok'), setting('sosial_facebook'),
            ])) ?: null,
        ]);
    }

    /**
     * Barang stok memakai Offer berharga pasti. Produk yang hanya dibuat
     * berdasarkan pesanan memakai AggregateOffer "mulai dari" harga termurah,
     * persis angka yang tampil di halamannya.
     *
     * @return array<string, mixed>
     */
    public static function produk(Product $produk): array
    {
        $penawaran = null;

        if ($produk->punyaJalurReadyStock() && $produk->harga_dasar) {
            $penawaran = [
                '@type' => 'Offer',
                'price' => (int) $produk->harga_dasar,
                'priceCurrency' => 'IDR',
                'availability' => $produk->variants->sum('stok_tersedia') > 0
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
                'url' => route('produk', $produk),
            ];
        } elseif ($produk->punyaJalurKustom()) {
            $termurah = $produk->prices->isNotEmpty()
                ? $produk->prices->min('harga_satuan')
                : $produk->priceTiers->min('harga_satuan');

            if ($termurah) {
                $penawaran = [
                    '@type' => 'AggregateOffer',
                    'lowPrice' => (int) $termurah,
                    'priceCurrency' => 'IDR',
                    'availability' => 'https://schema.org/MadeToOrder',
                    'url' => route('produk', $produk),
                ];
            }
        }

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $produk->nama,
            'sku' => $produk->sku,
            'description' => filled($produk->deskripsi) ? strip_tags($produk->deskripsi) : null,
            'image' => $produk->images->map(fn ($g) => url($g->url))->filter()->values()->all() ?: null,
            'category' => $produk->category?->nama,
            'offers' => $penawaran ? $penawaran + [
                'seller' => ['@type' => 'Organization', 'name' => setting('toko_nama', 'Setia Sport')],
            ] : null,
        ]);
    }
}
