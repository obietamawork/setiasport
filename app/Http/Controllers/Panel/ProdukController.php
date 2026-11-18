<?php

namespace App\Http\Controllers\Panel;

use App\Enums\FulfillmentType;
use App\Enums\PolaPakaian;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Material;
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

        /*
         * Penyaring kategori dan status stok dari gambar contoh klien
         * (KEP-116). "Habis" berarti barang ready stock yang tidak punya satu
         * varian pun dengan stok tersedia.
         */
        $kategori = $request->integer('kategori') ?: null;
        $stok = $request->string('stok')->toString();
        $stok = in_array($stok, ['habis', ...array_column(FulfillmentType::cases(), 'value')], true) ? $stok : '';

        $dasar = fn () => Product::query()
            ->when($request->filled('q'), fn ($q) => $q->cari($request->string('q')->toString()))
            ->when($kategori, fn ($q) => $q->where('category_id', $kategori))
            ->when($stok === 'habis', fn ($q) => $q
                ->whereIn('fulfillment_type', [FulfillmentType::READY_STOCK->value, FulfillmentType::BOTH->value])
                ->whereDoesntHave('variants', fn ($v) => $v->whereColumn('stok', '>', 'stok_dipesan')))
            ->when($stok !== '' && $stok !== 'habis', fn ($q) => $q->where('fulfillment_type', $stok));

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
            'kategori' => $kategori,
            'stok' => $stok,
            'pilihanKategori' => $this->pilihanKategori(),
            'jumlah' => [
                '' => $dasar()->count(),
                'tayang' => $dasar()->where('aktif', true)->count(),
                'sembunyi' => $dasar()->where('aktif', false)->count(),
                'belum-lengkap' => $dasar()->where('aktif', true)->tap($belumLengkap)->count(),
            ],
            'produk' => $dasar()
                ->with(['category', 'images', 'priceTiers', 'prices'])
                ->withCount('variants')
                ->withSum('variants', 'stok')
                ->withSum('variants', 'stok_dipesan')
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
            'semuaBahan' => Material::orderBy('nama')->get(),
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
        $produk->load([
            'variants', 'priceTiers', 'options.values.material', 'prices', 'sizeCharts', 'sizeSurcharges',
            'images.optionValue.option',
        ]);

        return view('panel.produk.form', [
            'produk' => $produk,
            'kategori' => $this->pilihanKategori(),
            'semuaBahan' => Material::orderBy('nama')->get(),
        ]);
    }

    public function update(Request $request, Product $produk): RedirectResponse
    {
        $produk->update($this->validasi($request, $produk));

        ActivityLog::catat('produk.diubah', $produk, ['sku' => $produk->sku]);

        return back()->with('sukses', 'Perubahan produk disimpan.');
    }

    /**
     * Aksi massal dari kotak centang daftar produk (KEP-116): tayangkan,
     * sembunyikan, atau pindahkan kategori. Hapus massal sengaja tidak ada:
     * produk yang pernah dipesan memang tidak boleh dihapus, dan menghapus
     * puluhan produk dengan satu klik terlalu mudah terjadi tanpa sengaja.
     */
    public function massal(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'produk' => ['required', 'array', 'max:100'],
            'produk.*' => ['integer', 'distinct', Rule::exists('products', 'id')],
            'aksi' => ['required', Rule::in(['tayangkan', 'sembunyikan', 'kategori'])],
            'category_id' => [
                Rule::requiredIf($request->input('aksi') === 'kategori'),
                'nullable', 'integer', Rule::exists('categories', 'id'),
            ],
        ], [
            'produk.required' => 'Centang dulu produk yang mau diubah.',
            'produk.*.exists' => 'Salah satu produk yang dicentang sudah tidak ada. Muat ulang halamannya.',
            'category_id.required' => 'Pilih kategori tujuannya.',
        ], ['produk' => 'produk', 'aksi' => 'aksi', 'category_id' => 'kategori']);

        $aksi = (string) $data['aksi'];

        $ubah = match ($aksi) {
            'tayangkan' => ['aktif' => true],
            'sembunyikan' => ['aktif' => false],
            default => ['category_id' => (int) $data['category_id']],
        };

        $produk = Product::whereIn('id', $data['produk'])->get();
        $produk->each(fn (Product $p) => $p->update($ubah));

        ActivityLog::catat('produk.massal', null, [
            'aksi' => $data['aksi'],
            'sku' => $produk->pluck('sku')->all(),
            ...($data['aksi'] === 'kategori' ? ['category_id' => (int) $data['category_id']] : []),
        ]);

        $kata = ['tayangkan' => 'ditayangkan', 'sembunyikan' => 'disembunyikan', 'kategori' => 'dipindahkan kategorinya'];

        return back()->with('sukses', $produk->count().' produk '.$kata[$data['aksi']].'.');
    }

    /** Tayangkan atau sembunyikan satu produk dari menu aksi daftar (KEP-116). */
    public function alihTayang(Product $produk): RedirectResponse
    {
        $produk->update(['aktif' => ! $produk->aktif]);

        ActivityLog::catat($produk->aktif ? 'produk.ditayangkan' : 'produk.disembunyikan', $produk, ['sku' => $produk->sku]);

        return back()->with('sukses', "\"{$produk->nama}\" ".($produk->aktif ? 'ditayangkan.' : 'disembunyikan dari katalog.'));
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
            ->merge(Category::with('parent')->whereNull('parent_id')->whereDoesntHave('children')->get())
            ->sortBy(fn (Category $k) => ($k->parent?->nama ?? '').$k->nama);
    }

    private function validasi(Request $request, ?Product $produk = null): array
    {
        // Dibaca sebelum divalidasi, jadi isian array tidak boleh sampai ke
        // string() dan berakhir galat 500 (KEP-104); aturan di bawah menolaknya.
        $jalur = is_string($request->input('fulfillment_type')) ? $request->input('fulfillment_type') : '';
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
            'pola' => ['nullable', Rule::enum(PolaPakaian::class)],

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
            'pola' => 'gambar pola',
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
