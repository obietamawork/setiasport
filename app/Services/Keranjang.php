<?php

namespace App\Services;

use App\Enums\FulfillmentType;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Keranjang berbasis sesi.
 *
 * Yang disimpan hanya identitas dan jumlah, TIDAK ADA harga. Setiap kali
 * keranjang dibaca, harganya dihitung ulang lewat PriceCalculator. Harga yang
 * ikut menginap di sesi adalah harga yang bisa basi atau dipalsukan.
 */
class Keranjang
{
    private const KUNCI = 'keranjang';

    public function __construct(private PriceCalculator $kalkulator) {}

    /** @return array<string, array{product_id:int, variant_id:?int, qty:int, jalur:string, opsi:array<int,int>}> */
    public function baris(): array
    {
        return session(self::KUNCI, []);
    }

    public function kosong(): bool
    {
        return $this->baris() === [];
    }

    public function jumlahBaris(): int
    {
        return count($this->baris());
    }

    public function tambahReadyStock(ProductVariant $varian, int $qty): void
    {
        if ($qty < 1) {
            throw new InvalidArgumentException('Jumlah harus minimal 1.');
        }

        $baris = $this->baris();
        $kunci = 'rs:'.$varian->id;
        $qtyBaru = ($baris[$kunci]['qty'] ?? 0) + $qty;

        if ($qtyBaru > $varian->stok_tersedia) {
            throw new InvalidArgumentException(
                "Stok {$varian->product->nama} tinggal {$varian->stok_tersedia} pcs."
            );
        }

        $baris[$kunci] = [
            'product_id' => $varian->product_id,
            'variant_id' => $varian->id,
            'qty' => $qtyBaru,
            'jalur' => FulfillmentType::READY_STOCK->value,
            'opsi' => [],
        ];

        session([self::KUNCI => $baris]);
    }

    /**
     * Jalur kustom. Belum dipakai antarmuka sampai konfigurator dibuka, tapi
     * keranjang sudah menampungnya sejak sekarang karena satu order memang
     * boleh memuat kedua jalur (KEP-001).
     */
    public function tambahKustom(Product $produk, int $qty, array $optionValueIds): void
    {
        // Dihitung sekali di sini supaya MOQ dan opsi tidak sah ditolak sebelum
        // masuk keranjang, bukan baru ketahuan saat checkout.
        $this->kalkulator->kustom($produk, $qty, $optionValueIds);

        $baris = $this->baris();

        $baris['ks:'.Str::random(8)] = [
            'product_id' => $produk->id,
            'variant_id' => null,
            'qty' => $qty,
            'jalur' => FulfillmentType::PREORDER->value,
            'opsi' => array_values($optionValueIds),
        ];

        session([self::KUNCI => $baris]);
    }

    /**
     * Jalur kustom dengan harga per ukuran (KEP-039).
     *
     * Jumlah pesanan tidak dikirim terpisah: ia dijumlahkan dari daftar ukuran,
     * dan daftar itu pula yang nanti jadi daftar pemain di pesanan. Satu angka
     * jumlah yang berdiri sendiri pasti suatu saat berselisih dengan daftarnya.
     *
     * @param  array<int, array{kategori:string, ukuran:string, jumlah:int, nama_punggung?:?string, nomor?:?string}>  $daftarUkuran
     * @param  array<int, int>  $optionValueIds
     */
    public function tambahKustomPerUkuran(
        Product $produk,
        array $daftarUkuran,
        array $optionValueIds,
        ?string $catatan = null,
        ?string $desainSumber = null,
    ): void {
        $rincian = $this->kalkulator->kustomPerUkuran($produk, $daftarUkuran, $optionValueIds);

        $baris = $this->baris();

        $baris['ks:'.Str::random(8)] = [
            'product_id' => $produk->id,
            'variant_id' => null,
            'qty' => $rincian->qty,
            'jalur' => FulfillmentType::PREORDER->value,
            'opsi' => array_values($optionValueIds),
            'catatan' => $catatan,
            'desain_sumber' => $desainSumber,
            'ukuran' => array_map(fn (array $b) => [
                'kategori' => (string) ($b['kategori'] ?? ''),
                'ukuran' => (string) $b['ukuran'],
                'jumlah' => (int) $b['jumlah'],
                'nama_punggung' => $b['nama_punggung'] ?? null,
                'nomor' => $b['nomor'] ?? null,
            ], $daftarUkuran),
        ];

        session([self::KUNCI => $baris]);
    }

