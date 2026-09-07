<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\OrderAdjustment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Perubahan pesanan setelah DP masuk (KEP-007).
 *
 * Dicatat sebagai baris tersendiri, bukan dengan mengedit item lama, supaya
 * pelanggan melihat apa yang berubah di halaman pesanannya SEBELUM datang ke
 * toko. Itulah inti dari "tidak ada kejutan angka di kasir".
 */
class PenyesuaianController extends Controller
{
    public function store(Request $request, Order $pesanan): RedirectResponse
    {
        abort_if($pesanan->status->final(), 422, 'Pesanan ini sudah selesai atau dibatalkan.');

        $data = $request->validate([
            'deskripsi' => ['required', 'string', 'max:150'],
            'jumlah' => ['required', 'integer', 'not_in:0', 'min:-99999999', 'max:99999999'],
        ], [
            'deskripsi.required' => 'Tulis deskripsi yang bisa dimengerti pelanggan, misalnya "Tambah 2 pemain".',
            'jumlah.not_in' => 'Nominal tidak boleh nol. Pakai angka negatif untuk potongan harga.',
        ], ['deskripsi' => 'deskripsi', 'jumlah' => 'nominal']);

        $penyesuaian = $pesanan->adjustments()->create($data + ['dibuat_oleh' => auth('web')->id()]);

        ActivityLog::catat('penyesuaian.dibuat', $pesanan, $data);

        return back()->with('sukses', "Penyesuaian \"{$penyesuaian->deskripsi}\" ditambahkan. "
            .'Pelanggan bisa melihatnya di halaman pesanannya.');
    }

    public function destroy(Order $pesanan, OrderAdjustment $penyesuaian): RedirectResponse
    {
        abort_unless($penyesuaian->order_id === $pesanan->id, 404);
        abort_if($pesanan->status->final(), 422, 'Pesanan ini sudah selesai atau dibatalkan.');

        ActivityLog::catat('penyesuaian.dihapus', $pesanan, [
            'deskripsi' => $penyesuaian->deskripsi,
            'jumlah' => $penyesuaian->jumlah,
        ]);

        $penyesuaian->delete();

        return back()->with('sukses', 'Penyesuaian dihapus.');
    }
}
