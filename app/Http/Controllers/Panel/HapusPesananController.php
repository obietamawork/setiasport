<?php

namespace App\Http\Controllers\Panel;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\DesignAsset;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Hapus riwayat pesanan (KEP-119), permintaan klien untuk merapikan daftar.
 *
 * Yang boleh dihapus hanya pesanan yang sudah mati (kedaluwarsa atau
 * dibatalkan) dan tidak pernah membawa uang: menghapus pesanan ikut
 * menghapus pembayarannya, dan laporan penjualan yang uangnya hilang
 * diam-diam lebih buruk daripada daftar pesanan yang panjang.
 *
 * Sebelum hilang, jejaknya ditulis ke log aktivitas, supaya pertanyaan
 * "pesanan SS-... ke mana?" masih bisa dijawab.
 */
class HapusPesananController extends Controller
{
    public function satu(Order $pesanan): RedirectResponse
    {
        if (! $pesanan->bolehDihapus()) {
            return back()->withErrors($this->alasanDitolak($pesanan));
        }

        $kode = $pesanan->kode;
        $this->hapus($pesanan);

        return redirect()->route('panel.pesanan.index')->with('sukses', "Pesanan {$kode} dihapus.");
    }

    public function massal(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'pesanan' => ['required', 'array', 'max:100'],
            'pesanan.*' => ['integer', 'distinct', Rule::exists('orders', 'id')],
        ], [
            'pesanan.required' => 'Centang dulu pesanan yang mau dihapus.',
            'pesanan.*.exists' => 'Salah satu pesanan yang dicentang sudah tidak ada. Muat ulang halamannya.',
        ], ['pesanan' => 'pesanan']);

        // Diperiksa satu per satu: daftar id bisa saja dikirim tanpa lewat
        // tampilan yang hanya memberi kotak centang pada pesanan yang boleh.
        $daftar = Order::with('payments')->whereIn('id', $data['pesanan'])->get();
        [$boleh, $dilewati] = $daftar->partition(fn (Order $o) => $o->bolehDihapus());

        $boleh->each(fn (Order $o) => $this->hapus($o));

        $pesan = $boleh->count().' pesanan dihapus.';

        if ($dilewati->isNotEmpty()) {
            $pesan .= ' '.$dilewati->count().' dilewati karena masih berjalan atau sudah ada uang masuk: '
                .$dilewati->pluck('kode')->implode(', ').'.';
        }

        return back()->with('sukses', $pesan);
    }

    private function hapus(Order $pesanan): void
    {
        $pesanan->loadMissing(['customer', 'payments', 'items.options', 'items.rosters', 'adjustments']);

        /** @var OrderStatus $status */
        $status = $pesanan->status;

        $berkas = $pesanan->payments->pluck('bukti_path')
            ->merge(DesignAsset::whereIn('order_item_id', $pesanan->items->modelKeys())->pluck('path'))
            ->filter()
            ->unique();

        ActivityLog::catat('pesanan.dihapus', null, [
            'kode' => $pesanan->kode,
            'pelanggan' => $pesanan->customer->nama,
            'status' => $status->value,
            'total' => $pesanan->total,
        ]);

        DB::transaction(fn () => $pesanan->delete());

        // Berkas dihapus setelah datanya hilang: kalau penghapusan data gagal,
        // bukti bayar dan mockupnya tidak ikut lenyap.
        foreach ($berkas as $path) {
            foreach (['local', 'public'] as $disk) {
                Storage::disk($disk)->delete($path);
            }
        }
    }

    private function alasanDitolak(Order $pesanan): string
    {
        /** @var OrderStatus $status */
        $status = $pesanan->status;

        return in_array($status, [OrderStatus::KADALUARSA, OrderStatus::DIBATALKAN], true)
            ? "Pesanan {$pesanan->kode} pernah menerima pembayaran, jadi tidak bisa dihapus: uangnya akan hilang dari laporan penjualan."
            : "Pesanan {$pesanan->kode} masih {$status->label()}. Yang bisa dihapus hanya pesanan kedaluwarsa atau dibatalkan yang belum pernah dibayar.";
    }
}
