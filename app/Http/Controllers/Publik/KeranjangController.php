<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Keranjang;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class KeranjangController extends Controller
{
    public function __construct(private Keranjang $keranjang) {}

    public function index()
    {
        return view('publik.keranjang', [
            'isi' => $this->keranjang->isi(),
            'total' => $this->keranjang->total(),
            'adaMasalah' => $this->keranjang->adaMasalah(),
        ]);
    }

    public function tambah(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'variant_id' => ['required', 'exists:product_variants,id'],
            'qty' => ['required', 'integer', 'min:1', 'max:999'],
        ], [], ['variant_id' => 'varian produk', 'qty' => 'jumlah']);

        $varian = ProductVariant::with('product')->findOrFail($data['variant_id']);

        abort_unless($varian->product->aktif && $varian->aktif, 404);

        try {
            $this->keranjang->tambahReadyStock($varian, $data['qty']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors($e->getMessage());
        }

        return redirect()->route('keranjang')
            ->with('sukses', "{$varian->product->nama} dimasukkan ke keranjang.");
    }

    /**
     * Jalur kustom. Harga tetap dihitung ulang di server oleh PriceCalculator, * angka apa pun yang dikirim konfigurator di browser tidak dipercaya (KEP-009).
     */
    public function tambahKustom(Request $request, Product $produk): RedirectResponse
    {
        abort_unless($produk->aktif && $produk->punyaJalurKustom(), 404);

        // Opsi yang tidak wajib mengirim nilai kosong saat pelanggan memilih
        // "Tidak dipakai". Dibuang lebih dulu, karena kosong berarti tidak
        // memilih, bukan memilih sesuatu yang tidak sah.
        $request->merge([
            'opsi' => array_values(array_filter(
                (array) $request->input('opsi', []),
                fn ($v) => $v !== '' && $v !== null
            )),
        ]);

        $data = $request->validate([
            'qty' => ['required', 'integer', 'min:1', 'max:100000'],
            'opsi' => ['array'],
            'opsi.*' => ['integer', 'exists:option_values,id'],
        ], [
            'opsi.*.integer' => 'Ada pilihan yang tidak dikenali. Muat ulang halaman lalu coba lagi.',
            'opsi.*.exists' => 'Ada pilihan yang sudah tidak tersedia. Muat ulang halaman lalu coba lagi.',
        ], ['qty' => 'jumlah']);

        try {
            $this->keranjang->tambahKustom($produk, $data['qty'], $data['opsi'] ?? []);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors($e->getMessage())->withInput();
        }

        return redirect()->route('keranjang')
            ->with('sukses', "{$produk->nama} ({$data['qty']} pcs) dimasukkan ke keranjang.");
    }

    public function ubah(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kunci' => ['required', 'string'],
            'qty' => ['required', 'integer', 'min:0', 'max:999'],
        ], [], ['qty' => 'jumlah']);

        try {
            $this->keranjang->ubahJumlah($data['kunci'], $data['qty']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors($e->getMessage());
        }

        return back()->with('sukses', 'Keranjang diperbarui.');
    }

    public function hapus(Request $request): RedirectResponse
    {
        $this->keranjang->hapus($request->string('kunci')->toString());

        return back()->with('sukses', 'Barang dikeluarkan dari keranjang.');
    }
}
