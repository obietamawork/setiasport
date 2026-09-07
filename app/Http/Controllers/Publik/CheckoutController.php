<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use App\Services\Checkout;
use App\Services\Keranjang;
use App\Services\PengirimEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
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

        $isi = $this->keranjang->isi();

        return view('publik.checkout', [
            'isi' => $isi,
            'total' => $this->keranjang->total(),
            'adaMasalah' => $this->keranjang->adaMasalah(),
            // DP hanya masuk akal untuk pesanan kustom: barang stok diambil
            // besok, jadi tidak ada gunanya menunda pembayarannya (KEP-005).
            'bolehDp' => $isi->contains(fn ($b) => $b['jalur'] === \App\Enums\FulfillmentType::PREORDER),
            'persenDp' => (int) setting('dp_minimum_persen', 50),
            'pelanggan' => Auth::guard('pelanggan')->user(),
        ]);
    }

    /**
     * Pelanggan yang sudah masuk tidak perlu mengetik ulang datanya.
     * Emailnya dikunci karena itulah yang mengikat pesanan ke akunnya.
     */
    private function isiOtomatis(Request $request): void
    {
        if ($pelanggan = Auth::guard('pelanggan')->user()) {
            $request->merge([
                'email' => $pelanggan->email,
                'nama' => $request->filled('nama') ? $request->input('nama') : $pelanggan->nama,
                'no_hp' => $request->filled('no_hp') ? $request->input('no_hp') : $pelanggan->no_hp,
                'buat_akun' => null,
            ]);
        }
    }

    /**
     * Akun dibuat sebagai pendaftaran TERTUNDA, dan pelanggan tidak langsung
     * dimasukkan (KEP-021). Pesanannya sendiri tetap berjalan normal: tautan
     * bertoken di email pesanan tetap membukanya tanpa perlu akun.
     */
    private function buatkanAkun(\App\Models\Customer $pelanggan, string $password): void
    {
        if ($pelanggan->akunAktif()) {
            return;
        }

        $token = $pelanggan->siapkanKonfirmasi($password);

        $this->email->kirimKonfirmasiAkun($pelanggan, route('daftar.konfirmasi', $token));
    }

    public function proses(Request $request): RedirectResponse
    {
        $this->isiOtomatis($request);

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
            // Longgar dengan sengaja: keranjang berisi barang stok saja tidak
            // menampilkan pilihan ini, dan lunas adalah satu-satunya arti yang
            // masuk akal di sana.
            'tipe_pembayaran' => ['nullable', 'in:LUNAS,DP'],
            'setuju' => ['accepted'],

            // Akun sepenuhnya opsional. Kolomnya hanya diperiksa bila kotaknya
            // dicentang, supaya checkout tamu tidak pernah terhalang (KEP-020).
            'buat_akun' => ['nullable', 'boolean'],
            'password' => ['exclude_unless:buat_akun,1', 'required', 'confirmed', Password::min(8)],
        ], [
            'password.confirmed' => 'Ulangi kata sandi belum sama.',
            'email.required' => 'Email wajib diisi: ke sanalah tautan pesanan dan kabar progres dikirim.',
            'no_hp.regex' => 'Nomor HP hanya boleh berisi angka, spasi, tanda plus, dan strip.',
            'setuju.accepted' => 'Centang persetujuan pengambilan di toko untuk melanjutkan.',
        ], [
            'nama' => 'nama', 'email' => 'email', 'no_hp' => 'nomor HP',
        ]);

        $adaKustom = $this->keranjang->isi()
            ->contains(fn ($b) => $b['jalur'] === \App\Enums\FulfillmentType::PREORDER);

        $tipe = (($data['tipe_pembayaran'] ?? 'LUNAS') === 'DP' && $adaKustom) ? 'DP' : 'LUNAS';

        try {
            $order = $this->checkout->proses($data, $tipe);
        } catch (\RuntimeException $e) {
            // Umumnya stok habis diambil pembeli lain di detik terakhir.
            throw ValidationException::withMessages(['nama' => $e->getMessage()]);
        }

        $this->email->kirim($order, 'order-dibuat');

        $pesan = 'Pesanan Anda dibuat. Rincian pembayaran ada di bawah.';

        if ($request->boolean('buat_akun')) {
            $this->buatkanAkun($order->customer, $request->string('password')->toString());
            $pesan .= ' Kami juga mengirim tautan konfirmasi akun ke email Anda.';
        }

        return redirect()->route('lacak.token', $order->access_token)->with('sukses', $pesan);
    }
}
