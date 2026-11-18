<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Kotak cari di bilah atas (KEP-111): satu tempat untuk kode pesanan, kode
 * ambil, nama atau HP pelanggan, dan nama atau SKU produk.
 *
 * Kode yang tepat langsung membuka pesanannya, karena itulah yang paling
 * sering diketik: pelanggan menyebut kodenya lewat WhatsApp atau di meja
 * kasir, dan halaman hasil berisi satu baris hanya menambah satu klik.
 *
 * Hasil disaring menurut izin. Tautan yang berujung "tidak diizinkan" lebih
 * buruk daripada tidak ada hasil.
 */
class CariController extends Controller
{
    private const BATAS = 10;

    public function __invoke(Request $request): View|RedirectResponse
    {
        $kata = $request->string('q')->squish()->toString();
        $user = auth('web')->user();

        $bolehPesanan = $user->punyaIzin('order.lihat');
        $bolehProduk = $user->punyaIzin('katalog.kelola');

        if ($kata !== '' && $bolehPesanan) {
            $tepat = Order::query()
                ->where('kode', strtoupper($kata))
                ->orWhere('kode_ambil', strtoupper($kata))
                ->first();

            if ($tepat) {
                return redirect()->route('panel.pesanan.show', $tepat);
            }
        }

        $ada = $kata !== '';

        return view('panel.cari', [
            'kata' => $kata,
            'bolehPesanan' => $bolehPesanan,
            'bolehProduk' => $bolehProduk,
            'pesanan' => $ada && $bolehPesanan
                ? Order::query()->with(['customer', 'items.options', 'items.rosters', 'payments', 'adjustments'])
                    ->cari($kata)->latest('id')->limit(self::BATAS)->get()
                : collect(),
            'pelanggan' => $ada && $bolehPesanan
                ? Customer::query()->cari($kata)->withCount('orders')->orderBy('nama')->limit(self::BATAS)->get()
                : collect(),
            'produk' => $ada && $bolehProduk
                ? Product::query()->with(['category', 'images'])->cari($kata)->orderBy('nama')->limit(self::BATAS)->get()
                : collect(),
        ]);
    }
}
