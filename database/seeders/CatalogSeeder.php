<?php

namespace Database\Seeders;

use App\Enums\FulfillmentType;
use App\Enums\PriceBasis;
use App\Models\Category;
use App\Models\OptionValue;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductPriceTier;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Lima produk dari docs/09-katalog-contoh.md.
 *
 * Dipilih untuk MENGUJI BATAS model data, bukan sekadar mengisi etalase:
 * produk tanpa varian, matriks varian 12 SKU, tier + dua basis biaya + roster
 * wajib, MOQ tinggi + roster kondisional, dan produk hybrid dua jalur.
 *
 * Angkanya masih draft, pemilik toko akan mengoreksinya lewat panel.
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $k = $this->kategori();

        $this->bola($k['bola-futsal']);
        $this->sepatu($k['sepatu-futsal']);
        $this->jerseyFutsal($k['jersey-futsal']);
        $this->kaosSekolah($k['kaos-sekolah']);
        $this->jerseyPolos($k['jersey-polos']);
    }

    /** @return array<string, Category> */
    private function kategori(): array
    {
        $pohon = [
            'Bola' => ['Futsal' => 'bola-futsal'],
            'Sepatu' => ['Futsal' => 'sepatu-futsal'],
            'Jersey' => ['Futsal' => 'jersey-futsal', 'Polos' => 'jersey-polos'],
            'Kaos' => ['Sekolah' => 'kaos-sekolah'],
        ];

        $hasil = [];
        $urutan = 0;

        foreach ($pohon as $namaInduk => $anak) {
            $induk = Category::updateOrCreate(
                ['slug' => Str::slug($namaInduk)],
                ['nama' => $namaInduk, 'urutan' => $urutan++]
            );

            $u = 0;
            foreach ($anak as $namaAnak => $slug) {
                $hasil[$slug] = Category::updateOrCreate(
                    ['slug' => $slug],
                    ['nama' => $namaAnak, 'parent_id' => $induk->id, 'urutan' => $u++]
                );
            }
        }

        return $hasil;
    }

    /** A, produk paling sederhana: tanpa varian, tanpa opsi. */
    private function bola(Category $kategori): void
    {
        $p = $this->produk($kategori, [
            'sku' => 'BOLA-MITRE-01',
            'nama' => 'Bola Futsal Mitre Ultimatch',
            'fulfillment_type' => FulfillmentType::READY_STOCK,
            'harga_dasar' => 285_000,
        ]);

        $this->varian($p, [['sku' => 'BOLA-MITRE-01', 'stok' => 12]]);
    }

    /** B, matriks varian 12 SKU, penguji stok per SKU dan reservasi. */
    private function sepatu(Category $kategori): void
    {
        $p = $this->produk($kategori, [
            'sku' => 'SEPATU-ORT-CTL',
            'nama' => 'Sepatu Futsal Ortuseight Catalyst',
            'fulfillment_type' => FulfillmentType::READY_STOCK,
            'harga_dasar' => 450_000,
        ]);

        $varian = [];
        $stok = [39 => 2, 40 => 4, 41 => 5, 42 => 5, 43 => 3, 44 => 1];

        foreach (['Hitam', 'Merah'] as $warna) {
            foreach ($stok as $ukuran => $jumlah) {
                $varian[] = [
                    'sku' => "SEPATU-ORT-CTL-{$ukuran}-".strtoupper(substr($warna, 0, 3)),
                    'ukuran' => (string) $ukuran,
                    'warna' => $warna,
                    'stok' => $jumlah,
                ];
            }
        }

        $this->varian($p, $varian);
    }

    /** C, produk terpenting: tier, dua basis biaya, roster wajib. */
    private function jerseyFutsal(Category $kategori): void
    {
        $p = $this->produk($kategori, [
            'sku' => 'JRS-FUTSAL-FP',
            'nama' => 'Jersey Futsal Full Printing',
            'fulfillment_type' => FulfillmentType::PREORDER,
            'moq' => 12,
            'lead_time_hari' => 7,
            'roster_wajib' => true,
        ]);

        $this->tier($p, [[12, 23, 95_000], [24, 47, 88_000], [48, null, 82_000]]);

        $this->opsi($p, 'Bahan', [
            ['Dryfit Serena', 0],
            ['Milano', 7_000],
            ['Hyget', -8_000],
        ]);
        $this->opsi($p, 'Lengan', [['Pendek', 0], ['Panjang', 8_000]]);
        $this->opsi($p, 'Kerah', [['O-neck', 0], ['V-neck', 0], ['Kerah Polo', 10_000]]);
        $this->opsi($p, 'Celana', [['Tanpa Celana', 0], ['Dengan Celana', 45_000]]);
        $this->opsi($p, 'Nameset', [['Tanpa Nameset', 0], ['Nama & Nomor', 10_000]]);

        // Satu-satunya biaya PER_ITEM di produk ini. Kalau basisnya salah dan
        // ikut dikali 18, tagihan melar Rp 1.275.000 (KEP-009).
        $this->opsi($p, 'Setup Desain', [
            ['Setup Desain Kustom', 75_000, PriceBasis::PER_ITEM],
        ], wajib: false);
    }

    /** D, MOQ tinggi dan roster KONDISIONAL: tidak semua PreOrder butuh roster. */
    private function kaosSekolah(Category $kategori): void
    {
        $p = $this->produk($kategori, [
            'sku' => 'KAOS-SEKOLAH-SB',
            'nama' => 'Kaos Olahraga Sekolah (Sablon)',
            'fulfillment_type' => FulfillmentType::PREORDER,
            'moq' => 40,
            'lead_time_hari' => 10,
            'roster_wajib' => false,
        ]);

        $this->tier($p, [[40, 99, 55_000], [100, 299, 48_000], [300, null, 43_000]]);

        $this->opsi($p, 'Bahan', [['Cotton Combed 30s', 0], ['Lacoste CVC', 6_000]]);
        $this->opsi($p, 'Nama Siswa di Dada', [['Tidak', 0], ['Ya', 4_000]]);
        $this->opsi($p, 'Setup Sablon', [
            ['Setup Film Sablon', 150_000, PriceBasis::PER_ITEM],
        ], wajib: false);
    }

    /**
     * E, produk hybrid (KEP-011). Perhatikan harga kustom pada jumlah besar
     * LEBIH MURAH daripada ready stock: itu normal (produksi massal vs stok
     * satuan), dan model data memang harus membolehkannya.
     */
    private function jerseyPolos(Category $kategori): void
    {
        $p = $this->produk($kategori, [
            'sku' => 'JRS-POLOS-01',
            'nama' => 'Jersey Polos',
            'fulfillment_type' => FulfillmentType::BOTH,
            'harga_dasar' => 65_000,
            'moq' => 12,
            'lead_time_hari' => 10,
            'roster_wajib' => false,
        ]);

        $varian = [];
        foreach (['Merah', 'Biru', 'Hitam', 'Putih'] as $warna) {
            foreach (['S', 'M', 'L', 'XL', 'XXL'] as $ukuran) {
                $varian[] = [
                    'sku' => "JRS-POLOS-01-{$ukuran}-".strtoupper(substr($warna, 0, 3)),
                    'ukuran' => $ukuran,
                    'warna' => $warna,
                    'stok' => 8,
                ];
            }
        }
        $this->varian($p, $varian);

        // Jalur kustom, benar-benar terpisah dari harga ready stock di atas.
        $this->tier($p, [[12, 47, 58_000], [48, null, 52_000]]);

        $this->opsi($p, 'Sablon Logo Tim', [['Tidak', 0], ['Ya', 12_000]]);
        $this->opsi($p, 'Setup Sablon', [
            ['Setup Film Sablon', 100_000, PriceBasis::PER_ITEM],
        ], wajib: false);
    }

    // ------------------------------------------------------------- pembantu

    private function produk(Category $kategori, array $atribut): Product
    {
        return Product::updateOrCreate(
            ['sku' => $atribut['sku']],
            array_merge([
                'category_id' => $kategori->id,
                'slug' => Str::slug($atribut['nama']),
                'deskripsi' => null,
                'harga_dasar' => 0,
                'moq' => 1,
                'lead_time_hari' => 0,
                'roster_wajib' => false,
                'aktif' => true,
            ], $atribut)
        );
    }

    private function varian(Product $p, array $daftar): void
    {
        foreach ($daftar as $v) {
            ProductVariant::updateOrCreate(
                ['sku' => $v['sku']],
                array_merge([
                    'product_id' => $p->id,
                    'ukuran' => null,
                    'warna' => null,
                    'tambahan_harga' => 0,
                    'stok' => 0,
                ], $v)
            );
        }
    }

    /** @param array<int, array{0:int, 1:?int, 2:int}> $tiers */
    private function tier(Product $p, array $tiers): void
    {
        foreach ($tiers as [$min, $max, $harga]) {
            ProductPriceTier::updateOrCreate(
                ['product_id' => $p->id, 'min_qty' => $min],
                ['max_qty' => $max, 'harga_satuan' => $harga]
            );
        }
    }

    /** @param array<int, array{0:string, 1:int, 2?:PriceBasis}> $values */
    private function opsi(Product $p, string $nama, array $values, bool $wajib = true): void
    {
        $urutan = ProductOption::where('product_id', $p->id)->count();

        $opsi = ProductOption::updateOrCreate(
            ['product_id' => $p->id, 'nama' => $nama],
            ['wajib' => $wajib, 'urutan' => $urutan]
        );

        foreach ($values as $i => $v) {
            OptionValue::updateOrCreate(
                ['product_option_id' => $opsi->id, 'label' => $v[0]],
                [
                    'tambahan_harga' => $v[1],
                    'basis' => $v[2] ?? PriceBasis::PER_PCS,
                    'urutan' => $i,
                    'aktif' => true,
                ]
            );
        }
    }
}
