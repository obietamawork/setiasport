<?php

namespace Database\Seeders;

use App\Enums\FulfillmentType;
use App\Enums\PriceBasis;
use App\Models\Category;
use App\Models\OptionValue;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductPrice;
use App\Models\ProductSizeChart;
use App\Models\ProductSizeSurcharge;
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
    /**
     * Produk kustom dengan harga asli klien. Dipisah dari katalog contoh karena
     * keduanya diperlakukan berbeda saat seeder dijalankan ulang.
     */
    private const SKU_KUSTOM = [
        'CUST-JRS-ATASAN', 'CUST-SETELAN', 'CUST-CELANA-PENDEK', 'CUST-JAKET', 'CUST-TRAINING',
        'CUST-SERAGAM', 'CUST-KAOS-KERAH', 'CUST-KAOS-OBLONG', 'CUST-KAOS-KATUN',
    ];

    public function run(): void
    {
        $k = $this->kategori();

        /*
         * Produk kustom berharga per ukuran ikut dipasang di instalasi yang
         * sudah jalan (KEP-039): ia susunan baru yang dipakai toko, bukan
         * sekadar contoh etalase. Yang sudah ada tidak disentuh.
         */
        if (! Product::where('sku', 'CUST-JRS-ATASAN')->exists()) {
            $this->jerseyAtasanKustom($k['jersey-futsal']);
        }

        if (! Product::where('sku', 'CUST-SETELAN')->exists()) {
            $this->setelanKustom($k['jersey-futsal']);
            $this->celanaPendekKustom($k['celana-custom']);
            $this->jaketKustom($k['jaket-custom']);
            $this->trainingKustom($k['celana-custom']);
        }

        if (! Product::where('sku', 'CUST-SERAGAM')->exists()) {
            $this->seragamOlahraga($k['pakaian-olahraga']);
            $this->kaosBerkrah($k['pakaian-olahraga']);
            $this->kaosOblong($k['pakaian-olahraga']);
            $this->kaosKatun($k['pakaian-olahraga']);
        }

        $this->panduanUkuranSusulan();

        /*
         * Sisanya katalog contoh, hanya untuk instalasi yang masih kosong.
         * Menjalankannya lagi setelah pemilik toko mengoreksi harga akan
         * menimpa harga asli dengan angka draft, tanpa ada yang sadar sampai
         * pelanggan membayar dengan harga yang salah.
         */
        if (Product::whereNotIn('sku', self::SKU_KUSTOM)->exists()) {
            return;
        }

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
            'Celana' => ['Custom' => 'celana-custom'],
            'Jaket' => ['Custom' => 'jaket-custom'],
        ];

        /*
         * Kategori barang stok toko, dikirim klien apa adanya. Ditaruh sebagai
         * kategori datar tanpa induk: toko menyebutnya per cabang olahraga,
         * bukan per jenis barang.
         */
        $stok = [
            'Volly', 'Badminton', 'Sepak Bola & Futsal', 'Basket', 'Tennis', 'Tenis Meja',
            'Renang', 'Fitness & Gym', 'Running & Training', 'Sepatu Olahraga',
            'Pakaian Olahraga', 'Support & Pelindung', 'Perlengkapan Olahraga / Latihan',
            'Perlengkapan Lapangan & Pertandingan', 'Aksesori Olahraga', 'Tarraw',
            'Tolak Peluru', 'Karambol', 'Sepeda', 'Atletik', 'Yoga', 'Taekwondo',
        ];

        $hasil = [];
        $urutan = 0;

        foreach ($stok as $i => $nama) {
            $hasil[Str::slug($nama)] = Category::updateOrCreate(
                ['slug' => Str::slug($nama)],
                ['nama' => $nama, 'urutan' => 100 + $i]
            );
        }

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

    /**
     * Produk kustom dengan harga per kategori ukuran (KEP-039). Angkanya
     * diambil dari daftar harga asli klien, bukan karangan: harga per potong
     * berbeda untuk SD, SMP, dan dewasa, dan satu tim boleh memesan ketiganya.
     */
    private function jerseyAtasanKustom(Category $kategori): void
    {
        $p = $this->produk($kategori, [
            'sku' => 'CUST-JRS-ATASAN',
            'nama' => 'Custom Jersey Atasan',
            'fulfillment_type' => FulfillmentType::PREORDER,
            'berat_gram' => 250,
            'moq' => 6,
            'diskon_min_qty' => 12,
            'diskon_per_pcs' => 5_000,
            'lead_time_hari' => 7,
            'roster_wajib' => true,
            'deskripsi' => 'Jersey atasan full printing. Harga mengikuti bahan dan ukuran, '
                .'dan satu pesanan boleh memuat ukuran anak sampai dewasa sekaligus.',
        ]);

        // Bahan tidak menambah harga sendiri: ia memilih baris matriks harga.
        $this->opsi($p, 'Bahan', [
            ['Serena', 0], ['Jarum', 0], ['Milano', 0], ['Wafel', 0], ['Embos', 0],
        ]);
        $this->opsi($p, 'Model Baju', [['Oblong', 0], ['Pakai Kerah', 10_000]]);
        $this->opsi($p, 'Model Lengan', [['Pendek', 0], ['Panjang', 10_000]]);
        $this->opsi($p, 'Saku', [['Tanpa Saku', 0], ['Pakai Saku', 5_000]]);

        // Harga pendek oblong per kategori dan bahan, apa adanya dari klien.
        $this->matriksHarga($p, [
            'SD' => ['Serena' => 45_000, 'Jarum' => 55_000, 'Milano' => 65_000, 'Wafel' => 70_000, 'Embos' => 70_000],
            'SMP' => ['Serena' => 55_000, 'Jarum' => 65_000, 'Milano' => 75_000, 'Wafel' => 80_000, 'Embos' => 80_000],
            'DEWASA' => ['Serena' => 60_000, 'Jarum' => 70_000, 'Milano' => 80_000, 'Wafel' => 85_000, 'Embos' => 90_000],
        ]);

        $this->tambahanUkuran($p, ['3XL' => 5_000, '5XL' => 10_000]);
    }

    /**
     * Setelan jersey, atasan dan celana sekaligus (KEP-039). Harganya matriks
     * bahan kali kategori ukuran, persis seperti daftar harga klien.
     */
    private function setelanKustom(Category $kategori): void
    {
        $p = $this->produk($kategori, [
            'sku' => 'CUST-SETELAN',
            'nama' => 'Custom Setelan Jersey',
            'fulfillment_type' => FulfillmentType::PREORDER,
            'berat_gram' => 280,
            'moq' => 6,
            'diskon_min_qty' => 12,
            'diskon_per_pcs' => 5_000,
            'lead_time_hari' => 7,
            'roster_wajib' => true,
            'deskripsi' => 'Atasan dan celana sekaligus, full printing. Harga mengikuti bahan '
                .'dan ukuran, dan satu pesanan boleh memuat ukuran anak sampai dewasa.',
        ]);

        $this->opsi($p, 'Bahan', [
            ['Serena', 0], ['Jarum', 0], ['Milano', 0], ['Wafel', 0], ['Embos', 0],
        ]);
        $this->opsi($p, 'Model Baju', [['Oblong', 0], ['Pakai Kerah', 10_000]]);
        $this->opsi($p, 'Model Lengan', [['Pendek', 0], ['Panjang', 10_000]]);

        $this->matriksHarga($p, [
            'SD' => ['Serena' => 75_000, 'Jarum' => 85_000, 'Milano' => 95_000, 'Wafel' => 100_000, 'Embos' => 115_000],
            'SMP' => ['Serena' => 85_000, 'Jarum' => 95_000, 'Milano' => 105_000, 'Wafel' => 110_000, 'Embos' => 125_000],
            'DEWASA' => ['Serena' => 100_000, 'Jarum' => 110_000, 'Milano' => 120_000, 'Wafel' => 125_000, 'Embos' => 135_000],
        ]);

        $this->tambahanUkuran($p, ['3XL' => 10_000]);
    }

    /**
     * Celana pendek fullprint. Satu-satunya produk kustom yang harganya TIDAK
     * bergantung kategori umur: kategori null berarti berlaku untuk semua.
     */
    private function celanaPendekKustom(Category $kategori): void
    {
        $p = $this->produk($kategori, [
            'sku' => 'CUST-CELANA-PENDEK',
            'nama' => 'Custom Celana Pendek Fullprint',
            'fulfillment_type' => FulfillmentType::PREORDER,
            'berat_gram' => 250,
            'moq' => 6,
            'diskon_min_qty' => 12,
            'diskon_per_pcs' => 5_000,
            'lead_time_hari' => 7,
            'roster_wajib' => false,
            'deskripsi' => 'Celana pendek full printing. Harganya sama untuk semua ukuran, '
                .'mengikuti bahan yang dipilih. Ukurannya ikut panduan ukuran celana.',
        ]);

        $this->opsi($p, 'Bahan', [['Lotto', 0], ['Diadora', 0], ['Micro', 0]]);

        // Panduan ukuran dari klien (KEP-049). Daftar inilah yang membatasi
        // ukuran mana yang boleh dipesan, bukan daftar bawaan di kode.
        $this->panduanUkuran($p, null, [
            'S' => [35, 45], 'M' => [37, 47], 'L' => [39, 49],
            'XL' => [41, 51], '2XL' => [43, 53], '3XL' => [45, 55],
        ]);

        // Kategori null: satu harga dari ukuran terkecil sampai terbesar.
        $this->matriksHarga($p, [
            null => ['Lotto' => 35_000, 'Diadora' => 45_000, 'Micro' => 45_000],
        ]);
    }

    private function jaketKustom(Category $kategori): void
    {
        $p = $this->produk($kategori, [
            'sku' => 'CUST-JAKET',
            'nama' => 'Custom Jaket Fullprint',
            'fulfillment_type' => FulfillmentType::PREORDER,
            'berat_gram' => 280,
            'moq' => 6,
            'diskon_min_qty' => 12,
            'diskon_per_pcs' => 5_000,
            'lead_time_hari' => 10,
            'roster_wajib' => false,
            'deskripsi' => 'Jaket full printing dengan desain bebas. Harga mengikuti bahan dan ukuran.',
        ]);

        $this->opsi($p, 'Bahan', [['Lotto', 0], ['Diadora Lokal', 0], ['Diadora Import', 0]]);

        $this->matriksHarga($p, [
            'SD' => ['Lotto' => 115_000, 'Diadora Lokal' => 130_000, 'Diadora Import' => 140_000],
            'SMP' => ['Lotto' => 125_000, 'Diadora Lokal' => 140_000, 'Diadora Import' => 150_000],
            'DEWASA' => ['Lotto' => 135_000, 'Diadora Lokal' => 150_000, 'Diadora Import' => 160_000],
        ]);
    }

    private function trainingKustom(Category $kategori): void
    {
        $p = $this->produk($kategori, [
            'sku' => 'CUST-TRAINING',
            'nama' => 'Custom Celana Training Fullprint',
            'fulfillment_type' => FulfillmentType::PREORDER,
            'berat_gram' => 280,
            'moq' => 6,
            'diskon_min_qty' => 12,
            'diskon_per_pcs' => 5_000,
            'lead_time_hari' => 10,
            'roster_wajib' => false,
            'deskripsi' => 'Celana training panjang, full printing. Harga mengikuti bahan dan ukuran.',
        ]);

        $this->opsi($p, 'Bahan', [['Lotto', 0], ['Diadora Lokal', 0], ['Diadora Import', 0]]);

        $this->matriksHarga($p, [
            'SD' => ['Lotto' => 75_000, 'Diadora Lokal' => 85_000, 'Diadora Import' => 100_000],
            'SMP' => ['Lotto' => 80_000, 'Diadora Lokal' => 90_000, 'Diadora Import' => 105_000],
            'DEWASA' => ['Lotto' => 85_000, 'Diadora Lokal' => 95_000, 'Diadora Import' => 110_000],
        ]);
    }

    /**
     * Seragam olahraga sekolah: satu set berarti kaos DAN training panjang.
     * Harganya matriks bahan kali kategori umur, sama seperti jersey.
     */
    private function seragamOlahraga(Category $kategori): void
    {
        $p = $this->produk($kategori, [
            'sku' => 'CUST-SERAGAM',
            'nama' => 'Seragam Olahraga (Kaos + Training)',
            'fulfillment_type' => FulfillmentType::PREORDER,
            'moq' => 24,
            'lead_time_hari' => 10,
            'deskripsi' => 'Satu set berisi kaos dan training panjang. Harga mengikuti bahan '
                .'dan jenjang sekolah. Lengan panjang menambah biaya per set.',
        ]);

        $this->opsi($p, 'Bahan', [
            ['PE Printing Lotto', 0], ['PE Printing Diadora', 0],
            ['Katun Lotto', 0], ['Katun Diadora', 0],
        ]);
        $this->opsi($p, 'Model Lengan', [['Pendek', 0], ['Panjang', 10_000]]);

        $this->matriksHarga($p, [
            'SD' => ['PE Printing Lotto' => 65_000, 'PE Printing Diadora' => 75_000,
                'Katun Lotto' => 85_000, 'Katun Diadora' => 95_000],
            'SMP' => ['PE Printing Lotto' => 75_000, 'PE Printing Diadora' => 85_000,
                'Katun Lotto' => 95_000, 'Katun Diadora' => 105_000],
            'DEWASA' => ['PE Printing Lotto' => 85_000, 'PE Printing Diadora' => 95_000,
                'Katun Lotto' => 105_000, 'Katun Diadora' => 115_000],
        ]);
    }

    /** Kaos berkrah, harganya per bahan dan tidak dipisah jenjang sekolah. */
    private function kaosBerkrah(Category $kategori): void
    {
        $p = $this->produk($kategori, [
            'sku' => 'CUST-KAOS-KERAH',
            'nama' => 'Kaos Berkrah',
            'fulfillment_type' => FulfillmentType::PREORDER,
            'moq' => 6,
            'lead_time_hari' => 7,
            'deskripsi' => 'Kaos berkrah polos. Harga belum termasuk bordir atau sablon; '
                .'biayanya menyusul setelah desainnya dibicarakan.',
        ]);

        $this->opsi($p, 'Bahan', [
            ['PE', 0], ['PE 20', 0], ['Lakos PE', 0], ['Katun', 0], ['Lakos CVC', 0],
        ]);
        $this->opsi($p, 'Model Lengan', [['Pendek', 0], ['Panjang', 10_000]]);

        $this->matriksHarga($p, [
            null => ['PE' => 35_000, 'PE 20' => 40_000, 'Lakos PE' => 45_000,
                'Katun' => 60_000, 'Lakos CVC' => 70_000],
        ]);
    }

    /**
     * Kaos oblong. Model tunik dan oversize dari daftar harga klien BELUM
     * dipasang: harganya tidak mengikuti pola pendek-panjang, dan perlu
     * dipastikan dulu apakah itu produk sendiri atau pilihan di sini.
     */
    private function kaosOblong(Category $kategori): void
    {
        $p = $this->produk($kategori, [
            'sku' => 'CUST-KAOS-OBLONG',
            'nama' => 'Kaos Oblong',
            'fulfillment_type' => FulfillmentType::PREORDER,
            'moq' => 6,
            'lead_time_hari' => 7,
            'deskripsi' => 'Kaos oblong tanpa krah. Harga belum termasuk bordir atau sablon.',
        ]);

        $this->opsi($p, 'Bahan', [['PE', 0], ['PE 20 (Semi Katun)', 0]]);
        $this->opsi($p, 'Model Lengan', [['Pendek', 0], ['Panjang', 10_000]]);

        $this->matriksHarga($p, [
            null => ['PE' => 25_000, 'PE 20 (Semi Katun)' => 30_000],
        ]);
    }

    /** Kaos katun, dipisah per jenjang. Lengan panjangnya hanya +Rp5.000. */
    private function kaosKatun(Category $kategori): void
    {
        $p = $this->produk($kategori, [
            'sku' => 'CUST-KAOS-KATUN',
            'nama' => 'Kaos Katun',
            'fulfillment_type' => FulfillmentType::PREORDER,
            'moq' => 6,
            'lead_time_hari' => 7,
            'deskripsi' => 'Kaos katun 24s atau 30s, dipisah menurut jenjang sekolah. '
                .'Harga belum termasuk bordir atau sablon.',
        ]);

        $this->opsi($p, 'Bahan', [['Katun 24s', 0], ['Katun 30s', 0]]);
        $this->opsi($p, 'Model Lengan', [['Pendek', 0], ['Panjang', 5_000]]);

        $this->matriksHarga($p, [
            'SD' => ['Katun 24s' => 35_000, 'Katun 30s' => 30_000],
            'SMP' => ['Katun 24s' => 45_000, 'Katun 30s' => 35_000],
            'DEWASA' => ['Katun 24s' => 55_000, 'Katun 30s' => 40_000],
        ]);
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

    /**
     * Matriks harga per kategori ukuran dan bahan (KEP-039).
     *
     * @param  array<string, array<string, int>>  $matriks
     */
    private function matriksHarga(Product $p, array $matriks): void
    {
        foreach ($matriks as $kategori => $baris) {
            $kategori = $kategori === '' ? null : $kategori;

            foreach ($baris as $bahan => $harga) {
                $value = OptionValue::whereHas(
                    'option',
                    fn ($q) => $q->where('product_id', $p->id)->where('nama', 'Bahan')
                )->where('label', $bahan)->firstOrFail();

                ProductPrice::updateOrCreate(
                    ['product_id' => $p->id, 'kategori' => $kategori, 'option_value_id' => $value->id],
                    ['harga_satuan' => $harga]
                );
            }
        }
    }

    /**
     * Panduan ukuran dari klien biasanya menyusul setelah produknya dibuat,
     * jadi pengisiannya dipisah dari pembuatan produk. Yang sudah punya
     * panduan tidak disentuh, supaya koreksi pemilik toko tidak tertimpa.
     */
    private function panduanUkuranSusulan(): void
    {
        /*
         * Kaos dan seragam hanya dijual M, L, dan XL. Angka sentimeternya belum
         * dikirim klien, jadi barisnya diisi tanpa ukuran badan: yang penting
         * daftar ukurannya sudah membatasi pesanan.
         */
        foreach (['CUST-SERAGAM', 'CUST-KAOS-KERAH', 'CUST-KAOS-OBLONG', 'CUST-KAOS-KATUN'] as $sku) {
            $kaos = Product::where('sku', $sku)->first();

            if (! $kaos || $kaos->sizeCharts()->exists()) {
                continue;
            }

            $kategoriKaos = $kaos->prices()->whereNotNull('kategori')->exists()
                ? ['SD', 'SMP', 'DEWASA']
                : [null];

            foreach ($kategoriKaos as $kat) {
                foreach (['M', 'L', 'XL'] as $urutan => $ukuran) {
                    ProductSizeChart::updateOrCreate(
                        ['product_id' => $kaos->id, 'kategori' => $kat, 'ukuran' => $ukuran],
                        ['ukuran_cm' => [], 'urutan' => $urutan]
                    );
                }
            }
        }

        $celana = Product::where('sku', 'CUST-CELANA-PENDEK')->first();

        if ($celana && $celana->sizeCharts()->doesntExist()) {
            $this->panduanUkuran($celana, null, [
                'S' => [35, 45], 'M' => [37, 47], 'L' => [39, 49],
                'XL' => [41, 51], '2XL' => [43, 53], '3XL' => [45, 55],
            ]);
        }
    }

    /**
     * Panduan ukuran celana: lebar dan panjang dalam sentimeter.
     *
     * @param  array<string, array{0:int, 1:int}>  $baris
     */
    private function panduanUkuran(Product $p, ?string $kategori, array $baris): void
    {
        $urutan = 0;

        foreach ($baris as $ukuran => [$lebar, $panjang]) {
            ProductSizeChart::updateOrCreate(
                ['product_id' => $p->id, 'kategori' => $kategori, 'ukuran' => $ukuran],
                [
                    'ukuran_cm' => [
                        ['nama' => 'Lebar', 'nilai' => $lebar],
                        ['nama' => 'Panjang', 'nilai' => $panjang],
                    ],
                    'urutan' => $urutan++,
                ]
            );
        }
    }

    /** @param array<string, int> $daftar */
    private function tambahanUkuran(Product $p, array $daftar): void
    {
        foreach ($daftar as $ukuran => $tambahan) {
            ProductSizeSurcharge::updateOrCreate(
                ['product_id' => $p->id, 'ukuran' => $ukuran],
                ['tambahan_harga' => $tambahan]
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
