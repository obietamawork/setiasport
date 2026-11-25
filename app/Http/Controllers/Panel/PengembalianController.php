<?php

namespace App\Http\Controllers\Panel;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Kelebihan bayar yang dikembalikan ke pelanggan (KEP-158), misalnya setelah
 * potongan pada pesanan yang sudah lunas.
 *
 * Dicatat sebagai baris pembayaran berjenis Pengembalian, sehingga sisa
 * tagihan, laporan, dan hitungan pembatalan memakai uang bersih yang benar-benar
 * dipegang toko. Pengembalian karena pembatalan tetap lewat kotak pembatalan.
 */
class PengembalianController extends Controller
{
    public function store(Request $request, Order $pesanan): RedirectResponse
    {
        $data = $request->validate([
            'jumlah' => ['required', 'integer', 'min:1'],
            'metode' => ['required', Rule::in([PaymentMethod::TUNAI->value, PaymentMethod::TRANSFER->value])],
            'catatan' => ['nullable', 'string', 'max:200'],
        ], [], ['jumlah' => 'nominal', 'metode' => 'cara pengembalian']);

        DB::transaction(function () use ($pesanan, $data) {
            // Dua pencatatan bersamaan tidak boleh sama-sama lolos pemeriksaan.
            $order = Order::whereKey($pesanan->id)->lockForUpdate()->firstOrFail()
                ->load(['items.options', 'items.rosters', 'adjustments', 'payments']);

            // Jalur ini bukan untuk pembatalan: di sana jumlah yang dikembalikan
            // sudah diputuskan menurut tahapnya, termasuk uang muka yang hangus.
            if (in_array($order->status, [OrderStatus::DIBATALKAN, OrderStatus::KADALUARSA], true)) {
                throw ValidationException::withMessages([
                    'jumlah' => 'Pesanan ini dibatalkan. Pengembalian uangnya diatur lewat keputusan pembatalan.',
                ]);
            }

            if ($order->kelebihan_bayar < 1) {
                throw ValidationException::withMessages(['jumlah' => 'Pesanan ini tidak punya kelebihan bayar.']);
            }

            if ($data['jumlah'] > $order->kelebihan_bayar) {
                throw ValidationException::withMessages([
                    'jumlah' => 'Nominal melebihi kelebihan bayar '.rupiah($order->kelebihan_bayar).'.',
                ]);
            }

            $order->payments()->create([
                'jenis' => PaymentType::PENGEMBALIAN,
                'metode' => PaymentMethod::from($data['metode']),
                'status' => PaymentStatus::LUNAS,
                'jumlah' => $data['jumlah'],
                // Titik rawan uang kas: siapa yang mengeluarkan uang selalu tercatat.
                'dicatat_oleh' => auth('web')->id(),
                'diverifikasi_oleh' => auth('web')->id(),
                'dibayar_pada' => now(),
            ]);
        });

        ActivityLog::catat('pengembalian.dicatat', $pesanan, $data);

        return back()->with('sukses', rupiah($data['jumlah']).' dicatat sudah dikembalikan ke pelanggan.');
    }
}
