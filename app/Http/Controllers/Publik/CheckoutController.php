<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use App\Services\Checkout;
use App\Services\Keranjang;
use App\Services\PengirimEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function __construct(
        private Keranjang $keranjang,
        private Checkout $checkout,
        private PengirimEmail $email,
    ) {}

    public function form()
    {
        if ($this->keranjang->kosong()) {
            return redirect()->route('keranjang');
        }

        return view('publik.checkout', [
            'isi' => $this->keranjang->isi(),
            'total' => $this->keranjang->total(),
            'adaMasalah' => $this->keranjang->adaMasalah(),
        ]);
    }

    public function proses(Request $request): RedirectResponse
    {
        if ($this->keranjang->kosong()) {
            return redirect()->route('keranjang');
        }

        if ($this->keranjang->adaMasalah()) {
            return redirect()->route('keranjang')
                ->withErrors('Ada barang yang stoknya berubah. Periksa keranjang Anda dulu.');
        }

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            // Email wajib: ia satu-satunya kanal otomatis ke pelanggan (KEP-004),
            // sekaligus kunci untuk membuka halaman pesanan tanpa akun (KEP-008).
            'email' => ['required', 'email', 'max:150'],
            'no_hp' => ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s]+$/'],
            'catatan' => ['nullable', 'string', 'max:500'],
            'setuju' => ['accepted'],
        ], [
            'email.required' => 'Email wajib diisi — ke sanalah tautan pesanan dan kabar progres dikirim.',
            'no_hp.regex' => 'Nomor HP hanya boleh berisi angka, spasi, tanda plus, dan strip.',
            'setuju.accepted' => 'Centang persetujuan pengambilan di toko untuk melanjutkan.',
        ], [
            'nama' => 'nama', 'email' => 'email', 'no_hp' => 'nomor HP',
        ]);

        try {
            $order = $this->checkout->proses($data, 'LUNAS');
        } catch (\RuntimeException $e) {
            // Umumnya stok habis diambil pembeli lain di detik terakhir.
            throw ValidationException::withMessages(['nama' => $e->getMessage()]);
        }

        $this->email->kirim($order, 'order-dibuat');

        return redirect()->route('lacak.token', $order->access_token)
            ->with('sukses', 'Pesanan Anda dibuat. Rincian pembayaran ada di bawah.');
    }
}
