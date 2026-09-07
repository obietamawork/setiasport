<?php

namespace App\Http\Controllers\Publik;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Halaman pesanan pelanggan — tanpa akun, tanpa kata sandi (KEP-008).
 *
 * Tautan bertoken memang dirancang untuk dibagikan ke grup tim, jadi halaman
 * ini TIDAK BOLEH memuat data kontak utuh. Nomor HP dan email disamarkan.
 */
class LacakController extends Controller
{
    public function form()
    {
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

        return view('publik.lacak', [
            'order' => $order,
            'menungguBayar' => $order->payments
                ->firstWhere('status', PaymentStatus::MENUNGGU),
            'gerbangAktif' => app(\App\Services\Pembayaran\GerbangPembayaran::class)->aktif(),
        ]);
    }

    /** Pelanggan mengunggah bukti transfer; kasir yang memverifikasi. */
    public function buktiTransfer(Request $request, string $token): RedirectResponse
    {
        $order = Order::where('access_token', $token)->firstOrFail();

        $maks = (int) setting('upload_maks_mb', 10) * 1024;

        $request->validate([
            'bukti' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', "max:{$maks}"],
        ], [
            'bukti.mimes' => 'Bukti transfer harus berupa foto (JPG/PNG) atau PDF.',
            'bukti.max' => "Ukuran berkas maksimal {$maks} KB.",
        ], ['bukti' => 'bukti transfer']);

        $payment = $order->payments()->where('status', PaymentStatus::MENUNGGU)->first();

        if (! $payment) {
            return back()->withErrors('Tidak ada tagihan yang menunggu pembayaran pada pesanan ini.');
        }

        $payment->update([
            'bukti_path' => $request->file('bukti')->store('bukti', 'public'),
        ]);

        return back()->with('sukses',
            'Bukti transfer terkirim. Kami memeriksanya pada jam kerja dan akan mengabari lewat email.');
    }
}
