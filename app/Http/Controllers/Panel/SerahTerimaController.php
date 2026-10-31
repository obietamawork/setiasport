<?php

namespace App\Http\Controllers\Panel;

use App\Enums\MetodeAmbil;
use App\Enums\OrderStatus;
use App\Enums\StatusPembatalan;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Services\AlurPesanan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Layar kasir. Pelunasan dan pengambilan adalah SATU tindakan (KEP-006):
 * barang diproduksi dengan modal DP, sisanya dibayar saat diserahkan.
 */
class SerahTerimaController extends Controller
{
    public function __construct(private AlurPesanan $alur) {}

    public function form(Request $request)
    {
        $order = null;
        $kode = $request->string('kode')->trim()->toString();

        if ($kode !== '') {
            $order = Order::with(['customer', 'items.options', 'items.rosters', 'adjustments', 'payments'])
                ->where('kode_ambil', strtoupper($kode))
                ->orWhere('kode', strtoupper($kode))
                ->first();
        }

        return view('panel.serah-terima', [
            'order' => $order,
            'kode' => $kode,
            'tidakKetemu' => $kode !== '' && ! $order,
            'siapHariIni' => Order::with('customer')
                ->where('status', OrderStatus::SIAP_DIAMBIL)
                ->where('metode_ambil', '!=', MetodeAmbil::KIRIM->value)
                ->orderBy('siap_pada')
                ->limit(15)
                ->get(),
        ]);
    }

    public function proses(Request $request, Order $pesanan): RedirectResponse
    {
        $pesanan->load(['items.options', 'items.rosters', 'adjustments', 'payments']);

        $data = $request->validate([
            'diambil_oleh' => ['required', 'string', 'max:100'],
        ], [
            'diambil_oleh.required' => 'Tulis nama orang yang mengambil: termasuk bila diwakilkan.',
        ], ['diambil_oleh' => 'nama pengambil']);

        if ($pesanan->dikirim()) {
            return back()->withErrors(
                'Pesanan ini disetel untuk dikirim, bukan diambil. Catat jasa kirim dan '
                .'nomor resinya di halaman pesanan.'
            );
        }

        // Pengajuan pembatalan diputuskan dulu (KEP-098). Barang yang sudah
        // keluar dari toko membuat pengajuannya tidak mungkin dikabulkan lagi.
        if ($pesanan->pembatalans()->where('status', StatusPembatalan::DIAJUKAN->value)->exists()) {
            return back()->withErrors('Pelanggan mengajukan pembatalan pesanan ini. Putuskan dulu lewat '
                .'kotak Pengajuan pembatalan di halaman pesanan, baru barangnya diserahkan.');
        }

        if ($pesanan->sisa_tagihan > 0) {
            return back()->withErrors(
                'Masih ada sisa tagihan '.rupiah($pesanan->sisa_tagihan).'. '
                .'Catat pelunasannya dulu, baru serahkan barangnya.'
            );
        }

        $this->alur->serahkan($pesanan, $data['diambil_oleh'], auth('web')->id());

        ActivityLog::catat('pesanan.diserahkan', $pesanan, $data);

        return redirect()->route('panel.serah-terima')
            ->with('sukses', "Pesanan {$pesanan->kode} diserahkan kepada {$data['diambil_oleh']}.");
    }
}
