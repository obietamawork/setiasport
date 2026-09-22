<?php

namespace App\Http\Controllers\Panel;

use App\Enums\MetodeAmbil;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Services\AlurPesanan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Pengiriman terbatas untuk barang stok toko (KEP-026).
 *
 * Semuanya dikerjakan dari halaman pesanan, bukan dari layar kasir, karena
 * kesepakatan mengirim terjadi lewat WhatsApp jauh sebelum barangnya siap, bukan
 * di depan meja kasir. Yang dicatat di sini hanya apa yang benar-benar diketahui
 * orang di toko: alamat yang disepakati, nama jasa kirim, dan nomor resi.
 *
 * Ongkirnya kini punya kolom sendiri (KEP-058), bukan lagi baris penyesuaian
 * biaya: pelanggan yang memilih dikirim di checkout sudah melihat angkanya
 * sebelum memesan, dan penyesuaian biaya harus tetap berarti "ada yang berubah
 * setelah disepakati". Selama penyedia ongkir belum dinyalakan, angkanya
 * diketik di sini setelah paket ditimbang di konter.
 */
class PengirimanController extends Controller
{
    public function __construct(private AlurPesanan $alur) {}

    /** Menyetel pesanan ini akan diambil atau dikirim, berikut alamatnya. */
    public function atur(Request $request, Order $pesanan): RedirectResponse
    {
        $pesanan->load('items');

        abort_if($pesanan->status->final(), 422, 'Pesanan ini sudah selesai atau dibatalkan.');
        abort_if($pesanan->dikirim_pada !== null, 422, 'Paketnya sudah berangkat, penyetelan tidak bisa diubah lagi.');

        $data = $request->validate([
            'metode_ambil' => ['required', Rule::enum(MetodeAmbil::class)],
            'alamat_kirim' => ['nullable', 'string', 'max:500'],
            'ongkir' => ['nullable', 'integer', 'min:0', 'max:10000000'],
        ], [], [
            'metode_ambil' => 'cara pengambilan',
            'alamat_kirim' => 'alamat pengiriman',
            'ongkir' => 'ongkos kirim',
        ]);

        $metode = MetodeAmbil::from($data['metode_ambil']);

        /*
         * Barang kustom dulu wajib diambil di toko supaya ukuran yang meleset
         * bisa diperbaiki saat itu juga (KEP-026). Klien mencabutnya: banyak
         * pemesan datang dari luar kota, dan menolak mengirim berarti menolak
         * pesanannya (KEP-055). Risiko ukuran meleset ditanggung dengan panduan
         * ukuran yang kini tampil di halaman pemesanan (KEP-049).
         */

        if ($metode === MetodeAmbil::KIRIM && trim((string) ($data['alamat_kirim'] ?? '')) === '') {
            return back()->withErrors('Tulis alamat lengkapnya. Tanpa itu paket tidak bisa dibuatkan resi.');
        }

        $ongkir = (int) ($data['ongkir'] ?? 0);

        $pesanan->update([
            'metode_ambil' => $metode,
            'alamat_kirim' => $metode === MetodeAmbil::KIRIM ? $data['alamat_kirim'] : null,
            // Pesanan yang batal dikirim tidak boleh menyisakan ongkir di
            // tagihannya. Dikosongkan di sini, bukan dipercayakan kepada orang
            // yang menyetelnya, karena yang tertinggal adalah uang pelanggan.
            'ongkir' => $metode === MetodeAmbil::KIRIM ? $ongkir : 0,
        ]);

        ActivityLog::catat('pesanan.pengiriman-disetel', $pesanan, [
            'metode' => $metode->value,
            'ongkir' => $metode === MetodeAmbil::KIRIM ? $ongkir : 0,
        ]);

        return back()->with('sukses', $metode === MetodeAmbil::KIRIM
            ? 'Pesanan ini akan dikirim'.($ongkir > 0 ? ', ongkir '.rupiah($ongkir).' masuk tagihan.' : '. Ongkirnya belum diisi.')
            : 'Pesanan ini kembali ke pengambilan di toko.');
    }

    /** Paket sudah diserahkan ke kurir dan resinya sudah di tangan. */
    public function kirim(Request $request, Order $pesanan): RedirectResponse
    {
        $pesanan->load(['items.options', 'payments', 'adjustments']);

        $data = $request->validate([
            'jasa_kirim' => ['required', 'string', 'max:50'],
            'no_resi' => ['required', 'string', 'max:60'],
        ], [
            'no_resi.required' => 'Nomor resi wajib diisi: tanpa itu pelanggan tidak bisa melacak apa pun.',
        ], ['jasa_kirim' => 'jasa kirim', 'no_resi' => 'nomor resi']);

        if (! $pesanan->bolehDikirim()) {
            return back()->withErrors($pesanan->alasanBelumBisaDikirim() ?? 'Pesanan ini belum bisa dikirim.');
        }

        $this->alur->kirimkan($pesanan, $data['jasa_kirim'], $data['no_resi'], auth('web')->id());

        ActivityLog::catat('pesanan.dikirim', $pesanan, $data);

        return back()->with('sukses',
            'Resi dicatat dan pelanggan dikabari lewat email. Pesanan tetap di daftar aktif sampai paketnya sampai.');
    }

    public function diterima(Order $pesanan): RedirectResponse
    {
        $pesanan->load(['items.options', 'payments', 'adjustments']);

        $this->alur->tandaiDiterima($pesanan, auth('web')->id());

        ActivityLog::catat('pesanan.diterima', $pesanan, ['no_resi' => $pesanan->no_resi]);

        return back()->with('sukses', 'Pesanan ditutup. Paketnya tercatat sudah sampai.');
    }
}
