<?php

namespace Database\Seeders;

use App\Enums\KategoriUkuran;
use App\Models\Product;
use App\Models\ProductSizeChart;
use Illuminate\Database\Seeder;

/**
 * Angka panduan ukuran dan berat satuan untuk produk konveksi.
 *
 * Sebelum ini, baris panduan ukurannya ada tetapi kolom ukurannya kosong, jadi
 * pelanggan tetap harus bertanya "ukuran L itu berapa lebar dadanya?" — dan
 * pertanyaan itu justru yang paling sering datang lewat WhatsApp.
 *
 * Angkanya adalah ukuran jadi yang lazim dipakai konveksi jersey di Jawa,
 * dalam sentimeter, dibaca sebagai LEBAR (bukan lingkar) dada dan panjang badan
 * dari bahu. Toko tinggal mengoreksinya lewat Panel → Produk → Panduan ukuran
 * kalau polanya sendiri berbeda; nilai di sini hanya titik awal supaya halaman
 * pemesanan tidak pernah tampil tanpa panduan sama sekali.
 *
 * Idempoten: hanya mengisi yang masih kosong, tidak pernah menimpa angka yang
 * sudah dikoreksi pemilik toko.
 */
class PanduanUkuranSeeder extends Seeder
{
    /**
     * Lebar dada dan panjang badan per kelompok ukuran, untuk atasan.
     *
     * @var array<string, array<string, array{int, int}>>
     */
    private const ATASAN = [
        'SD' => ['S' => [32, 46], 'M' => [34, 49], 'L' => [36, 52], 'XL' => [38, 55]],
        'SMP' => ['S' => [40, 58], 'M' => [42, 61], 'L' => [44, 64], 'XL' => [46, 66]],
        'DEWASA' => [
            'S' => [46, 68], 'M' => [48, 70], 'L' => [50, 72], 'XL' => [52, 74],
            '2XL' => [54, 76], '3XL' => [56, 78], '4XL' => [58, 80], '5XL' => [60, 82],
        ],
    ];

    /** Lingkar pinggang dan panjang, untuk bawahan. */
    private const BAWAHAN = [
        'SD' => ['S' => [52, 34], 'M' => [56, 37], 'L' => [60, 40], 'XL' => [64, 43]],
        'SMP' => ['S' => [64, 44], 'M' => [68, 47], 'L' => [72, 50], 'XL' => [76, 52]],
        'DEWASA' => [
            'S' => [72, 50], 'M' => [76, 52], 'L' => [80, 54], 'XL' => [84, 56],
            '2XL' => [88, 58], '3XL' => [92, 60], '4XL' => [96, 62], '5XL' => [100, 64],
        ],
    ];

    /**
     * Produk konveksi: bentuk panduannya, dan berat satu potong dalam gram.
     *
     * Berat dipakai menghitung ongkir, jadi dibulatkan ke atas: ongkir yang
     * kurang ditanggung toko, ongkir yang lebih dikembalikan lewat penyesuaian.
     *
     * @var array<string, array{bentuk: string, berat: int}>
     */
    private const PRODUK = [
        'CUST-JRS-ATASAN' => ['bentuk' => 'atasan', 'berat' => 250],
        'CUST-SETELAN' => ['bentuk' => 'setelan', 'berat' => 400],
        'CUST-JAKET' => ['bentuk' => 'atasan', 'berat' => 400],
        'CUST-TRAINING' => ['bentuk' => 'bawahan', 'berat' => 300],
        'CUST-CELANA-PENDEK' => ['bentuk' => 'bawahan', 'berat' => 200],
        'CUST-KAOS-OBLONG' => ['bentuk' => 'atasan', 'berat' => 180],
        'CUST-KAOS-KERAH' => ['bentuk' => 'atasan', 'berat' => 220],
        'CUST-KAOS-KATUN' => ['bentuk' => 'atasan', 'berat' => 200],
        'CUST-SERAGAM' => ['bentuk' => 'setelan', 'berat' => 450],
        'JRS-FUTSAL-FP' => ['bentuk' => 'atasan', 'berat' => 250],
        'KAOS-SEKOLAH-SB' => ['bentuk' => 'atasan', 'berat' => 200],
    ];

    public function run(): void
    {
        foreach (self::PRODUK as $sku => $aturan) {
            $produk = Product::where('sku', $sku)->first();

            if (! $produk) {
                continue;
            }

            // Berat hanya diisi kalau masih kosong: yang sudah ditimbang toko
            // sendiri selalu lebih benar daripada angka bawaan di sini.
            if ((int) $produk->berat_gram === 0) {
                $produk->update(['berat_gram' => $aturan['berat']]);
            }

            foreach (KategoriUkuran::cases() as $kategori) {
                $this->isiPanduan($produk, $kategori, $aturan['bentuk']);
            }

            /*
             * Baris panduan lama yang kolom ukurannya kosong dibuang.
             *
             * Ia peninggalan sebelum angka panduan ada: barisnya menentukan
             * ukuran apa saja yang boleh dipesan, tetapi tidak memberi tahu
             * ukurannya berapa. Di halaman pemesanan ia tampil sebagai baris
             * kosong, dan baris kosong di tabel ukuran lebih membingungkan
             * daripada tidak ada tabel sama sekali.
             */
            // Dibandingkan lewat json_length, bukan disamakan dengan teks "[]":
            // kolom JSON di MySQL tidak pernah cocok dengan perbandingan teks,
            // sehingga barisnya dulu tidak terhapus sama sekali.
            $produk->sizeCharts()
                ->where(fn ($q) => $q->whereNull('ukuran_cm')->orWhereRaw('json_length(ukuran_cm) = 0'))
                ->delete();
        }
    }

    private function isiPanduan(Product $produk, KategoriUkuran $kategori, string $bentuk): void
    {
        $tabel = $bentuk === 'bawahan' ? self::BAWAHAN : self::ATASAN;
        $urutan = 0;

        foreach ($tabel[$kategori->value] as $ukuran => [$a, $b]) {
            $baris = ProductSizeChart::firstOrNew([
                'product_id' => $produk->id,
                'kategori' => $kategori->value,
                'ukuran' => $ukuran,
            ]);

            // Baris yang sudah diisi angkanya tidak disentuh.
            if ($baris->exists && ! empty($baris->ukuran_cm)) {
                $urutan++;

                continue;
            }

            $baris->fill([
                'urutan' => $urutan++,
                'ukuran_cm' => $this->kolom($bentuk, $a, $b),
            ])->save();
        }
    }

    /**
     * Angkanya ditulis polos tanpa satuan: kepala tabel di halaman pemesanan
     * sudah menuliskan "(cm)" sendiri, dan "46 cm cm" adalah yang terbaca kalau
     * satuannya ditulis dua kali.
     *
     * Setelan memakai kolom yang sama dengan atasan; celananya mengikuti ukuran
     * atasannya, dan itu disebut sebagai catatan, bukan sebagai kolom angka.
     *
     * @return list<array{nama: string, nilai: string}>
     */
    private function kolom(string $bentuk, int $a, int $b): array
    {
        return $bentuk === 'bawahan'
            ? [
                ['nama' => 'Lingkar pinggang', 'nilai' => (string) $a],
                ['nama' => 'Panjang celana', 'nilai' => (string) $b],
            ]
            : [
                ['nama' => 'Lebar dada', 'nilai' => (string) $a],
                ['nama' => 'Panjang badan', 'nilai' => (string) $b],
            ];
    }
}
