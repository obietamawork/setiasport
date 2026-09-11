<?php

namespace App\Http\Controllers\Publik;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Halaman pesanan pelanggan, tanpa akun, tanpa kata sandi (KEP-008).
 *
 * Tautan bertoken memang dirancang untuk dibagikan ke grup tim, jadi halaman
 * ini TIDAK BOLEH memuat data kontak utuh. Nomor HP dan email disamarkan.
 */
class LacakController extends Controller
{
    public function form()
    {
        // Pelanggan yang sudah masuk tidak perlu mengetik kode apa pun.
        if (Auth::guard('pelanggan')->check()) {
            return redirect()->route('pesanan-saya');
        }

        return view('publik.lacak-form');
    }

    public function cari(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email'],
        ], [], ['kode' => 'kode pesanan', 'email' => 'email']);

        // Tanpa pembatasan, kombinasi kode + email bisa ditebak dengan mencoba.
        $kunci = 'lacak:'.$request->ip();

        if (RateLimiter::tooManyAttempts($kunci, 10)) {
            throw ValidationException::withMessages([
                'kode' => 'Terlalu banyak percobaan. Coba lagi dalam beberapa menit.',
            ]);
        }

        RateLimiter::hit($kunci, 300);

        $order = Order::where('kode', $data['kode'])
            ->whereHas('customer', fn ($q) => $q->where('email', $data['email']))
            ->first();

        if (! $order) {
            throw ValidationException::withMessages([
                'kode' => 'Kode pesanan dan email tidak cocok. Periksa lagi email konfirmasi Anda.',
            ]);
        }

        RateLimiter::clear($kunci);

        return redirect()->route('lacak.token', $order->access_token);
    }

    public function show(string $token)
    {
        $order = Order::where('access_token', $token)
            ->with([
                'customer', 'items.options', 'items.product', 'items.rosters',
                'items.designAssets', 'items.approvedDesign', 'adjustments',
                'payments', 'statusLogs.orderItem',
            ])
            ->firstOrFail();

        $menunggu = $order->payments->firstWhere('status', PaymentStatus::MENUNGGU);

        /*
         * Tagihan yang sedang harus dibayar pelanggan, kalau ada. DP punya baris
         * pembayaran sejak checkout. Pelunasan (KEP-028) belum: nominalnya
         * dibaca langsung dari sisa tagihan, supaya penyesuaian biaya yang
         * menyusul, ongkir misalnya, ikut terhitung tanpa ada angka yang basi.
         */
        $tagihan = match (true) {
            $menunggu !== null => [
                'jenis' => $menunggu->jenis === PaymentType::PELUNASAN ? 'pelunasan' : 'awal',
                'jumlah' => $menunggu->jumlah,
                'batas' => $order->kadaluarsa_pada,
                'pembayaran' => $menunggu,
            ],
            $order->status === OrderStatus::MENUNGGU_PELUNASAN && $order->sisa_tagihan > 0 => [
                'jenis' => 'pelunasan',
                'jumlah' => $order->sisa_tagihan,
                'batas' => null,
                'pembayaran' => null,
            ],
            default => null,
        };

        return view('publik.lacak', [
            'order' => $order,
            'tagihan' => $tagihan,
        ]);
    }

}
