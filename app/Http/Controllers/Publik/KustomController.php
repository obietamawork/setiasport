<?php

namespace App\Http\Controllers\Publik;

use App\Enums\KategoriUkuran;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Keranjang;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Pemesanan kustom empat langkah: pilih produk, desain & detail, data pemain,
 * lalu cek pesanan (KEP-040).
 *
 * Satu halaman, bukan empat halaman terpisah: seluruh pilihan tetap ada di
 * memori sampai pesanan dibuat, jadi pelanggan bebas mundur memperbaiki bahan
 * tanpa kehilangan daftar pemain yang sudah diketik.
 */
class KustomController extends Controller
{
    public function form(Product $produk): View
    {
        abort_unless($produk->aktif && $produk->punyaJalurKustom(), 404);

        $produk->load(['options.values', 'prices', 'sizeSurcharges', 'images']);

        return view('publik.kustom', [
            'produk' => $produk,
            'kategori' => KategoriUkuran::cases(),

            // Langkah pertama: pindah produk tanpa keluar dari alur pemesanan.
            'produkLain' => Product::where('aktif', true)
                ->where('id', '!=', $produk->id)
                ->whereHas('prices')
                ->orderBy('nama')
                ->get(),
        ]);
    }

    public function simpan(Request $request, Product $produk, Keranjang $keranjang): RedirectResponse
    {
        abort_unless($produk->aktif && $produk->punyaJalurKustom(), 404);

        $data = $request->validate([
            'opsi' => ['array'],
            'opsi.*' => ['nullable', 'integer'],
            'baris' => ['required', 'array', 'min:1'],
            'baris.*.kategori' => ['nullable', 'string', 'max:10'],
            'baris.*.ukuran' => ['required', 'string', 'max:10'],
            'baris.*.jumlah' => ['required', 'integer', 'min:1', 'max:5000'],
            'baris.*.nama_punggung' => ['nullable', 'string', 'max:60'],
            'baris.*.nomor' => ['nullable', 'string', 'max:5'],
            'desain_sumber' => ['required', 'in:katalog,sendiri,admin'],
            'catatan_desain' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'baris' => 'data pemain',
            'baris.*.ukuran' => 'ukuran',
            'baris.*.jumlah' => 'jumlah',
        ]);

        $opsi = collect($data['opsi'] ?? [])->filter()->map(fn ($v) => (int) $v)->values()->all();

        try {
            $keranjang->tambahKustomPerUkuran(
                $produk,
                $data['baris'],
                $opsi,
                $this->catatan($data),
            );
        } catch (InvalidArgumentException $e) {
            // Harga dan MOQ dihitung ulang di server; pesan aslinya yang paling
            // menjelaskan, misalnya "Minimum pemesanan 6 pcs".
            return back()->withInput()->withErrors(['baris' => $e->getMessage()]);
        }

        return redirect()->route('keranjang')
            ->with('sukses', $produk->nama.' masuk keranjang. Periksa sekali lagi sebelum melanjutkan.');
    }

    /** @param  array<string, mixed>  $data */
    private function catatan(array $data): ?string
    {
        $sumber = match ($data['desain_sumber']) {
            'katalog' => 'Desain dipilih dari katalog.',
            'sendiri' => 'Pelanggan mengirim desain sendiri.',
            'admin' => 'Minta dibuatkan desain oleh admin.',
        };

        return trim($sumber.' '.trim((string) ($data['catatan_desain'] ?? ''))) ?: null;
    }
}
