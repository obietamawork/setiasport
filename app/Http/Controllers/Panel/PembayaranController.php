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
use App\Services\PengunggahGambar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PembayaranController extends Controller
{
    public function __construct(private AlurPesanan $alur) {}

    /**
     * Verifikasi manual (KEP-023). Tidak ada gerbang yang mengabari kami; admin
     * membuka mutasi rekening atau riwayat QRIS, mencocokkannya dengan bukti
     * yang diunggah pelanggan di halaman pesanannya, lalu menekan tombol ini.
     *
     * Metode dan bukti diisi di sini, bukan saat checkout, karena saat checkout
     * belum ada yang tahu pelanggan akan bayar lewat apa. Buktinya disimpan agar
     * ada yang bisa ditunjukkan kalau kelak ada selisih setoran.
     */
    public function verifikasi(Request $request, Order $pesanan, Payment $pembayaran, PengunggahGambar $pengunggah): RedirectResponse
    {
        abort_unless($pembayaran->order_id === $pesanan->id, 404);
        abort_unless($pembayaran->status === PaymentStatus::MENUNGGU, 422,
            'Tagihan ini sudah tidak menunggu pembayaran. Muat ulang halaman pesanannya.');

        $pesanan->load(['items.options', 'items.rosters', 'payments', 'adjustments']);

        $data = $request->validate([
            'metode' => ['required', Rule::in(['TUNAI', 'TRANSFER', 'QRIS'])],
            /*
             * Nominal yang benar-benar terlihat di mutasi (KEP-098). Pelanggan
             * kadang mentransfer kurang, atau tagihannya berubah setelah bukti
             * diunggah, misalnya karena potongan reseller. Dulu satu-satunya
             * pilihan admin adalah menolak, atau verifikasi yang gagal terus
             * karena nominal tagihan melebihi sisa tagihan.
             */
            'jumlah' => ['nullable', 'integer', 'min:1'],
            'bukti' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.(setting('upload_maks_mb', 10) * 1024)],
            /*
             * Bukti bukanlah verifikasi (KEP-029). Tangkapan layar transfer
             * bisa diedit dalam semenit; satu-satunya yang membuktikan uang
             * masuk adalah mutasi rekening atau riwayat QRIS toko sendiri.
             * Centang ini memaksa langkah itu dilakukan, dan mencatat siapa
             * yang menyatakannya.
             */
            'cek_mutasi' => ['accepted'],
        ], [
            'cek_mutasi.accepted' => 'Pastikan dulu uangnya benar-benar masuk di mutasi rekening atau riwayat QRIS, lalu centang pernyataannya.',
        ], ['metode' => 'metode pembayaran', 'bukti' => 'bukti pembayaran', 'jumlah' => 'nominal yang masuk']);

        $jumlah = (int) ($data['jumlah'] ?? $pembayaran->jumlah);

        /*
         * Pemeriksaan cepat dulu, SEBELUM menyentuh berkas atau basis data
         * sama sekali. Pemeriksaan yang benar-benar menentukan -- aman dari
         * dua verifikasi hampir bersamaan lewat baris pesanan terkunci --
         * tetap di AlurPesanan::terimaPembayaran().
         */
        if ($jumlah > $pesanan->sisa_tagihan) {
            throw ValidationException::withMessages([
                'jumlah' => 'Nominal '.rupiah($jumlah).' melebihi sisa tagihan '.rupiah($pesanan->sisa_tagihan).'. '
                    .'Catat sebesar sisa tagihan, lalu kembalikan kelebihannya ke pelanggan.',
            ]);
        }

        $buktiLama = $pembayaran->bukti_path;
        $bukti = $buktiLama;

        if ($request->hasFile('bukti')) {
            $bukti = $pengunggah->simpan($request->file('bukti'), 'bukti', 'local', denganThumb: false)['path'];
        }

        // metode dan bukti_path lewat $tambahan, BUKAN $pembayaran->update()
        // terpisah di sini: kalau pemeriksaan sisa tagihan di dalam menolak
        // (race condition jarang yang lolos dari pemeriksaan cepat di atas),
        // perubahan field ini harus ikut batal, bukan tersimpan duluan
        // padahal verifikasinya sendiri gagal.
        $this->alur->terimaPembayaran($pembayaran, auth('web')->id(), [
            'metode' => PaymentMethod::from($data['metode']),
            'bukti_path' => $bukti,
            'jumlah' => $jumlah,
        ]);

        // Bukti lama dibuang HANYA setelah seluruhnya berhasil tersimpan.
        if ($bukti !== $buktiLama) {
            $pengunggah->hapusDari('local', $buktiLama);
        }

        ActivityLog::catat('pembayaran.diverifikasi', $pesanan, [
            'payment_id' => $pembayaran->id,
            'jumlah' => $jumlah,
            'metode' => $data['metode'],
            'ada_bukti' => (bool) $bukti,
            'mutasi_dicek' => true,
        ]);

        return back()->with('sukses', 'Pembayaran diverifikasi. Pesanan mulai dikerjakan dan pelanggan dikabari.');
    }

    /**
     * Pembayaran yang diterima langsung di toko, tunai maupun QRIS di kasir.
     * Berbeda dari verifikasi di atas: di sini tagihannya belum ada, admin yang
     * membuatnya sekaligus menyatakan uangnya sudah diterima.
     */
    public function catat(Request $request, Order $pesanan): RedirectResponse
    {
        $pesanan->load(['items.options', 'items.rosters', 'adjustments', 'payments']);

        abort_if($pesanan->status->final(), 422, 'Pesanan ini sudah selesai atau dibatalkan.');

        $data = $request->validate([
            'jumlah' => ['required', 'integer', 'min:1', 'max:'.max(1, $pesanan->sisa_tagihan)],
            'metode' => ['required', Rule::in(['TUNAI', 'TRANSFER', 'QRIS'])],
            'jenis' => ['required', Rule::in(['DP', 'PELUNASAN', 'FULL'])],
        ], [
            'jumlah.max' => 'Nominal melebihi sisa tagihan '.rupiah($pesanan->sisa_tagihan).'.',
        ], ['jumlah' => 'nominal', 'metode' => 'metode pembayaran']);

        /*
         * Dibuat dan diverifikasi dalam SATU transaksi: terimaPembayaran()
         * membuka transaksinya sendiri (nested, lewat savepoint), jadi kalau
         * pemeriksaan sisa tagihan di dalamnya menolak -- race condition
         * langka, dua pencatatan kasir untuk pesanan yang sama hampir
         * bersamaan -- baris pembayaran yang baru dibuat ini ikut batal,
         * bukan tertinggal sebagai baris Menunggu yang yatim.
         */
        DB::transaction(function () use ($pesanan, $data) {
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
        });

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

        $this->alur->tolakPembayaran($pembayaran, $data['alasan'], auth('web')->id());

        ActivityLog::catat('pembayaran.ditolak', $pesanan, $data);

        return back()->with('sukses', 'Pembayaran ditandai gagal. Pelanggan sudah dikabari lewat email '
            .'beserta alasannya, dan bisa mengirim ulang bukti dari halaman pesanannya.');
    }
}
