<?php

namespace App\Services;

use App\Enums\KategoriUkuran;
use App\Enums\PriceBasis;
use App\Models\OptionValue;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductVariant;
use InvalidArgumentException;

/**
 * Satu-satunya tempat harga dihitung (KEP-009).
 *
 * Konfigurator di sisi pelanggan dan proses checkout di server memakai kelas
 * yang sama, dan server selalu jadi otoritas, harga yang dikirim dari browser
 * tidak pernah dipercaya.
 *
 *   harga_satuan  = tier(product, qty) + SUM(opsi PER_PCS)
 *   subtotal_item = harga_satuan * qty + SUM(opsi PER_ITEM)
 */
class PriceCalculator
{
    /**
     * Jalur kustom / PreOrder.
     *
     * @param  array<int, int>  $optionValueIds
     */
    public function kustom(Product $product, int $qty, array $optionValueIds = []): PriceBreakdown
    {
        if (! $product->punyaJalurKustom()) {
            throw new InvalidArgumentException("Produk {$product->sku} tidak punya jalur kustom.");
        }

        if ($qty < $product->moq) {
            throw new InvalidArgumentException(
                "Minimum pemesanan {$product->nama} adalah {$product->moq} pcs."
            );
        }

        $hargaTier = $product->hargaTier($qty);

        if ($hargaTier === null) {
            throw new InvalidArgumentException(
                "Tidak ada tier harga untuk {$qty} pcs pada produk {$product->sku}."
            );
        }

        $values = $this->ambilOptionValues($product, $optionValueIds);

        $perPcs = [];
        $perItem = [];

        foreach ($values as $value) {
            $baris = [
                'label' => $value->option->nama,
                'nilai' => $value->label,
                'jumlah' => $value->tambahan_harga,
            ];

            if ($value->basis === PriceBasis::PER_PCS) {
                $perPcs[] = $baris;
            } else {
                $perItem[] = ['label' => $value->label, 'jumlah' => $value->tambahan_harga];
            }
        }

        $hargaSatuan = $hargaTier + array_sum(array_column($perPcs, 'jumlah'));
        $subtotal = $hargaSatuan * $qty + array_sum(array_column($perItem, 'jumlah'));

        return new PriceBreakdown(
            hargaTier: $hargaTier,
            komponenPerPcs: $perPcs,
            hargaSatuan: $hargaSatuan,
            komponenPerItem: $perItem,
            qty: $qty,
            subtotal: $subtotal,
        );
    }

