<?php

namespace App\Http\Controllers\Panel;

use App\Enums\FulfillmentType;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\BarangMasuk;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Stok barang ready stock (KEP-120): daftar per produk, barang masuk dari
 * supplier, dan penyesuaian manual. Ketiganya dicatat, karena stok yang
 * berubah tanpa jejak adalah sumber perselisihan internal (docs/04).
 */
class StokController extends Controller
{
    /**
     * Daftar stok per produk, varian bisa dibuka-tutup, seperti gambar contoh
     * klien. Ringkasan angka di atasnya sengaja tidak ada: klien mencoretnya.
     */
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $status = in_array($status, ['aman', 'menipis', 'habis'], true) ? $status : '';
        $kategori = $request->integer('kategori') ?: null;

        $varian = $this->kueriVarian($request)
            ->when($kategori, fn ($q) => $q->whereHas('product', fn ($p) => $p->where('category_id', $kategori)))
            ->with('product.images')
            ->get()
            ->when($status !== '', fn (Collection $c) => $c->filter(fn (ProductVariant $v) => $this->keadaan($v) === $status));

        return view('panel.stok.index', [
            'varian' => $this->kelompokkan($varian),
            'status' => $status,
            'kategori' => $kategori,
            'pilihanKategori' => Category::query()
                ->whereHas('products', fn ($p) => $p->whereIn('fulfillment_type', $this->jalurStok()))
                ->orderBy('nama')
                ->get(),
            // Supplier yang pernah diketik ditawarkan lagi, supaya "CV Sumber
            // Kain" tidak tercatat dengan lima ejaan berbeda.
            'supplier' => BarangMasuk::query()->distinct()->orderBy('supplier')->pluck('supplier'),
            // Untuk formulir barang masuk: semua produk ready stock, tidak ikut tersaring.
            'semuaProduk' => Product::query()
                ->whereIn('fulfillment_type', $this->jalurStok())
                ->with('variants')
                ->orderBy('nama')
                ->get(),
        ]);
    }

    /** Formulir opname: semua angka stok sekaligus, dengan alasan wajib. */
    public function penyesuaian(Request $request): View
    {
        $varian = $this->kueriVarian($request)
            ->when($request->string('saring')->toString() === 'habis',
                fn ($q) => $q->whereColumn('stok', '<=', 'stok_dipesan'))
            ->get();

        return view('panel.stok.penyesuaian', ['varian' => $this->kelompokkan($varian)]);
    }

    /**
     * Barang masuk dari supplier. Satu kiriman bisa berisi beberapa varian;
     * baris yang jumlahnya dikosongkan dilewati, tetapi minimal satu harus
     * berisi. Stok dan catatannya disimpan dalam satu transaksi.
     */
    public function masuk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'supplier' => ['required', 'string', 'max:100'],
            'tanggal' => ['required', 'date', 'before_or_equal:today'],
            'harga_beli' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'catatan' => ['nullable', 'string', 'max:255'],
            'baris' => ['required', 'array', 'max:200', function (string $atribut, mixed $nilai, \Closure $gagal) {
                if (! collect((array) $nilai)->contains(fn ($b) => (int) ($b['jumlah'] ?? 0) > 0)) {
                    $gagal('Isi jumlah minimal satu varian yang datang.');
                }
            }],
            'baris.*.variant_id' => ['required', 'integer', Rule::exists('product_variants', 'id')],
            'baris.*.jumlah' => ['nullable', 'integer', 'min:1', 'max:99999'],
        ], [
            'supplier.required' => 'Tulis nama supplier pengirim barangnya.',
            'tanggal.before_or_equal' => 'Tanggal barang masuk tidak boleh di masa depan.',
            'baris.*.jumlah.min' => 'Jumlah barang masuk minimal 1. Kosongkan saja varian yang tidak dikirim.',
            'baris.*.variant_id.exists' => 'Salah satu varian sudah tidak ada. Muat ulang halamannya.',
        ], [
            'supplier' => 'supplier', 'tanggal' => 'tanggal', 'harga_beli' => 'harga beli',
            'baris' => 'barang', 'baris.*.jumlah' => 'jumlah', 'baris.*.variant_id' => 'varian',
        ]);

        $isi = collect($data['baris'])->filter(fn (array $b) => (int) ($b['jumlah'] ?? 0) > 0)->values();

        $tercatat = DB::transaction(function () use ($isi, $data) {
            return $isi->map(function (array $b) use ($data) {
                $varian = ProductVariant::lockForUpdate()->findOrFail($b['variant_id']);
                $sebelum = $varian->stok;
                $varian->increment('stok', (int) $b['jumlah']);

                BarangMasuk::create([
                    'product_variant_id' => $varian->id,
                    'jumlah' => (int) $b['jumlah'],
                    'supplier' => $data['supplier'],
                    'harga_beli' => $data['harga_beli'] ?? null,
                    'tanggal' => $data['tanggal'],
                    'catatan' => $data['catatan'] ?? null,
                    'user_id' => auth('web')->id(),
                ]);

                return ['sku' => $varian->sku, 'dari' => $sebelum, 'ke' => $sebelum + (int) $b['jumlah']];
            });
        });

        ActivityLog::catat('stok.masuk', null, [
            'supplier' => $data['supplier'],
            'perubahan' => $tercatat->all(),
        ]);

        return back()->with('sukses', 'Barang masuk dari '.$data['supplier'].' dicatat: '
            .$isi->sum('jumlah').' pcs untuk '.$isi->count().' varian.');
    }

    /**
     * Penyesuaian stok manual. Selalu tercatat beserta alasannya, stok yang
     * berubah tanpa jejak adalah sumber perselisihan internal (docs/04).
     */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'stok' => ['required', 'array'],
            'stok.*' => ['required', 'integer', 'min:0', 'max:999999'],
            'alasan' => ['required', 'string', 'max:120'],
        ], [
            'alasan.required' => 'Tulis alasan penyesuaian, misalnya "opname 7 September" atau "barang rusak".',
        ], ['alasan' => 'alasan penyesuaian']);

        $perubahan = [];

        foreach ($data['stok'] as $id => $jumlah) {
            $varian = ProductVariant::find($id);

            if (! $varian || $varian->stok === (int) $jumlah) {
                continue;
            }

            $perubahan[] = [
                'sku' => $varian->sku,
                'dari' => $varian->stok,
                'ke' => (int) $jumlah,
            ];

            $varian->update(['stok' => (int) $jumlah]);
        }

        if ($perubahan === []) {
            return back()->with('sukses', 'Tidak ada angka stok yang berubah.');
        }

        ActivityLog::catat('stok.disesuaikan', null, [
            'alasan' => $data['alasan'],
            'perubahan' => $perubahan,
        ]);

        /*
         * Stok fisik yang turun di bawah jumlah yang sudah dipesan (KEP-098),
         * misalnya barang rusak saat opname. Pesanan yang memegangnya tetap
         * berjalan, jadi orang toko harus tahu sekarang, bukan saat pelanggan
         * datang mengambil barang yang ternyata tidak ada.
         */
        $kurang = ProductVariant::with('product')
            ->whereIn('sku', array_column($perubahan, 'sku'))
            ->whereColumn('stok', '<', 'stok_dipesan')
            ->get();

        $redirect = back()->with('sukses', count($perubahan).' varian diperbarui dan dicatat.');

        return $kurang->isEmpty() ? $redirect : $redirect->with('peringatan',
            'Stok di bawah jumlah yang sudah dipesan pelanggan: '
            .$kurang->map(fn (ProductVariant $v) => $v->product->nama.' '.$v->label
                .' (ada '.$v->stok.', dipesan '.$v->stok_dipesan.')')->implode(', ')
            .'. Cari pesanannya di daftar pesanan dan kabari pelanggannya.');
    }

    /** @return list<string> */
    private function jalurStok(): array
    {
        return [FulfillmentType::READY_STOCK->value, FulfillmentType::BOTH->value];
    }

    /** @return Builder<ProductVariant> */
    private function kueriVarian(Request $request): Builder
    {
        return ProductVariant::query()
            ->with('product')
            ->whereHas('product', fn ($q) => $q->whereIn('fulfillment_type', $this->jalurStok()))
            ->when($request->filled('q'), function ($q) use ($request) {
                // % dan _ dibaca sebagai huruf biasa, bukan pola (KEP-105, seperti pesanan di KEP-099).
                $cari = '%'.addcslashes($request->string('q')->toString(), '%_\\').'%';
                $q->where(fn ($w) => $w->where('sku', 'like', $cari)
                    ->orWhereHas('product', fn ($p) => $p->where('nama', 'like', $cari)));
            });
    }

    /** Aman, menipis (sisa 1 sampai 3), atau habis, dari stok yang bisa dijual. */
    private function keadaan(ProductVariant $v): string
    {
        return match (true) {
            $v->stok_tersedia <= 0 => 'habis',
            $v->stok_tersedia <= 3 => 'menipis',
            default => 'aman',
        };
    }

    /**
     * Varian dikelompokkan per produk, ukurannya diurutkan seperti orang
     * membacanya (S, M, L, XL), bukan alfabetis.
     *
     * @param  Collection<int, ProductVariant>  $varian
     * @return Collection<int|string, Collection<int, ProductVariant>>
     */
    private function kelompokkan(Collection $varian): Collection
    {
        return $varian
            ->sortBy(fn (ProductVariant $v) => $v->product->nama)
            ->groupBy('product_id')
            ->map(function (Collection $grup) {
                $urutan = urutkan_ukuran($grup->pluck('ukuran'));

                return $grup->sortBy(fn (ProductVariant $v) => [
                    array_search((string) $v->ukuran, $urutan, true) === false
                        ? 999
                        : array_search((string) $v->ukuran, $urutan, true),
                    (string) $v->warna,
                ])->values();
            });
    }
}
