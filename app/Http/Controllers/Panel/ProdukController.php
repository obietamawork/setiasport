<?php

namespace App\Http\Controllers\Panel;

use App\Enums\FulfillmentType;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProdukController extends Controller
{
    public function index(Request $request)
    {
        $saring = $request->string('saring')->toString();

        $dasar = fn () => Product::query()
            ->when($request->filled('q'), function ($q) use ($request) {
                $cari = '%'.$request->string('q').'%';
                $q->where(fn ($w) => $w->where('nama', 'like', $cari)->orWhere('sku', 'like', $cari));
            });

        /*
         * Penyaring "belum lengkap" menggabungkan dua hal yang sama-sama membuat
         * produk tidak bisa dijual sendiri: tanpa foto pelanggan tidak percaya,
         * dan tanpa harga ia harus bertanya ke admin. Keduanya muncul di
         * dashboard sebagai peringatan; di sini disediakan jalan memperbaikinya.
         */
        $belumLengkap = fn ($q) => $q->where(fn ($w) => $w
            ->whereDoesntHave('images')
            ->orWhere(fn ($h) => $h
                ->whereIn('fulfillment_type', [FulfillmentType::PREORDER->value, FulfillmentType::BOTH->value])
                ->whereDoesntHave('priceTiers')
                ->whereDoesntHave('prices')));

        return view('panel.produk.index', [
            'saring' => $saring,
            'jumlah' => [
                '' => $dasar()->count(),
                'tayang' => $dasar()->where('aktif', true)->count(),
                'sembunyi' => $dasar()->where('aktif', false)->count(),
                'belum-lengkap' => $dasar()->where('aktif', true)->tap($belumLengkap)->count(),
            ],
            'produk' => $dasar()
                ->with(['category', 'images'])
                ->withCount('variants')
                ->when($saring === 'tayang', fn ($q) => $q->where('aktif', true))
                ->when($saring === 'sembunyi', fn ($q) => $q->where('aktif', false))
                ->when($saring === 'belum-lengkap',
                    fn ($q) => $q->where('aktif', true)->tap($belumLengkap))
                ->orderBy('nama')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function create()
    {
        return view('panel.produk.form', [
            'produk' => new Product([
                'aktif' => true, 'moq' => 1, 'lead_time_hari' => 0, 'harga_dasar' => 0,
                'berat_gram' => 0, 'diskon_min_qty' => 0, 'diskon_per_pcs' => 0,
            ]),
            'kategori' => $this->pilihanKategori(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $produk = Product::create($this->validasi($request));

        ActivityLog::catat('produk.dibuat', $produk, ['sku' => $produk->sku]);

        return redirect()->route('panel.produk.edit', $produk)
            ->with('sukses', "Produk \"{$produk->nama}\" dibuat. Lengkapi varian dan harganya di bawah.");
    }

    public function edit(Product $produk)
    {
        $produk->load(['variants', 'priceTiers', 'options.values', 'prices', 'sizeCharts', 'sizeSurcharges']);

        return view('panel.produk.form', [
            'produk' => $produk,
            'kategori' => $this->pilihanKategori(),
        ]);
    }

    public function update(Request $request, Product $produk): RedirectResponse
    {
        $produk->update($this->validasi($request, $produk));

        ActivityLog::catat('produk.diubah', $produk, ['sku' => $produk->sku]);

        return back()->with('sukses', 'Perubahan produk disimpan.');
    }

    public function destroy(Product $produk): RedirectResponse
    {
        if ($produk->orderItems()->exists()) {
            return back()->withErrors(
                'Produk ini sudah pernah dipesan, jadi tidak bisa dihapus: riwayat pesanan '
                .'akan ikut rusak. Sembunyikan saja dari katalog.'
            );
        }

        $nama = $produk->nama;
        $produk->delete();

        ActivityLog::catat('produk.dihapus', null, ['nama' => $nama]);

        return redirect()->route('panel.produk.index')->with('sukses', "Produk \"{$nama}\" dihapus.");
    }

    private function pilihanKategori()
    {
        return Category::with('parent')->whereNotNull('parent_id')->orderBy('nama')->get()
            ->merge(Category::whereNull('parent_id')->whereDoesntHave('children')->get())
            ->sortBy(fn (Category $k) => ($k->parent?->nama ?? '').$k->nama);
    }

    private function validasi(Request $request, ?Product $produk = null): array
    {
        $jalur = $request->string('fulfillment_type')->toString();
        $adaStok = in_array($jalur, [FulfillmentType::READY_STOCK->value, FulfillmentType::BOTH->value], true);
        $adaKustom = in_array($jalur, [FulfillmentType::PREORDER->value, FulfillmentType::BOTH->value], true);

        $data = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'sku' => ['required', 'string', 'max:60', 'alpha_dash',
                Rule::unique('products', 'sku')->ignore($produk?->id)],
            'nama' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:180', 'alpha_dash',
                Rule::unique('products', 'slug')->ignore($produk?->id)],
            'deskripsi' => ['nullable', 'string', 'max:5000'],
            'fulfillment_type' => ['required', Rule::enum(FulfillmentType::class)],

            // Harga dasar hanya bermakna di jalur ready stock; tier mengurus jalur kustom.
            'harga_dasar' => [$adaStok ? 'required' : 'nullable', 'integer', 'min:0', 'max:999999999'],

            'moq' => [$adaKustom ? 'required' : 'nullable', 'integer', 'min:1', 'max:100000'],
            'berat_gram' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'diskon_min_qty' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'diskon_per_pcs' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'lead_time_hari' => [$adaKustom ? 'required' : 'nullable', 'integer', 'min:1', 'max:365'],
            'roster_wajib' => ['boolean'],
            'aktif' => ['boolean'],
        ], [
            'harga_dasar.required' => 'Harga dasar wajib diisi untuk produk yang dijual dari stok.',
            'moq.required' => 'Minimum pemesanan wajib diisi untuk produk kustom.',
            'lead_time_hari.required' => 'Waktu pengerjaan wajib diisi untuk produk kustom.',
        ], [
            'category_id' => 'kategori',
            'fulfillment_type' => 'cara beli',
            'harga_dasar' => 'harga dasar',
            'moq' => 'minimum pemesanan',
            'berat_gram' => 'berat satuan',
            'diskon_min_qty' => 'potongan borongan mulai',
            'diskon_per_pcs' => 'potongan per pcs',
            'lead_time_hari' => 'waktu pengerjaan',
        ]);

        $data['slug'] = $data['slug'] ?: Str::slug($data['nama']);
        $data['harga_dasar'] = $data['harga_dasar'] ?? 0;
        $data['moq'] = $data['moq'] ?? 1;
        $data['berat_gram'] = $data['berat_gram'] ?? 0;
        $data['diskon_min_qty'] = $data['diskon_min_qty'] ?? 0;
        $data['diskon_per_pcs'] = $data['diskon_per_pcs'] ?? 0;
        $data['lead_time_hari'] = $data['lead_time_hari'] ?? 0;
        $data['roster_wajib'] = $request->boolean('roster_wajib');
        $data['aktif'] = $request->boolean('aktif');

        return $data;
    }
}
