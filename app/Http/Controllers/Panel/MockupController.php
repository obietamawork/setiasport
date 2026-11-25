<?php

namespace App\Http\Controllers\Panel;

use App\Enums\OrderItemStatus;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\DesignAsset;
use App\Models\OrderItem;
use App\Services\AlurDesain;
use App\Services\PengunggahGambar;
use App\Services\RosterPemain;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class MockupController extends Controller
{
    private const PESAN_GAMBAR_WAJIB = 'Unggah gambar mockup: pelanggan menyetujui desain dari gambar ini, '
        .'dan gambar ini yang tercetak di lembar kerja. Tautan file siap cetak boleh ditambahkan.';

    public function __construct(
        private AlurDesain $desain,
        private RosterPemain $roster,
        private PengunggahGambar $pengunggah,
    ) {}

    public function store(Request $request, OrderItem $item): RedirectResponse
    {
        abort_unless(auth('web')->user()->punyaIzin('produksi.desain'), 403);
        /*
         * Versi baru juga boleh dikirim selama pelanggan belum menyetujui
         * (KEP-098): desainer menemukan salah ketik nama, atau toko setuju
         * memberi satu revisi lagi setelah jatah revisi pelanggan habis. Dulu
         * keduanya buntu, karena hanya pelanggan yang bisa mengembalikan
         * desain ke meja desainer.
         */
        abort_unless(
            in_array($item->status_item, [
                OrderItemStatus::ANTRIAN_DESAIN, OrderItemStatus::PROSES_DESAIN, OrderItemStatus::MENUNGGU_PERSETUJUAN,
            ], true),
            422,
            'Desain barang ini sudah disetujui pelanggan, jadi mockupnya tidak bisa diganti lagi.'
        );

        $maks = (int) setting('upload_maks_mb', 10) * 1024;

        // Diperlakukan sama seperti unggahan pelanggan (KEP-064): gambar di-encode
        // ulang lewat GD, bukan disimpan mentah. Preview ini cuma dilihat lewat
        // layar, bukan dicetak, jadi dikecilkan tidak kehilangan gunanya, dan
        // berkas raksasa dari kamera HP tidak menumpuk di disk hosting.
        //
        // Gambarnya wajib (KEP-164): pelanggan menyetujui desain dari gambar ini,
        // dan gambar ini yang tercetak di lembar kerja. Mockup yang dulu dikirim
        // sebagai tautan saja membuat konveksi menerima lembar kerja tanpa desain.
        $request->validate([
            'preview' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', "max:{$maks}"],
            'tautan' => ['nullable', 'url', 'max:500'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ], [
            'preview.required' => self::PESAN_GAMBAR_WAJIB,
            'preview.image' => 'Preview harus gambar (JPG, PNG, atau WebP). File siap cetak dibagikan lewat tautan.',
            'preview.mimes' => 'Preview harus JPG, PNG, atau WebP. File siap cetak dibagikan lewat tautan.',
            'preview.max' => "Ukuran preview maksimal {$maks} KB.",
        ], ['preview' => 'preview mockup', 'tautan' => 'tautan file siap cetak']);

        // Roster tidak lengkap berarti ada jersey yang tidak tahu mau dicetak
        // nama siapa. Lebih murah dihentikan di sini daripada di meja printing.
        if ($item->product->roster_wajib && ! $this->roster->lengkap($item)) {
            return back()->withErrors(
                'Daftar pemain baru terisi '.$item->rosters()->count().' dari '.$item->qty.' baris. '
                .'Minta pelanggan melengkapinya sebelum mockup dikirim untuk disetujui.'
            );
        }

        $path = null;

        if ($request->hasFile('preview')) {
            try {
                $path = $this->pengunggah->simpan($request->file('preview'), 'mockup', 'public', denganThumb: false)['path'];
            } catch (RuntimeException $e) {
                return back()->withErrors($e->getMessage());
            }
        }

        try {
            $aset = $this->desain->unggahMockup(
                $item,
                $path,
                $request->input('tautan'),
                $request->input('catatan'),
                auth('web')->id(),
            );
        } catch (HttpExceptionInterface $e) {
            // Pelanggan menyetujui tepat saat versi ini diunggah: berkasnya
            // tidak dipakai, jadi tidak ditinggal di disk.
            $this->pengunggah->hapusDari('public', $path);

            throw $e;
        }

        ActivityLog::catat('mockup.diunggah', $item, ['versi' => $aset->versi]);

        return back()->with('sukses',
            "Mockup v{$aset->versi} dikirim. Pelanggan sekarang bisa menyetujuinya di halaman pesanan.");
    }

    /**
     * Melengkapi gambar desain yang sudah disetujui tetapi dulu dikirim sebagai
     * tautan saja (KEP-164), supaya lembar kerja punya gambar untuk dicetak.
     * Persetujuan dan versinya tidak berubah, dan gambar yang sudah ada tidak
     * bisa ditimpa: yang disetujui pelanggan tetap yang tercatat.
     */
    public function lengkapi(Request $request, OrderItem $item): RedirectResponse
    {
        /** @var DesignAsset|null $aset */
        $aset = $item->approvedDesign;

        abort_unless($item->sudahDisetujui() && $aset !== null, 422,
            'Desain barang ini belum disetujui pelanggan. Kirim mockup bergambar untuk disetujui lebih dulu.');
        abort_if($aset->path !== null, 422,
            'Desain yang disetujui sudah punya gambar, jadi tidak bisa diganti.');

        $maks = (int) setting('upload_maks_mb', 10) * 1024;

        $request->validate([
            'gambar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', "max:{$maks}"],
        ], [
            'gambar.required' => 'Pilih gambar desain yang disetujui pelanggan.',
        ], ['gambar' => 'gambar desain']);

        try {
            $path = $this->pengunggah->simpan($request->file('gambar'), 'mockup', 'public', denganThumb: false)['path'];
        } catch (RuntimeException $e) {
            return back()->withErrors(['gambar' => $e->getMessage()]);
        }

        $aset->update(['path' => $path]);

        ActivityLog::catat('desain.gambar_dilengkapi', $item, ['versi' => $aset->versi]);

        return back()->with('sukses', 'Gambar desain yang disetujui tersimpan. Cetak ulang lembar kerjanya supaya gambarnya ikut.');
    }
}
