<?php

namespace App\Http\Controllers\Panel;

use App\Enums\OrderStatus;
use App\Enums\StatusPembatalan;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\ProductionJob;
use App\Services\AlurPesanan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

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
            $order = Order::with([
                'customer', 'items.options', 'items.rosters', 'adjustments', 'payments',
                'items.product.images', 'items.variant', 'items.approvedDesign',
            ])
                ->where('kode_ambil', strtoupper($kode))
                ->orWhere('kode', strtoupper($kode))
                ->first();
        }

        return view('panel.serah-terima', [
            'order' => $order,
            'kode' => $kode,
            'tidakKetemu' => $kode !== '' && ! $order,
            /*
             * Siapa yang menyelesaikan tahap-tahapnya (KEP-115): pekerja yang
             * memindai QR, atau staf yang menekan Selesai di antrian. Gambar
             * contoh klien menyebutnya "Dikerjakan oleh".
             */
            'dikerjakanOleh' => $order ? $this->pengerja($order) : collect(),
            ...$this->daftar($request),
        ]);
    }

    /**
     * Tabel bertab di bawah kotak cari (KEP-130), seperti gambar klien.
     * Pesanan yang akan dikirim ikut di tab Siap, supaya jumlahnya sama dengan
     * angka di menu samping; aksinya ke halaman pesanan karena paket tidak
     * lewat meja kasir (KEP-026).
     *
     * @return array{tab: string, daftar: LengthAwarePaginator, jumlahSiap: int}
     */
    private function daftar(Request $request): array
    {
        $tab = in_array($request->query('tab'), ['sudah', 'semua'], true) ? (string) $request->query('tab') : 'siap';

        $siap = fn () => Order::query()->where('status', OrderStatus::SIAP_DIAMBIL);

        $kueri = match ($tab) {
            'sudah' => Order::query()->where('status', OrderStatus::SELESAI)->whereNotNull('diambil_oleh')
                ->orderByDesc('diambil_pada'),
            'semua' => Order::query()
                ->where(fn ($q) => $q
                    ->where('status', OrderStatus::SIAP_DIAMBIL)
                    ->orWhere(fn ($s) => $s->where('status', OrderStatus::SELESAI)->whereNotNull('diambil_oleh')))
                ->orderByRaw('diambil_pada IS NOT NULL')
                ->orderByDesc('diambil_pada')
                ->orderBy('siap_pada'),
            default => $siap()->orderBy('siap_pada'),
        };

        return [
            'tab' => $tab,
            'daftar' => $kueri
                ->with(['customer', 'items.options', 'items.rosters', 'payments', 'adjustments'])
                ->paginate(20)
                ->withQueryString(),
            'jumlahSiap' => $siap()->count(),
        ];
    }

    /**
     * Serahkan beberapa pesanan sekaligus ke satu pengambil (KEP-130),
     * misalnya reseller yang mengambil tiga pesanan. Aturannya sama dengan
     * serah terima satu per satu: hanya yang lunas, diambil di toko, dan
     * tanpa pengajuan pembatalan. Yang tidak memenuhi dilewati dan disebut.
     */
    public function massal(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'pesanan' => ['required', 'array', 'min:1', 'max:50'],
            'pesanan.*' => ['integer'],
            'diambil_oleh' => ['required', 'string', 'max:100'],
        ], [
            'pesanan.required' => 'Centang dulu pesanan yang diserahkan.',
            'diambil_oleh.required' => 'Tulis nama orang yang mengambil.',
        ], ['diambil_oleh' => 'nama pengambil']);

        $diserahkan = [];
        $dilewati = [];

        $daftar = Order::query()
            ->with(['items.options', 'items.rosters', 'payments', 'adjustments'])
            ->whereIn('id', $data['pesanan'])
            ->get();

        foreach ($daftar as $pesanan) {
            $pengajuan = $pesanan->pembatalans()->where('status', StatusPembatalan::DIAJUKAN->value)->exists();

            if (! $pesanan->bolehDiserahkan() || $pengajuan) {
                $dilewati[] = $pesanan->kode;

                continue;
            }

            $this->alur->serahkan($pesanan, $data['diambil_oleh'], auth('web')->id());
            ActivityLog::catat('pesanan.diserahkan', $pesanan, ['diambil_oleh' => $data['diambil_oleh'], 'massal' => true]);
            $diserahkan[] = $pesanan->kode;
        }

        $pesan = count($diserahkan).' pesanan diserahkan kepada '.$data['diambil_oleh'].'.';

        if ($dilewati !== []) {
            $pesan .= ' Dilewati karena belum lunas, dikirim, atau ada pengajuan pembatalan: '.implode(', ', $dilewati).'.';
        }

        return redirect()->route('panel.serah-terima')->with('sukses', $pesan);
    }

    /**
     * Nama yang menyelesaikan tahap-tahap pesanan ini, tanpa dobel.
     *
     * @return Collection<int, non-falsy-string>
     */
    private function pengerja(Order $order): Collection
    {
        return ProductionJob::query()
            ->with(['pekerja', 'penyelesai'])
            ->whereIn('order_item_id', $order->items->modelKeys())
            ->whereNotNull('selesai_pada')
            ->orderBy('selesai_pada')
            ->get()
            ->map(fn (ProductionJob $job) => $job->pekerja->nama ?? $job->penyelesai->name ?? null)
            ->filter()
            ->unique()
            ->values();
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
