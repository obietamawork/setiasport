<?php

namespace App\Http\Controllers\Panel;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\Payment;
use App\Services\AlurPesanan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PembayaranController extends Controller
{
    public function __construct(private AlurPesanan $alur) {}

    public function verifikasi(Order $pesanan, Payment $pembayaran): RedirectResponse
    {
        abort_unless($pembayaran->order_id === $pesanan->id, 404);
        abort_if($pembayaran->status === PaymentStatus::LUNAS, 422, 'Pembayaran ini sudah diverifikasi.');

        $this->alur->terimaPembayaran($pembayaran, auth('web')->id());

        ActivityLog::catat('pembayaran.diverifikasi', $pesanan, [
            'payment_id' => $pembayaran->id,
            'jumlah' => $pembayaran->jumlah,
        ]);

        return back()->with('sukses', 'Pembayaran diverifikasi. Pesanan mulai dikerjakan dan pelanggan dikabari.');
    }

    /**
     * Pembayaran yang diterima langsung di toko, tunai, transfer, atau QRIS/EDC.
     * Diperlakukan setara dengan pembayaran gateway (KEP-003); yang membedakan
     * hanya metode dan siapa yang mencatatnya.
     */
    public function catat(Request $request, Order $pesanan): RedirectResponse
    {
        $pesanan->load(['items.options', 'adjustments', 'payments']);

        abort_if($pesanan->status->final(), 422, 'Pesanan ini sudah selesai atau dibatalkan.');

        $data = $request->validate([
            'jumlah' => ['required', 'integer', 'min:1', 'max:'.max(1, $pesanan->sisa_tagihan)],
            'metode' => ['required', Rule::in(['TUNAI', 'TRANSFER', 'QRIS_EDC'])],
            'jenis' => ['required', Rule::in(['DP', 'PELUNASAN', 'FULL'])],
        ], [
            'jumlah.max' => 'Nominal melebihi sisa tagihan '.rupiah($pesanan->sisa_tagihan).'.',
        ], ['jumlah' => 'nominal', 'metode' => 'metode pembayaran']);

        $pembayaran = $pesanan->payments()->create([
            'jenis' => PaymentType::from($data['jenis']),
            'metode' => PaymentMethod::from($data['metode']),
            'status' => PaymentStatus::MENUNGGU,
            'jumlah' => $data['jumlah'],
            // Titik rawan uang kas: siapa yang menerima selalu tercatat.
            'dicatat_oleh' => auth('web')->id(),
            'dibayar_pada' => now(),
        ]);

        $this->alur->terimaPembayaran($pembayaran, auth('web')->id());

        ActivityLog::catat('pembayaran.dicatat', $pesanan, [
            'jumlah' => $data['jumlah'],
            'metode' => $data['metode'],
        ]);

        return back()->with('sukses', rupiah($data['jumlah']).' dicatat sebagai pembayaran.');
    }

    public function tolak(Request $request, Order $pesanan, Payment $pembayaran): RedirectResponse
    {
        abort_unless($pembayaran->order_id === $pesanan->id, 404);

        $data = $request->validate([
            'alasan' => ['required', 'string', 'max:150'],
        ], [
            'alasan.required' => 'Tulis alasannya agar bisa dijelaskan ke pelanggan.',
        ], ['alasan' => 'alasan']);

        $pembayaran->update([
            'status' => PaymentStatus::GAGAL,
            'diverifikasi_oleh' => auth('web')->id(),
        ]);

        ActivityLog::catat('pembayaran.ditolak', $pesanan, $data);

        return back()->with('sukses', 'Pembayaran ditandai gagal. Hubungi pelanggan untuk menjelaskannya.');
    }
}
