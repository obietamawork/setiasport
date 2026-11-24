<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\OrderAdjustment;
use App\Services\AlurPesanan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Perubahan pesanan setelah DP masuk (KEP-007).
 *
 * Dicatat sebagai baris tersendiri, bukan dengan mengedit item lama, supaya
 * pelanggan melihat apa yang berubah di halaman pesanannya SEBELUM datang ke
 * toko. Itulah inti dari "tidak ada kejutan angka di kasir".
 */
class PenyesuaianController extends Controller
{
    public function __construct(private AlurPesanan $alur) {}

    /**
     * Pilihan alasan dari gambar karyawan (KEP-124). Daftar tetap supaya
     * laporan dan halaman pelanggan memakai kata yang sama; yang tidak
     * masuk daftar ditulis sendiri lewat "Lainnya".
     */
    public const ALASAN = ['Perubahan spesifikasi', 'Tambahan desain', 'Diskon khusus', 'Lainnya'];

    public function store(Request $request, Order $pesanan): RedirectResponse
    {
        abort_if($pesanan->status->final(), 422, 'Pesanan ini sudah selesai atau dibatalkan.');

        $isian = $request->validate([
            'jenis' => ['required', Rule::in(['tambah', 'potong'])],
            'alasan' => ['required', Rule::in(self::ALASAN)],
            'keterangan' => ['nullable', 'string', 'max:120', 'required_if:alasan,Lainnya'],
            'nominal' => ['required', 'integer', 'min:1', 'max:99999999'],
        ], [
            'keterangan.required_if' => 'Tulis keterangannya: pelanggan membaca kalimat ini di halaman pesanannya.',
            'nominal.min' => 'Nominal minimal Rp1. Pilih "Potongan harga" untuk mengurangi tagihan.',
        ], ['jenis' => 'jenis penyesuaian', 'alasan' => 'alasan', 'keterangan' => 'keterangan', 'nominal' => 'nominal']);

        $keterangan = trim((string) ($isian['keterangan'] ?? ''));
        $potong = $isian['jenis'] === 'potong' || $isian['alasan'] === 'Diskon khusus';

        // Paket yang sudah berangkat tidak bisa ditagih lagi: barangnya sudah
        // di tangan pelanggan dan tidak ada yang bisa menahannya (KEP-158).
        if (! $potong && $pesanan->dikirim_pada !== null) {
            throw ValidationException::withMessages([
                'jenis' => 'Paketnya sudah berangkat, jadi tagihan tidak bisa ditambah lagi. Potongan tetap bisa.',
            ]);
        }

        // Salah ketik satu nol tidak boleh membuat total pesanan minus (KEP-158).
        if ($potong) {
            $total = $pesanan->load(['items.options', 'items.rosters', 'payments', 'adjustments'])->total;

            if ($isian['nominal'] > $total) {
                throw ValidationException::withMessages([
                    'nominal' => 'Potongan melebihi total pesanan '.rupiah($total).'.',
                ]);
            }
        }

        $data = [
            'deskripsi' => match (true) {
                $isian['alasan'] === 'Lainnya' => $keterangan,
                $keterangan !== '' => $isian['alasan'].': '.$keterangan,
                default => $isian['alasan'],
            },
            'jumlah' => $potong ? -$isian['nominal'] : $isian['nominal'],
        ];

        $penyesuaian = $pesanan->adjustments()->create($data + ['dibuat_oleh' => auth('web')->id()]);

        // Tagihan yang menunggu ikut menyesuaikan, dan potongan yang membuat
        // sisa tagihan nol membuka gerbang pelunasan (KEP-067 lanjutan, KEP-098).
        $this->alur->totalBerubah($pesanan, auth('web')->id());

        ActivityLog::catat('penyesuaian.dibuat', $pesanan, $data);

        return back()->with('sukses', "Penyesuaian \"{$penyesuaian->deskripsi}\" ditambahkan. "
            .'Pelanggan bisa melihatnya di halaman pesanannya.');
    }

    public function destroy(Order $pesanan, OrderAdjustment $penyesuaian): RedirectResponse
    {
        abort_unless($penyesuaian->order_id === $pesanan->id, 404);
        abort_if($pesanan->status->final(), 422, 'Pesanan ini sudah selesai atau dibatalkan.');

        // Menghapus potongan menaikkan tagihan, dan itu tertutup begitu paket
        // berangkat (KEP-158).
        if ($penyesuaian->jumlah < 0 && $pesanan->dikirim_pada !== null) {
            throw ValidationException::withMessages([
                'penyesuaian' => 'Paketnya sudah berangkat. Menghapus potongan ini akan menaikkan tagihan, jadi tidak bisa lagi.',
            ]);
        }

        ActivityLog::catat('penyesuaian.dihapus', $pesanan, [
            'deskripsi' => $penyesuaian->deskripsi,
            'jumlah' => $penyesuaian->jumlah,
        ]);

        $penyesuaian->delete();

        $this->alur->totalBerubah($pesanan, auth('web')->id());

        return back()->with('sukses', 'Penyesuaian dihapus.');
    }
}