    /**
     * Jalur kustom dengan harga per potong menurut kategori ukuran (KEP-039).
     *
     * Dipakai produk yang daftar harganya dipisah SD / SMP / dewasa. Jumlah
     * pesanan TIDAK dikirim sebagai satu angka: ia dijumlahkan dari daftar
     * ukuran, supaya angka yang dipakai menghitung harga selalu angka yang
     * sama dengan yang nanti dikerjakan konveksi.
     *
     * @param  array<int, array{kategori: string|KategoriUkuran, ukuran: string, jumlah: int}>  $daftarUkuran
     * @param  array<int, int>  $optionValueIds
     */
    public function kustomPerUkuran(Product $product, array $daftarUkuran, array $optionValueIds = []): RincianPerUkuran
    {
        if (! $product->punyaJalurKustom()) {
            throw new InvalidArgumentException("Produk {$product->sku} tidak punya jalur kustom.");
        }

        if ($daftarUkuran === []) {
            throw new InvalidArgumentException('Daftar ukuran belum diisi.');
        }

        $values = $this->ambilOptionValues($product, $optionValueIds);

        $perPcs = [];
        $perItem = [];

        foreach ($values as $value) {
            if ($value->basis === PriceBasis::PER_PCS) {
                $perPcs[] = [
                    'label' => $value->option->nama,
                    'nilai' => $value->label,
                    'jumlah' => $value->tambahan_harga,
                ];
            } else {
                $perItem[] = ['label' => $value->label, 'jumlah' => $value->tambahan_harga];
            }
        }

        /*
         * Bahan menentukan SEL MANA di matriks harga yang dipakai, jadi
         * harganya sudah termasuk di harga dasar. Barisnya tetap ditampilkan
         * supaya pelanggan melihat bahan yang dipilih, tetapi angkanya dinolkan
         * agar tidak terhitung dua kali.
         */
        $bahan = $values->first(fn (OptionValue $v) => $product->prices->contains(
            fn (ProductPrice $h) => $h->option_value_id === $v->id
        ));

        if ($bahan !== null) {
            $perPcs = array_map(function (array $k) use ($bahan) {
                if ($k['label'] === $bahan->option->nama && $k['nilai'] === $bahan->label) {
                    $k['jumlah'] = 0;
                }

                return $k;
            }, $perPcs);
        }

        $tambahanPerPcs = array_sum(array_column($perPcs, 'jumlah'));

        $baris = [];
        $qty = 0;
        $subtotal = 0;

        foreach ($daftarUkuran as $b) {
            $kategori = $b['kategori'] instanceof KategoriUkuran
                ? $b['kategori']
                : KategoriUkuran::tryFrom((string) $b['kategori']);

            $jumlah = (int) $b['jumlah'];

            if ($jumlah < 1) {
                throw new InvalidArgumentException('Jumlah tiap ukuran minimal 1 pcs.');
            }

            if (! in_array($b['ukuran'], $product->ukuranTersedia($kategori), true)) {
                throw new InvalidArgumentException(
                    "Ukuran {$b['ukuran']} tidak tersedia untuk "
                    .($kategori?->label() ?? 'produk ini').'.'
                );
            }

            $dasar = $product->hargaPerPotong($kategori, $bahan?->id);

            if ($dasar === null) {
                throw new InvalidArgumentException(
                    "Harga {$product->nama} untuk kategori ".($kategori?->label() ?? 'ini').' belum diisi.'
                );
            }

            $hargaSatuan = $dasar + $tambahanPerPcs + $product->tambahanUkuran($b['ukuran']);

            $baris[] = [
                'kategori' => $kategori?->value ?? '',
                'ukuran' => $b['ukuran'],
                'jumlah' => $jumlah,
                'harga_satuan' => $hargaSatuan,
                'subtotal' => $hargaSatuan * $jumlah,
            ];

            $qty += $jumlah;
            $subtotal += $hargaSatuan * $jumlah;
        }

        if ($qty < $product->moq) {
            throw new InvalidArgumentException(
                "Minimum pemesanan {$product->nama} adalah {$product->moq} pcs."
            );
        }

        return new RincianPerUkuran(
            baris: $baris,
            komponenPerPcs: $perPcs,
            komponenPerItem: $perItem,
            qty: $qty,
            subtotal: $subtotal + array_sum(array_column($perItem, 'jumlah')),
        );
    }

    /**
     * Jalur ready stock. Tidak memakai tier sama sekali, kedua jalur pada
     * produk BOTH benar-benar terpisah dan tidak saling memengaruhi (KEP-011).
     */
    public function readyStock(ProductVariant $variant, int $qty): PriceBreakdown
    {
        $product = $variant->product;

        if (! $product->punyaJalurReadyStock()) {
            throw new InvalidArgumentException("Produk {$product->sku} tidak dijual ready stock.");
        }

        if ($qty > $variant->stok_tersedia) {
            throw new InvalidArgumentException(
                "Stok {$product->nama} ({$variant->label}) tinggal {$variant->stok_tersedia}."
            );
        }

        $hargaSatuan = $variant->harga;

        return new PriceBreakdown(
            hargaTier: $product->harga_dasar,
            komponenPerPcs: $variant->tambahan_harga === 0 ? [] : [[
                'label' => 'Varian',
                'nilai' => $variant->label,
                'jumlah' => $variant->tambahan_harga,
            ]],
            hargaSatuan: $hargaSatuan,
            komponenPerItem: [],
            qty: $qty,
            subtotal: $hargaSatuan * $qty,
        );
    }

    /**
     * Memuat option value sekaligus memastikan semuanya benar-benar milik
     * produk ini, mencegah harga dimanipulasi lewat id opsi dari produk lain.
     *
     * @param  array<int, int>  $ids
     * @return \Illuminate\Support\Collection<int, OptionValue>
     */
    private function ambilOptionValues(Product $product, array $ids)
    {
        if ($ids === []) {
            return collect();
        }

        $values = OptionValue::with('option')
            ->whereIn('id', $ids)
            ->where('aktif', true)
            ->get();

        foreach ($values as $value) {
            if ($value->option->product_id !== $product->id) {
                throw new InvalidArgumentException(
                    "Opsi \"{$value->label}\" bukan milik produk {$product->sku}."
                );
            }
        }

        if ($values->count() !== count(array_unique($ids))) {
            throw new InvalidArgumentException('Ada opsi yang tidak dikenali atau sudah nonaktif.');
        }

        return $values;
    }
}