    public function ubahJumlah(string $kunci, int $qty): void
    {
        $baris = $this->baris();

        if (! isset($baris[$kunci])) {
            return;
        }

        // Jumlahnya berasal dari daftar ukuran, jadi tidak boleh diubah dari
        // keranjang: mengubahnya di sini akan membuat jumlah potong dan daftar
        // pemain berselisih tanpa ada yang tahu mana yang benar.
        if (isset($baris[$kunci]['ukuran'])) {
            throw new InvalidArgumentException(
                'Jumlah pesanan kustom diubah lewat daftar ukurannya, bukan dari keranjang.'
            );
        }

        if ($qty < 1) {
            $this->hapus($kunci);

            return;
        }

        // Tombol − di keranjang berhenti di minimum pesan (KEP-091); ini
        // penjaganya di server, untuk permintaan yang tidak lewat tombol itu.
        if ($baris[$kunci]['jalur'] === FulfillmentType::PREORDER->value) {
            $moq = (int) Product::whereKey($baris[$kunci]['product_id'])->value('moq');

            if ($qty < $moq) {
                throw new InvalidArgumentException("Minimum pemesanan {$moq} pcs.");
            }
        }

        if ($baris[$kunci]['variant_id']) {
            $varian = ProductVariant::with('product')->find($baris[$kunci]['variant_id']);

            if ($varian && $qty > $varian->stok_tersedia) {
                throw new InvalidArgumentException(
                    "Stok {$varian->product->nama} tinggal {$varian->stok_tersedia} pcs."
                );
            }
        }

        $baris[$kunci]['qty'] = $qty;
        session([self::KUNCI => $baris]);
    }

    public function hapus(string $kunci): void
    {
        $baris = $this->baris();
        unset($baris[$kunci]);
        session([self::KUNCI => $baris]);
    }

    public function bersihkan(): void
    {
        session()->forget(self::KUNCI);
    }

    /**
     * Isi keranjang lengkap dengan rincian harga terkini.
     *
     * @return Collection<int, array{kunci:string, produk:Product, varian:?ProductVariant, qty:int, jalur:FulfillmentType, opsi:array, rincian:PriceBreakdown, masalah:?string}>
     */
    public function isi(): Collection
    {
        return collect($this->baris())->map(function (array $b, string $kunci) {
            $produk = Product::with(['priceTiers', 'options.values', 'prices', 'sizeSurcharges'])
                ->find($b['product_id']);
            $varian = $b['variant_id'] ? ProductVariant::with('product')->find($b['variant_id']) : null;

            if (! $produk || ! $produk->aktif || ($b['variant_id'] && ! $varian)) {
                return null;
            }

            $jalur = FulfillmentType::from($b['jalur']);
            $masalah = null;

            try {
                $rincian = match (true) {
                    $jalur === FulfillmentType::READY_STOCK => $this->kalkulator->readyStock($varian, $b['qty']),
                    isset($b['ukuran']) => $this->kalkulator->kustomPerUkuran($produk, $b['ukuran'], $b['opsi']),
                    default => $this->kalkulator->kustom($produk, $b['qty'], $b['opsi']),
                };
            } catch (InvalidArgumentException $e) {
                // Stok berkurang atau harga berubah setelah barang masuk keranjang.
                // Barisnya tetap ditampilkan beserta penjelasannya, bukan dibuang diam-diam.
                $masalah = $e->getMessage();
                $rincian = null;
            }

            return compact('kunci', 'produk', 'varian', 'jalur', 'rincian', 'masalah')
                + [
                    'qty' => $rincian?->qty ?? $b['qty'],
                    'opsi' => $b['opsi'],
                    'catatan' => $b['catatan'] ?? null,
                    'desain_sumber' => $b['desain_sumber'] ?? null,
                    'ukuran' => $b['ukuran'] ?? null,
                ];
        })->filter()->values();
    }

    public function total(): int
    {
        return (int) $this->isi()->sum(
            fn (array $b) => $b['rincian'] instanceof RincianPerUkuran
                ? $b['rincian']->totalBersih()
                : ($b['rincian']?->subtotal ?? 0)
        );
    }

    /**
     * Berat seluruh isi keranjang, dipakai untuk menghitung ongkir (KEP-058).
     *
     * Produk yang beratnya belum diisi memakai berat bawaan dari Pengaturan,
     * bukan nol: paket seberat nol gram membuat penyedia ongkir menolak
     * menghitung, dan pelanggan kehilangan pilihan kurirnya gara-gara satu
     * kolom master yang lupa diisi.
     */
    public function beratGram(): int
    {
        $bawaan = (int) \App\Models\Setting::ambil('berat_bawaan_gram', 300);

        return (int) $this->isi()->sum(
            fn (array $b) => max(1, (int) ($b['produk']->berat_gram ?: $bawaan)) * $b['qty']
        );
    }

    public function adaMasalah(): bool
    {
        return $this->isi()->contains(fn (array $b) => $b['masalah'] !== null);
    }
}
