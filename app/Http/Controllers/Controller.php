<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Tujuan setelah barang masuk keranjang (KEP-091). "Beli sekarang" dan
     * "Pesan sekarang" langsung ke data pemesan; "Masukkan ke keranjang"
     * kembali ke keranjang. Barangnya sudah masuk keranjang di kedua jalan,
     * jadi satu-satunya beda adalah halaman berikutnya.
     */
    protected function sesudahDitambah(Request $request, string $pesan): RedirectResponse
    {
        if ($request->input('lanjut') === 'checkout') {
            return redirect()->route('checkout')->with('sukses', $pesan.' Lengkapi data pemesan di bawah.');
        }

        return redirect()->route('keranjang')->with('sukses', $pesan);
    }
}
