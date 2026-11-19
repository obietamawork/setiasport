<?php

namespace App\Http\Controllers\Publik;

use App\Enums\DesignAssetType;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\AlurDesain;
use App\Services\RosterPemain;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Tindakan pelanggan atas satu baris pesanannya, diakses lewat tautan bertoken
 * tanpa akun (KEP-008).
 */
class PesananItemController extends Controller
{
    /** Tautan file desain per barang (KEP-139). */
    private const BATAS_TAUTAN_DESAIN = 10;

    public function __construct(
        private RosterPemain $roster,
        private AlurDesain $desain,
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
            // Versi mockup yang tampil saat tombolnya ditekan (KEP-098).
            'mockup_versi' => ['required', 'integer', 'min:1'],
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

        $this->desain->setujui(
            $item,
            $request->string('nama')->trim()->toString(),
            $request->ip(),
            $request->integer('mockup_versi'),
        );

        return back()->with('sukses',
            'Desain disetujui dan pesanan masuk antrian produksi. Desain dan daftar pemain sekarang terkunci.');
    }

    /**
     * Catatan desain dari pelanggan (KEP-122). Terbuka sampai desain
     * disetujui: sesudah itu konveksi sudah bekerja dari mockup yang
     * disepakati, dan catatan yang berubah diam-diam tidak akan terbaca lagi.
     */
    public function ubahCatatan(Request $request, string $token, OrderItem $item): RedirectResponse
    {
        $item = $this->item($token, $item);
        $order = Order::where('access_token', $token)->firstOrFail();

        if (! $item->kustom() || $item->sudahDisetujui()) {
            return back()->withErrors(
                'Catatan tidak bisa diubah lagi karena desainnya sudah disetujui. '
                .'Kalau ada yang perlu diubah, hubungi kami lewat '.kanal_kontak().'.'
            );
        }

        $data = $request->validate([
            'catatan' => ['nullable', 'string', 'max:1000'],
        ], [], ['catatan' => 'catatan']);

        $item->update(['catatan' => $data['catatan'] ?? null]);

        ActivityLog::catat('pesanan.catatan_pelanggan', $order, [
            'kode' => $order->kode,
            'barang' => $item->nama_produk,
        ]);

        return back()->with('sukses', 'Catatan disimpan. Desainer membacanya sebelum menyiapkan mockup.');
    }

    /**
     * Tautan Google Drive atau Dropbox untuk file desain (KEP-139), di samping
     * WhatsApp. Yang disimpan hanya tautan https; tidak ada berkas yang masuk
     * ke server. Tautan baru tidak menghapus yang lama: desainer melihat
     * semuanya, yang terbaru di atas.
     */
    public function kirimTautanDesain(Request $request, string $token, OrderItem $item): RedirectResponse
    {
        $item = $this->item($token, $item);

        if (! $item->kustom() || $item->sudahDisetujui()) {
            return back()->withErrors(
                'Desainnya sudah disetujui, jadi file desain tidak bisa diganti lagi. '
                .'Kalau ada yang perlu diubah, hubungi kami lewat '.kanal_kontak().'.'
            );
        }

        $data = $request->validate([
            'tautan_desain' => ['required', 'url:https', 'max:500'],
        ], [
            'tautan_desain.url' => 'Tautan harus diawali https://, misalnya tautan Google Drive.',
        ], ['tautan_desain' => 'tautan file desain']);

        // Tautan pesanan beredar di grup tim: tanpa batas, siapa pun yang
        // memegangnya bisa menambah baris tanpa henti ke basis data hosting.
        $tautanPelanggan = $item->designAssets()->where('tipe', DesignAssetType::UPLOAD_PELANGGAN);

        if ($tautanPelanggan->count() >= self::BATAS_TAUTAN_DESAIN) {
            return back()->withErrors(
                'Sudah '.self::BATAS_TAUTAN_DESAIN.' tautan terkirim untuk barang ini. '
                .'Kalau masih ada file yang perlu dikirim, hubungi kami lewat '.kanal_kontak().'.'
            );
        }

        $item->designAssets()->create([
            'tipe' => DesignAssetType::UPLOAD_PELANGGAN,
            'versi' => (int) $tautanPelanggan->max('versi') + 1,
            'external_url' => $data['tautan_desain'],
            'catatan' => 'Tautan dari halaman pesanan pelanggan.',
        ]);

        ActivityLog::catat('pesanan.tautan_desain', $item->order, [
            'barang' => $item->nama_produk,
        ]);

        return back()->with('sukses', 'Tautan file desain terkirim. Desainer membukanya sebelum menyiapkan mockup.');
    }

    public function mintaRevisi(Request $request, string $token, OrderItem $item): RedirectResponse
    {
        $item = $this->item($token, $item);

        $data = $request->validate([
            'catatan' => ['required', 'string', 'min:5', 'max:500'],
            'mockup_versi' => ['required', 'integer', 'min:1'],
        ], [
            'catatan.required' => 'Tulis bagian mana yang perlu diubah agar desainer tidak menebak.',
            'catatan.min' => 'Tulis lebih rinci supaya revisinya tepat sasaran.',
        ], ['catatan' => 'catatan revisi']);

        $this->desain->mintaRevisi($item, $data['catatan'], (int) $data['mockup_versi']);

        return back()->with('sukses', 'Permintaan revisi terkirim ke desainer.');
    }
}
