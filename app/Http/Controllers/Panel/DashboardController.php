<?php

namespace App\Http\Controllers\Panel;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Services\DaftarTindakan;
use App\Services\KesehatanSistem;
use Illuminate\Contracts\View\View;

/**
 * Dashboard harian (docs/04), dengan tata letak dari revisi klien (KEP-112):
 * lima kartu berwarna, pesanan terbaru, stok, antrean produksi jersey, dan
 * jalan pintas, lalu daftar tindakan yang tetap menjadi isi utamanya.
 *
 * Daftar tindakannya diambil dari DaftarTindakan, sumber yang sama dengan
 * lonceng di bilah atas (KEP-111).
 */
class DashboardController extends Controller
{
    public function __invoke(KesehatanSistem $kesehatan, DaftarTindakan $tindakan): View
    {
        $hariIni = now()->startOfDay();

        return view('panel.dashboard', [
            // Kegagalan senyap didahulukan: yang tidak terlihat itulah yang mahal.
            'masalahSistem' => $kesehatan->masalah(),

            /*
             * Pemilik toko membuka halaman ini tiap pagi. Pertanyaan pertamanya
             * selalu sama: apa yang terjadi hari ini, dan apa yang menunggu.
             */
            'pesananHariIni' => Order::whereDate('created_at', $hariIni)->count(),
            'uangHariIni' => (int) Payment::where('status', PaymentStatus::LUNAS)
                ->whereDate('dibayar_pada', $hariIni)
                ->sum('jumlah'),
            // Kartu "Siap diambil/kirim" dari gambar contoh: yang menunggu
            // diambil di toko maupun yang menunggu diantar ke kurir.
            'siapDiambil' => Order::where('status', OrderStatus::SIAP_DIAMBIL)->count(),
            'sedangDikerjakan' => Order::where('status', OrderStatus::DIPROSES)->count(),
            'menungguPelunasan' => Order::where('status', OrderStatus::MENUNGGU_PELUNASAN)->count(),

            'pesananTerbaru' => Order::query()
                ->with(['customer', 'items'])
                ->latest('id')
                ->limit(5)
                ->get(),

            'antreanJersey' => $this->antreanJersey(),

            ...$tindakan->daftar(),

            'tanpaFoto' => Product::query()
                ->where('aktif', true)
                ->whereDoesntHave('images')
                ->count(),
            'produkAktif' => Product::where('aktif', true)->count(),
            'produkNonaktif' => Product::where('aktif', false)->count(),
        ]);
    }

    /**
     * Antrean konveksi dihitung per PESANAN, bukan per barang: pemilik toko
     * bertanya "berapa pesanan yang masih di desain", bukan berapa baris.
     *
     * @return array{desain: int, produksi: int, siap: int}
     */
    private function antreanJersey(): array
    {
        $dengan = fn (array $status) => Order::query()
            ->aktif()
            ->whereHas('items', fn ($i) => $i->whereIn('status_item', array_map(
                fn (OrderItemStatus $s) => $s->value, $status
            )))
            ->count();

        return [
            'desain' => $dengan([
                OrderItemStatus::ANTRIAN_DESAIN, OrderItemStatus::PROSES_DESAIN, OrderItemStatus::MENUNGGU_PERSETUJUAN,
            ]),
            'produksi' => $dengan([
                OrderItemStatus::ANTRIAN_PRODUKSI, OrderItemStatus::PRINTING, OrderItemStatus::PRESS,
                OrderItemStatus::POTONG, OrderItemStatus::JAHIT, OrderItemStatus::QC,
            ]),
            'siap' => Order::query()
                ->adaKustom()
                ->where('status', OrderStatus::SIAP_DIAMBIL)
                ->count(),
        ];
    }
}
