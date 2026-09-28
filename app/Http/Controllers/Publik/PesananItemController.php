<?php

namespace App\Http\Controllers\Publik;

use App\Enums\DesignAssetType;
use App\Enums\OrderItemStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\AlurDesain;
use App\Services\PengunggahGambar;
use App\Services\RosterPemain;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Tindakan pelanggan atas satu baris pesanannya, diakses lewat tautan bertoken
 * tanpa akun (KEP-008).
 */
class PesananItemController extends Controller
{
    public function __construct(
        private RosterPemain $roster,
        private AlurDesain $desain,
        private PengunggahGambar $pengunggah,
    ) {}

    private function item(string $token, OrderItem $item): OrderItem
    {
        $order = Order::where('access_token', $token)->firstOrFail();

        abort_unless($item->order_id === $order->id, 404);

        return $item->load(['product', 'order', 'designAssets']);
    }

    public function simpanRoster(Request $request, string $token, OrderItem $item): RedirectResponse
    {
        $item = $this->item($token, $item);

        $this->roster->simpan($item, $request->input('roster', []));

        return back()->with('sukses',
            'Daftar pemain disimpan. Masih bisa diubah sampai Anda menyetujui desain.');
    }

    /**
     * Gerbang persetujuan (docs/02). Nama diketik sendiri dan dicatat bersama
     * versi mockup, tautan halaman ini memang beredar di grup tim, jadi
     * perlindungan diletakkan tepat di titik yang berkonsekuensi, bukan di
     * pintu masuk (KEP-008).
     */
    public function setujuiDesain(Request $request, string $token, OrderItem $item): RedirectResponse
    {
        $item = $this->item($token, $item);

        $request->validate([
            'nama' => ['required', 'string', 'min:3', 'max:100'],
            'paham' => ['accepted'],
        ], [
            'nama.required' => 'Ketik nama Anda sebagai tanda persetujuan.',
            'nama.min' => 'Tulis nama lengkap Anda, bukan singkatan.',
            'paham.accepted' => 'Centang pernyataan bahwa desain tidak dapat diubah setelah disetujui.',
        ], ['nama' => 'nama']);

        if ($item->product->roster_wajib && ! $this->roster->lengkap($item)) {
            return back()->withErrors(
                'Daftar pemain belum lengkap: masih kurang dari '.$item->qty.' baris. '
                .'Lengkapi dulu sebelum menyetujui desain, karena setelah disetujui daftarnya terkunci.'
            );
        }

        $this->desain->setujui($item, $request->string('nama')->trim()->toString(), $request->ip());

        return back()->with('sukses',
            'Desain disetujui dan pesanan masuk antrian produksi. Desain dan daftar pemain sekarang terkunci.');
    }

    public function mintaRevisi(Request $request, string $token, OrderItem $item): RedirectResponse
    {
        $item = $this->item($token, $item);

        $data = $request->validate([
            'catatan' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'catatan.required' => 'Tulis bagian mana yang perlu diubah agar desainer tidak menebak.',
            'catatan.min' => 'Tulis lebih rinci supaya revisinya tepat sasaran.',
        ], ['catatan' => 'catatan revisi']);

        $this->desain->mintaRevisi($item, $data['catatan']);

        return back()->with('sukses', 'Permintaan revisi terkirim ke desainer.');
    }

    /**
     * Pelanggan mengirim desainnya sendiri: hanya preview yang masuk sistem (KEP-002).
     *
     * Diperlakukan sama seperti bukti transfer (KEP-029), karena bahayanya sama persis:
     * berkas dari orang tak dikenal, lewat tautan yang bisa beredar di luar kendali
     * toko, tanpa login. Tiga penjagaan yang sama dipasang di sini:
     * 1. Hanya gambar, dan gambarnya di-ENCODE ULANG oleh GD (lihat PengunggahGambar).
     *    Metadata terbuang, dan berkas yang menyamar sebagai gambar tidak selamat dari
     *    proses itu. PDF ditolak karena tidak bisa diperlakukan sama.
     * 2. Dibatasi enam unggahan per sepuluh menit per alamat IP (lihat routes/web.php).
     * 3. File mentah siap cetak (AI/PSD/CDR) tidak pernah diunggah ke sini sama sekali;
     *    pelanggan menempelkan tautan Google Drive-nya, dan sistem hanya menyimpan
     *    tautan itu (KEP-002, KEP-064).
     */
    public function unggahDesain(Request $request, string $token, OrderItem $item): RedirectResponse
    {
        $item = $this->item($token, $item);

        abort_if($item->sudahDisetujui(), 422, 'Desain sudah disetujui dan terkunci.');

        $maks = (int) setting('upload_maks_mb', 10) * 1024;

        $request->validate([
            'berkas' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', "max:{$maks}"],
            'tautan' => ['nullable', 'url', 'max:500'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ], [
            'berkas.image' => 'Kirim gambar preview-nya (JPG, PNG, atau WebP), bukan berkas lain.',
            'berkas.mimes' => 'Kirim gambar preview-nya (JPG, PNG, atau WebP). File mentah siap cetak '
                .'seperti AI, PSD, CDR, atau PDF terlalu besar untuk sistem ini: bagikan lewat tautan '
                .'Google Drive di bawah.',
            'berkas.max' => "Ukuran gambar maksimal {$maks} KB.",
            'tautan.url' => 'Tautan harus lengkap, diawali https://',
        ], ['berkas' => 'berkas desain', 'tautan' => 'tautan Google Drive']);

        if (! $request->hasFile('berkas') && ! $request->filled('tautan')) {
            return back()->withErrors('Unggah gambar preview atau tempelkan tautan Google Drive-nya.');
        }

        $path = null;

        if ($request->hasFile('berkas')) {
            try {
                // Tanpa thumbnail: ini preview kerja desainer, bukan kartu katalog
                // yang butuh dua ukuran.
                $path = $this->pengunggah->simpan($request->file('berkas'), 'desain', 'public', denganThumb: false)['path'];
            } catch (RuntimeException $e) {
                return back()->withErrors($e->getMessage());
            }
        }

        $versi = (int) $item->designAssets()
            ->where('tipe', DesignAssetType::UPLOAD_PELANGGAN->value)
            ->max('versi') + 1;

        $item->designAssets()->create([
            'tipe' => DesignAssetType::UPLOAD_PELANGGAN,
            'versi' => $versi,
            'path' => $path,
            'external_url' => $request->input('tautan'),
            'catatan' => $request->input('catatan'),
        ]);

        return back()->with('sukses', 'Desain Anda terkirim. Desainer kami akan menindaklanjutinya.');
    }
}
