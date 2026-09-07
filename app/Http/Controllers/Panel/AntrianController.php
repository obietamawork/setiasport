<?php

namespace App\Http\Controllers\Panel;

use App\Enums\OrderItemStatus;
use App\Enums\ProductionStage;
use App\Enums\ProductionStatus;
use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Services\AlurPesanan;
use Illuminate\Http\RedirectResponse;

/**
 * Antrian kerja per divisi — layar yang paling sering dibuka operator.
 *
 * Panel admin generik gagal di lingkungan workshop: operator jahit tidak peduli
 * pada 200 pesanan, dia peduli pada beberapa pekerjaan yang harus dia jahit hari
 * ini. Satu klik menyelesaikan pekerjaan SEKALIGUS menaikkan status pelanggan,
 * jadi tidak ada input ganda (docs/04).
 */
class AntrianController extends Controller
{
    /** Status item yang berada di tangan tiap divisi. */
    private const PEGANGAN = [
        'DESAIN' => [OrderItemStatus::ANTRIAN_DESAIN, OrderItemStatus::PROSES_DESAIN],
        'PRINT' => [OrderItemStatus::ANTRIAN_PRODUKSI, OrderItemStatus::PRINTING],
        'JAHIT' => [OrderItemStatus::JAHIT],
        'QC' => [OrderItemStatus::QC],
        'PACKING' => [OrderItemStatus::PACKING],
    ];

    public function __construct(private AlurPesanan $alur) {}

    public function index()
    {
        $tahap = auth()->user()->tahapProduksi();

        // Kalau hanya memegang satu divisi, tidak perlu memilih dulu.
        if (count($tahap) === 1) {
            return redirect()->route('panel.antrian.tahap', strtolower($tahap[0]->value));
        }

        return view('panel.antrian.index', [
            'tahap' => $tahap,
            'jumlah' => collect($tahap)->mapWithKeys(fn (ProductionStage $t) => [
                $t->value => $this->kueri($t)->count(),
            ]),
        ]);
    }

    public function tahap(string $tahap)
    {
        $stage = ProductionStage::tryFrom(strtoupper($tahap));

        abort_unless($stage, 404);
        abort_unless(auth()->user()->punyaIzin($stage->permission()), 403,
            'Peran Anda tidak memegang antrian '.$stage->label().'.');

        return view('panel.antrian.tahap', [
            'stage' => $stage,
            'pekerjaan' => $this->kueri($stage)->get(),
            'tahapLain' => collect(auth()->user()->tahapProduksi())
                ->reject(fn (ProductionStage $t) => $t === $stage),
        ]);
    }

    /**
     * Dua tahap punya status "antri" tersendiri di mata pelanggan. Mengambil
     * pekerjaannya berarti memindahkan item ke status kerja, supaya timeline
     * pelanggan menunjukkan waktu yang sebenarnya — bukan tahap yang tercentang
     * tanpa pernah dilewati.
     */
    private const ANTRI_KE_KERJA = [
        'ANTRIAN_DESAIN' => true,
        'ANTRIAN_PRODUKSI' => true,
    ];

    public function mulai(OrderItem $item): RedirectResponse
    {
        $this->pastikanBoleh($item);

        if (isset(self::ANTRI_KE_KERJA[$item->status_item->value])) {
            $baru = $this->alur->majukanItem($item, auth()->id());

            return back()->with('sukses', "{$item->nama_produk} sekarang: {$baru->label()}.");
        }

        $this->alur->mulaiKerja($item, auth()->id());

        return back()->with('sukses', "Mulai mengerjakan {$item->nama_produk}.");
    }

    public function selesai(OrderItem $item): RedirectResponse
    {
        $this->pastikanBoleh($item);

        $baru = $this->alur->majukanItem($item, auth()->id());

        return back()->with('sukses',
            "{$item->nama_produk} selesai dan diteruskan ke tahap {$baru->label()}.");
    }

    private function pastikanBoleh(OrderItem $item): void
    {
        $stage = collect(ProductionStage::cases())->first(
            fn (ProductionStage $t) => in_array($item->status_item, self::PEGANGAN[$t->value], true)
        );

        abort_unless($stage, 422, 'Item ini sedang tidak berada di antrian produksi mana pun.');
        abort_unless(auth()->user()->punyaIzin($stage->permission()), 403,
            'Item ini berada di antrian '.$stage->label().', yang bukan tanggung jawab peran Anda.');
    }

    private function kueri(ProductionStage $stage)
    {
        return OrderItem::query()
            ->with(['order.customer', 'product', 'productionJobs', 'rosters'])
            ->whereIn('status_item', array_map(
                fn (OrderItemStatus $s) => $s->value,
                self::PEGANGAN[$stage->value]
            ))
            ->whereHas('order', fn ($q) => $q->aktif())
            ->orderBy('order_id');
    }
}
