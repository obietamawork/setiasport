<?php

namespace App\Http\Controllers\Panel;

use App\Enums\KategoriUkuran;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductSizeChart;
use App\Models\ProductSizeSurcharge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Matriks harga, panduan ukuran, dan tambahan ukuran besar (KEP-052).
 *
 * Sebelum ini ketiganya hanya bisa diisi lewat seeder, artinya tiap kali toko
 * mengubah harga, ia harus melewati pengembang. Itu menyalahi cara kerja
 * seluruh panel ini: angka yang dipakai berjualan adalah milik pemilik toko,
 * dan ia harus bisa mengubahnya sendiri.
 */
class ProdukHargaController extends Controller
{
    /**
     * Seluruh sel matriks disimpan sekali jalan, bukan baris demi baris.
     * Harga bahan dan kategori dibaca sebagai satu tabel oleh pemilik toko,
     * dan menyimpannya satu per satu membuat tabel setengah terisi kalau
     * halamannya keburu ditutup.
     */
    public function simpanMatriks(Request $request, Product $produk): RedirectResponse
    {
        $data = $request->validate([
            'opsi_id' => ['required', 'integer'],
            'harga' => ['array'],
            'harga.*' => ['array'],
            'harga.*.*' => ['nullable', 'integer', 'min:0', 'max:999999999'],
        ], [], ['opsi_id' => 'opsi penentu harga']);

        $opsi = $produk->options()->whereKey($data['opsi_id'])->with('values')->firstOrFail();
        $nilaiSah = $opsi->values->pluck('id')->all();

        $terisi = 0;

        DB::transaction(function () use ($produk, $data, $nilaiSah, &$terisi) {
            foreach ($data['harga'] ?? [] as $kategori => $baris) {
                $kat = $kategori === 'SEMUA' ? null : KategoriUkuran::tryFrom($kategori)?->value;

                if ($kategori !== 'SEMUA' && $kat === null) {
                    continue;
                }

                foreach ($baris as $valueId => $harga) {
                    if (! in_array((int) $valueId, $nilaiSah, true)) {
                        continue;
                    }

                    $sel = $this->sel($produk, $kat, (int) $valueId);

                    // Sel yang dikosongkan berarti kombinasi itu tidak dijual,
                    // dan kalkulator harga memang menolaknya (KEP-039).
                    if ($harga === null || $harga === '') {
                        $sel?->delete();

                        continue;
                    }

                    ProductPrice::updateOrCreate(
                        ['id' => $sel?->id],
                        [
                            'product_id' => $produk->id,
                            'kategori' => $kat,
                            'option_value_id' => (int) $valueId,
                            'harga_satuan' => (int) $harga,
                        ]
                    );

                    $terisi++;
                }
            }
        });

        ActivityLog::catat('produk.matriks-harga', $produk, [
            'produk' => $produk->sku,
            'sel_terisi' => $terisi,
        ]);

        return back()->with('sukses', "Matriks harga disimpan, {$terisi} sel terisi.");
    }

    /** Panduan ukuran satu kategori sekaligus, karena dibaca sebagai satu tabel. */
    public function simpanPanduan(Request $request, Product $produk): RedirectResponse
    {
        $data = $request->validate([
            'kategori' => ['nullable', 'string', 'max:10'],
            'kolom' => ['required', 'array', 'min:1', 'max:3'],
            'kolom.*' => ['nullable', 'string', 'max:20'],
            'baris' => ['array'],
            'baris.*.ukuran' => ['nullable', 'string', 'max:10'],
            'baris.*.nilai' => ['array'],
            'baris.*.nilai.*' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ], [], ['kolom' => 'nama kolom']);

        $kat = KategoriUkuran::tryFrom((string) ($data['kategori'] ?? ''))?->value;
        $kolom = array_values(array_filter($data['kolom'], fn ($k) => trim((string) $k) !== ''));

        if ($kolom === []) {
            return back()->withErrors('Isi minimal satu nama kolom, misalnya "Lebar".');
        }

        $baris = collect($data['baris'] ?? [])
            ->filter(fn ($b) => trim((string) ($b['ukuran'] ?? '')) !== '')
            ->values();

        DB::transaction(function () use ($produk, $kat, $kolom, $baris) {
            $produk->sizeCharts()
                ->when($kat === null, fn ($q) => $q->whereNull('kategori'))
                ->when($kat !== null, fn ($q) => $q->where('kategori', $kat))
                ->delete();

            foreach ($baris as $urutan => $b) {
                $ukuranCm = [];

                foreach ($kolom as $i => $nama) {
                    $nilai = $b['nilai'][$i] ?? null;

                    if ($nilai !== null && $nilai !== '') {
                        $ukuranCm[] = ['nama' => trim($nama), 'nilai' => (int) $nilai];
                    }
                }

                $produk->sizeCharts()->create([
                    'kategori' => $kat,
                    'ukuran' => strtoupper(trim($b['ukuran'])),
                    'ukuran_cm' => $ukuranCm,
                    'urutan' => $urutan,
                ]);
            }
        });

        ActivityLog::catat('produk.panduan-ukuran', $produk, [
            'produk' => $produk->sku,
            'kategori' => $kat ?? 'semua',
            'baris' => $baris->count(),
        ]);

        return back()->with('sukses', $baris->isEmpty()
            ? 'Panduan ukuran dikosongkan. Daftar ukuran kembali memakai bawaan sistem.'
            : 'Panduan ukuran disimpan, '.$baris->count().' baris.');
    }

    public function simpanTambahanUkuran(Request $request, Product $produk): RedirectResponse
    {
        $data = $request->validate([
            'ukuran' => ['required', 'string', 'max:10'],
            'tambahan_harga' => ['required', 'integer', 'min:0', 'max:999999999'],
        ], [], ['tambahan_harga' => 'tambahan harga']);

        $produk->sizeSurcharges()->updateOrCreate(
            ['ukuran' => strtoupper(trim($data['ukuran']))],
            ['tambahan_harga' => $data['tambahan_harga']]
        );

        ActivityLog::catat('produk.tambahan-ukuran', $produk, [
            'produk' => $produk->sku,
            'ukuran' => $data['ukuran'],
            'tambahan' => $data['tambahan_harga'],
        ]);

        return back()->with('sukses', 'Tambahan ukuran '.strtoupper($data['ukuran']).' disimpan.');
    }

    public function hapusTambahanUkuran(Product $produk, ProductSizeSurcharge $tambahan): RedirectResponse
    {
        abort_unless($tambahan->product_id === $produk->id, 404);

        $ukuran = $tambahan->ukuran;
        $tambahan->delete();

        ActivityLog::catat('produk.tambahan-ukuran-hapus', $produk, [
            'produk' => $produk->sku,
            'ukuran' => $ukuran,
        ]);

        return back()->with('sukses', "Tambahan ukuran {$ukuran} dihapus.");
    }

    /**
     * Sel matriks dicari manual, bukan lewat updateOrCreate: MySQL menganggap
     * dua NULL sebagai nilai berbeda di indeks unik, sehingga baris "berlaku
     * semua kategori" akan tergandakan diam-diam.
     */
    private function sel(Product $produk, ?string $kategori, int $valueId): ?ProductPrice
    {
        return $produk->prices()
            ->where('option_value_id', $valueId)
            ->when($kategori === null, fn ($q) => $q->whereNull('kategori'))
            ->when($kategori !== null, fn ($q) => $q->where('kategori', $kategori))
            ->first();
    }
}
