<?php

namespace App\Http\Controllers\Panel;

use App\Enums\OrderItemStatus;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\OrderItem;
use App\Services\AlurDesain;
use App\Services\PengunggahGambar;
use App\Services\RosterPemain;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class MockupController extends Controller
{
    public function __construct(
        private AlurDesain $desain,
        private RosterPemain $roster,
        private PengunggahGambar $pengunggah,
    ) {}

    public function store(Request $request, OrderItem $item): RedirectResponse
    {
        abort_unless(auth('web')->user()->punyaIzin('produksi.desain'), 403);
        abort_unless(
            in_array($item->status_item, [OrderItemStatus::ANTRIAN_DESAIN, OrderItemStatus::PROSES_DESAIN], true),
            422,
            'Item ini sedang tidak berada di antrian desain.'
        );

        $maks = (int) setting('upload_maks_mb', 10) * 1024;

        // Diperlakukan sama seperti unggahan pelanggan (KEP-064): gambar di-encode
        // ulang lewat GD, bukan disimpan mentah. Preview ini cuma dilihat lewat
        // layar, bukan dicetak, jadi dikecilkan tidak kehilangan gunanya, dan
        // berkas raksasa dari kamera HP tidak menumpuk di disk hosting.
        $request->validate([
            'preview' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', "max:{$maks}"],
            'tautan' => ['nullable', 'url', 'max:500'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ], [
            'preview.image' => 'Preview harus gambar (JPG, PNG, atau WebP). File siap cetak dibagikan lewat tautan.',
            'preview.mimes' => 'Preview harus JPG, PNG, atau WebP. File siap cetak dibagikan lewat tautan.',
            'preview.max' => "Ukuran preview maksimal {$maks} KB.",
        ], ['preview' => 'preview mockup', 'tautan' => 'tautan file siap cetak']);

        if (! $request->hasFile('preview') && ! $request->filled('tautan')) {
            return back()->withErrors('Unggah preview mockup atau tempelkan tautan file siap cetaknya.');
        }

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

        $aset = $this->desain->unggahMockup(
            $item,
            $path,
            $request->input('tautan'),
            $request->input('catatan'),
            auth('web')->id(),
        );

        ActivityLog::catat('mockup.diunggah', $item, ['versi' => $aset->versi]);

        return back()->with('sukses',
            "Mockup v{$aset->versi} dikirim. Pelanggan sekarang bisa menyetujuinya di halaman pesanan.");
    }
}
