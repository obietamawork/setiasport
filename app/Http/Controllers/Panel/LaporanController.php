<?php

namespace App\Http\Controllers\Panel;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Laporan seadanya, dan itu disengaja.
 *
 * Yang dibutuhkan pemilik toko adalah menjawab beberapa pertanyaan: berapa uang
 * yang benar-benar masuk, berapa yang masih tertahan, dan apa yang laku. Grafik
 * tidak menjawab satu pun dari itu lebih baik daripada angka.
 */
class LaporanController extends Controller
{
    public function __invoke(Request $request): View
    {
        [$dari, $sampai, $labelPeriode] = $this->periode($request);

        // Uang yang benar-benar diterima, bukan nilai pesanan. Inilah angka yang
        // bisa dicocokkan dengan rekening dan laci kasir.
        $pembayaran = Payment::query()
            ->where('status', PaymentStatus::LUNAS)
            ->whereBetween('dibayar_pada', [$dari, $sampai])
            ->get();

        $pesanan = Order::query()
            ->with(['items.options', 'items.rosters', 'adjustments', 'payments'])
            ->whereBetween('created_at', [$dari, $sampai])
            ->get();

        // Sisa tagihan dihitung atas seluruh pesanan yang masih berjalan,
        // bukan hanya yang masuk periode ini: uangnya memang belum diterima.
        $berjalan = Order::query()
            ->with(['items.options', 'items.rosters', 'adjustments', 'payments'])
            ->aktif()
            ->get();

        return view('panel.laporan', [
            'dari' => $dari,
            'sampai' => $sampai,
            'labelPeriode' => $labelPeriode,
            'pilihan' => $request->string('periode', '30hari')->toString(),

            'uangMasuk' => (int) $pembayaran->sum('jumlah'),
            'perMetode' => $pembayaran->groupBy(fn (Payment $p) => $p->metode->label())
                ->map(fn ($g) => ['jumlah' => (int) $g->sum('jumlah'), 'transaksi' => $g->count()])
                ->sortByDesc('jumlah'),

            'nilaiPesanan' => (int) $pesanan->sum(fn (Order $o) => $o->total),
            'jumlahPesanan' => $pesanan->count(),
            'jumlahSelesai' => $pesanan->where('status', OrderStatus::SELESAI)->count(),
            'jumlahBatal' => $pesanan->whereIn('status', [OrderStatus::DIBATALKAN, OrderStatus::KADALUARSA])->count(),

            'sisaTertahan' => (int) $berjalan->sum(fn (Order $o) => $o->sisa_tagihan),
            'pesananBerjalan' => $berjalan->count(),

            'terlaris' => $this->terlaris($dari, $sampai),
        ]);
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string} */
    private function periode(Request $request): array
    {
        $kini = CarbonImmutable::now();

        return match ($request->string('periode')->toString()) {
            'bulan-ini' => [$kini->startOfMonth(), $kini->endOfDay(), 'Bulan ini'],
            'bulan-lalu' => [
                // $kini->subMonth() langsung (tanggal 29-31) meleset ke bulan
                // INI lagi pada bulan berumur 31 hari yang bulan sebelumnya
                // lebih pendek (Maret, Mei, Juli, Oktober, Desember): mundur
                // sebulan dari tanggal 31 dibulatkan maju ke tanggal 1-3 bulan
                // ini, bukan mendarat di bulan lalu. Mundur dari tanggal 1
                // (yang selalu ada di semua bulan) baru menghitung isi
                // bulannya supaya tidak pernah meleset.
                $kini->startOfMonth()->subMonth(),
                $kini->startOfMonth()->subMonth()->endOfMonth(),
                'Bulan lalu',
            ],
            'tahun-ini' => [$kini->startOfYear(), $kini->endOfDay(), 'Tahun ini'],
            default => [$kini->subDays(30)->startOfDay(), $kini->endOfDay(), '30 hari terakhir'],
        };
    }

    /**
     * `order_items.nama_produk` adalah SNAPSHOT nama saat pesanan dibuat (lihat
     * komentar di `OrderItem`) -- dikelompokkan dari itu, produk yang pernah
     * diganti namanya akan terpecah jadi dua baris "produk berbeda" di sini,
     * dan terlaris sungguhan bisa tidak muncul di Top 10 sama sekali karena
     * jumlahnya terbelah dua. Dikelompokkan dari `product_id` (tidak pernah
     * berubah) supaya sejarah penjualannya tetap menyatu.
     */
    private function terlaris(CarbonImmutable $dari, CarbonImmutable $sampai)
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->whereBetween('orders.created_at', [$dari, $sampai])
            ->whereNotIn('orders.status', [
                OrderStatus::DIBATALKAN->value, OrderStatus::KADALUARSA->value,
            ])
            ->groupBy('products.id')
            ->orderByDesc(DB::raw('SUM(order_items.qty)'))
            ->limit(10)
            ->get([
                'products.nama as nama_produk',
                DB::raw('SUM(order_items.qty) as total_qty'),
                DB::raw('COUNT(DISTINCT orders.id) as total_pesanan'),
            ]);
    }
}
