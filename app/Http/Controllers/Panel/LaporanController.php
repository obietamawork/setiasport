<?php

namespace App\Http\Controllers\Panel;

use App\Enums\FulfillmentType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StatusPembatalan;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Pembatalan;
use App\Models\ProductVariant;
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
 *
 * Sejak revisi klien (KEP-117) dipecah tiga halaman dengan periode yang sama:
 * Penjualan (uang dan pesanan), Stok barang (isi rak dan nilainya), dan
 * Produk terlaris.
 */
class LaporanController extends Controller
{
    public function penjualan(Request $request): View
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

            // Uang yang keluar lagi lewat pembatalan (KEP-098). Tanpa ini,
            // angka di atas lebih besar dari isi rekening setiap kali ada
            // pesanan berbayar yang dibatalkan.
            'dikembalikan' => (int) Pembatalan::query()
                ->where('status', StatusPembatalan::DISETUJUI->value)
                ->whereBetween('diputuskan_pada', [$dari, $sampai])
                ->sum('refund_jumlah'),
            'perMetode' => $pembayaran->groupBy(fn (Payment $p) => $p->metode->label())
                ->map(fn ($g) => ['jumlah' => (int) $g->sum('jumlah'), 'transaksi' => $g->count()])
                ->sortByDesc('jumlah'),

            'nilaiPesanan' => (int) $pesanan->sum(fn (Order $o) => $o->total),
            'jumlahPesanan' => $pesanan->count(),
            'jumlahSelesai' => $pesanan->where('status', OrderStatus::SELESAI)->count(),
            'jumlahBatal' => $pesanan->whereIn('status', [OrderStatus::DIBATALKAN, OrderStatus::KADALUARSA])->count(),

            'sisaTertahan' => (int) $berjalan->sum(fn (Order $o) => $o->sisa_tagihan),
            'pesananBerjalan' => $berjalan->count(),

            'terlaris' => $this->kueriTerlaris($dari, $sampai, 5),
        ]);
    }

    /**
     * Isi rak barang ready stock saat ini dan nilainya menurut harga jual.
     * Bukan laporan berperiode: stok adalah keadaan sekarang, dan riwayat
     * penyesuaiannya sudah tercatat di log aktivitas (stok.disesuaikan).
     */
    public function stok(Request $request): View
    {
        $status = $request->string('status')->toString();
        $status = in_array($status, ['aman', 'menipis', 'habis'], true) ? $status : '';
        $kata = mb_strtolower($request->string('q')->squish()->toString());

        $semua = ProductVariant::query()
            ->with('product')
            ->whereHas('product', fn ($q) => $q->whereIn('fulfillment_type', [
                FulfillmentType::READY_STOCK->value, FulfillmentType::BOTH->value,
            ]))
            ->get()
            ->sortBy(fn (ProductVariant $v) => mb_strtolower($v->product->nama.' '.$v->label))
            ->values();

        $keadaan = fn (ProductVariant $v) => match (true) {
            $v->stok_tersedia <= 0 => 'habis',
            $v->stok_tersedia <= 3 => 'menipis',
            default => 'aman',
        };

        return view('panel.laporan.stok', [
            'status' => $status,
            'varian' => $semua
                ->when($status !== '', fn ($c) => $c->filter(fn (ProductVariant $v) => $keadaan($v) === $status))
                ->when($kata !== '', fn ($c) => $c->filter(fn (ProductVariant $v) => str_contains(
                    mb_strtolower($v->product->nama.' '.$v->product->sku.' '.$v->sku), $kata)))
                ->values(),
            // Stok minus (salah ketik, barang rusak) tidak boleh mengurangi nilai rak.
            'nilaiStok' => (int) $semua->sum(fn (ProductVariant $v) => max(0, $v->stok) * $v->harga),
            'totalUnit' => (int) $semua->sum(fn (ProductVariant $v) => max(0, $v->stok)),
            'jumlahHabis' => $semua->filter(fn (ProductVariant $v) => $keadaan($v) === 'habis')->count(),
            'jumlahMenipis' => $semua->filter(fn (ProductVariant $v) => $keadaan($v) === 'menipis')->count(),
            'riwayat' => ActivityLog::query()
                ->with('user')
                ->where('aksi', 'stok.disesuaikan')
                ->latest('created_at')
                ->limit(20)
                ->get(),
        ]);
    }

    public function terlaris(Request $request): View
    {
        [$dari, $sampai, $labelPeriode] = $this->periode($request);

        $terlaris = $this->kueriTerlaris($dari, $sampai, 50);

        return view('panel.laporan.terlaris', [
            'dari' => $dari,
            'sampai' => $sampai,
            'labelPeriode' => $labelPeriode,
            'pilihan' => $request->string('periode', '30hari')->toString(),
            'terlaris' => $terlaris,
            'totalQty' => (int) $terlaris->sum('total_qty'),
            'totalPesanan' => (int) DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->whereNotNull('order_items.product_id')
                ->whereBetween('orders.created_at', [$dari, $sampai])
                ->whereNotIn('orders.status', [OrderStatus::DIBATALKAN->value, OrderStatus::KADALUARSA->value])
                ->distinct()
                ->count('orders.id'),
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
     *
     * `products.nama` ikut disebut di GROUP BY walau bergantung penuh pada id:
     * MySQL 8 memahaminya, MariaDB di hosting tidak dan menolak kuerinya
     * (ONLY_FULL_GROUP_BY, KEP-093).
     */
    private function kueriTerlaris(CarbonImmutable $dari, CarbonImmutable $sampai, int $batas)
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->whereBetween('orders.created_at', [$dari, $sampai])
            ->whereNotIn('orders.status', [
                OrderStatus::DIBATALKAN->value, OrderStatus::KADALUARSA->value,
            ])
            ->groupBy('products.id', 'products.nama')
            ->orderByDesc(DB::raw('SUM(order_items.qty)'))
            ->limit($batas)
            ->get([
                'products.nama as nama_produk',
                DB::raw('SUM(order_items.qty) as total_qty'),
                DB::raw('COUNT(DISTINCT orders.id) as total_pesanan'),
            ]);
    }
}
