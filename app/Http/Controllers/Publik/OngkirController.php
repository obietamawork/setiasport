<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use App\Services\Keranjang;
use App\Services\Ongkir\Pengiriman;
use App\Services\Ongkir\TarifKirim;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pilihan pengiriman di halaman checkout (KEP-058).
 *
 * Dipanggil dari halaman, bukan dirender sekaligus, karena tarifnya baru bisa
 * dihitung setelah pelanggan menyebut tujuannya. Beratnya TIDAK diterima dari
 * browser: ia dijumlahkan di sini dari isi keranjang, supaya ongkir tidak bisa
 * ditekan dengan mengubah satu angka di formulir (KEP-009).
 */
class OngkirController extends Controller
{
    public function __construct(
        private Pengiriman $pengiriman,
        private Keranjang $keranjang,
    ) {}

    public function area(Request $request): JsonResponse
    {
        /*
         * Tetap diam selama ongkir otomatis belum benar-benar menyala
         * (KEP-067): mencari area tujuan tidak ada gunanya kalau area asal
         * toko belum diisi, sebab hitung tarifnya tetap akan kosong. Tanpa
         * penjagaan ini, jeda "kunci API sudah ada tapi area asal belum"
         * membuat kotak pencarian publik ini diam-diam memanggil Biteship
         * berbayar — throttle:30,1 di routes/web.php membatasi lajunya,
         * tapi tidak mencegahnya terjadi sama sekali.
         */
        if (! $this->pengiriman->otomatis()) {
            return response()->json(['area' => []]);
        }

        $kata = trim((string) $request->query('q', ''));

        return response()->json(['area' => $this->pengiriman->cariArea($kata)]);
    }

    public function tarif(Request $request): JsonResponse
    {
        $area = trim((string) $request->query('area', ''));

        if ($area === '' || $this->keranjang->kosong()) {
            return response()->json(['tarif' => [], 'berat' => 0]);
        }

        $berat = $this->keranjang->beratGram();
        $subtotal = $this->keranjang->total();

        $tarif = collect($this->pengiriman->pilihan($area, $berat, $subtotal))
            ->map(fn (TarifKirim $t) => [
                'kode' => $t->kode(),
                'label' => $t->label(),
                'harga' => $t->harga,
            ])
            ->all();

        return response()->json([
            'tarif' => $tarif,
            'berat' => $berat,
            'gratis' => $this->pengiriman->gratis($subtotal),
        ]);
    }
}
