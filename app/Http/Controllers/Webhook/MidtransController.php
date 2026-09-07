<?php

namespace App\Http\Controllers\Webhook;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Payment;
use App\Services\AlurPesanan;
use App\Services\Pembayaran\GerbangPembayaran;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Notifikasi pembayaran dari Midtrans.
 *
 * Datang dari internet terbuka tanpa sesi, jadi keabsahannya sepenuhnya
 * bergantung pada tanda tangan yang diperiksa di dalam driver. Endpoint ini
 * dikecualikan dari CSRF karena pengirimnya bukan browser pelanggan.
 */
class MidtransController extends Controller
{
    public function __construct(
        private GerbangPembayaran $gerbang,
        private AlurPesanan $alur,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $hasil = $this->gerbang->bacaNotifikasi($request->all());

        if (! $hasil) {
            // Sengaja tidak menjelaskan apa yang salah kepada pemanggil.
            return response()->json(['pesan' => 'Notifikasi tidak sah.'], 403);
        }

        $payment = Payment::with('order')->where('provider_ref', $hasil['ref'])->first();

        if (! $payment) {
            return response()->json(['pesan' => 'Pembayaran tidak ditemukan.'], 404);
        }

        // Midtrans mengirim ulang notifikasi yang sama sampai dibalas 200.
        // Tanpa penjagaan ini, satu pembayaran bisa diproses berkali-kali.
        if ($payment->status === PaymentStatus::LUNAS) {
            return response()->json(['pesan' => 'Sudah diproses.']);
        }

        $payment->update([
            'payload_json' => array_merge($payment->payload_json ?? [], [
                'notifikasi_terakhir' => $request->all(),
            ]),
        ]);

        if ($hasil['status'] === PaymentStatus::LUNAS) {
            $this->alur->terimaPembayaran($payment);
        } else {
            $payment->update(['status' => $hasil['status']]);
        }

        ActivityLog::catat('pembayaran.notifikasi', $payment->order, [
            'ref' => $hasil['ref'],
            'status' => $hasil['status']->value,
        ]);

        return response()->json(['pesan' => 'Diterima.']);
    }
}
