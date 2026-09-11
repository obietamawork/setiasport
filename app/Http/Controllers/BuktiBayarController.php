<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\Payment;
use App\Services\PengunggahGambar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bukti pembayaran, hybrid dan privat (KEP-029).
 *
 * Pelanggan mengunggahnya sendiri dari halaman pesanan, sehingga bukti otomatis
 * menempel ke tagihan dan nominal yang benar. Admin tetap bisa melampirkan bukti
 * saat verifikasi untuk yang diterima lewat chat CS atau di toko. Tidak ada jalur
 * WhatsApp: klien menetapkan tidak ada transaksi di luar website, karena di
 * WhatsApp-lah penipu menyamar sebagai toko.
 *
 * Tiga hal yang membuat jalur ini aman:
 * - Berkasnya disimpan di disk PRIVAT dan hanya dilayani lewat rute yang
 *   memeriksa hak akses. Isinya nama dan nomor rekening pelanggan.
 * - Gambar di-encode ulang oleh GD: metadata terbuang, dan berkas yang
 *   menyamar sebagai gambar tidak selamat dari proses itu. PDF tidak diterima
 *   karena tidak bisa diperlakukan sama.
 * - Bukti TIDAK PERNAH memverifikasi dirinya sendiri. Ia cuma petunjuk untuk
 *   admin, yang tetap wajib mencocokkan dengan mutasi sebelum menekan Verifikasi.
 */
class BuktiBayarController extends Controller
{
    public function unggah(Request $request, string $token, PengunggahGambar $pengunggah): RedirectResponse
    {
        $order = Order::where('access_token', $token)
            ->with(['items.options', 'payments', 'adjustments'])
            ->firstOrFail();

        $request->validate([
            'bukti' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.(setting('upload_maks_mb', 10) * 1024)],
        ], [
            'bukti.image' => 'Kirim foto atau tangkapan layar bukti transfernya (JPG atau PNG).',
            'bukti.mimes' => 'Kirim foto atau tangkapan layar bukti transfernya (JPG atau PNG).',
        ], ['bukti' => 'bukti transfer']);

        $tagihan = $this->tagihanTerbuka($order);

        if (! $tagihan) {
            return back()->withErrors('Tidak ada tagihan yang sedang menunggu pembayaran di pesanan ini.');
        }

        $baru = $pengunggah->simpan($request->file('bukti'), 'bukti', 'local', denganThumb: false)['path'];
        $lama = $tagihan->bukti_path;

        $tagihan->update(['bukti_path' => $baru]);
        $pengunggah->hapusDari('local', $lama);

        ActivityLog::catat('pembayaran.bukti-pelanggan', $order, [
            'payment_id' => $tagihan->id,
            'mengganti' => (bool) $lama,
        ]);

        return back()->with('sukses',
            'Bukti transfer terkirim. Kami mencocokkannya dengan mutasi rekening pada jam operasional '
            .'('.lcfirst((string) setting('cs_jam_layanan')).'), '
            .'lalu mengabari lewat email.');
    }

    /** Untuk pelanggan: hanya pemegang tautan pesanan yang bisa membukanya. */
    public function lihatPelanggan(string $token, Payment $pembayaran): Response
    {
        $order = Order::where('access_token', $token)->firstOrFail();

        abort_unless($pembayaran->order_id === $order->id, 404);

        return $this->layani($pembayaran);
    }

    /** Untuk staf: rute ini berada di balik izin order.lihat. */
    public function lihatStaf(Order $pesanan, Payment $pembayaran): Response
    {
        abort_unless($pembayaran->order_id === $pesanan->id, 404);

        return $this->layani($pembayaran);
    }

    /**
     * Tagihan yang boleh menerima bukti. DP punya baris sendiri sejak checkout;
     * pelunasan belum, karena nominalnya baru pasti setelah semua penyesuaian
     * biaya tercatat. Barisnya dibuat saat pelanggan menyatakan sudah membayar,
     * dengan sisa tagihan saat itu, bukan disimpan jauh hari lalu jadi basi.
     */
    private function tagihanTerbuka(Order $order): ?Payment
    {
        $menunggu = $order->payments->firstWhere('status', PaymentStatus::MENUNGGU);

        if ($menunggu) {
            return $menunggu;
        }

        if ($order->status === OrderStatus::MENUNGGU_PELUNASAN && $order->sisa_tagihan > 0) {
            return $order->payments()->create([
                'jenis' => PaymentType::PELUNASAN,
                // Metode sebenarnya dipilih admin saat verifikasi.
                'metode' => PaymentMethod::TRANSFER,
                'status' => PaymentStatus::MENUNGGU,
                'jumlah' => $order->sisa_tagihan,
            ]);
        }

        return null;
    }

    private function layani(Payment $pembayaran): Response
    {
        $path = $pembayaran->bukti_path;

        abort_if(! $path, 404);

        // Bukti lama dari sebelum KEP-029 masih ada di disk publik.
        $disk = Storage::disk('local')->exists($path) ? 'local' : 'public';

        abort_unless(Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->response($path, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
