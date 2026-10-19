<?php

namespace App\Http\Controllers\Panel;

use App\Enums\FulfillmentType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\KesehatanSistem;
use Illuminate\Contracts\View\View;

/**
 * Dashboard sebagai DAFTAR TINDAKAN, bukan grafik omzet (docs/04).
 *
 * Selama pesanan belum ada (fase 2), yang paling berguna tiap pagi adalah
 * kesehatan katalog: harga yang belum lengkap dan stok yang menipis.
 */
class DashboardController extends Controller
{
    public function __invoke(KesehatanSistem $kesehatan): View
    {
        $jalurKustom = [FulfillmentType::PREORDER->value, FulfillmentType::BOTH->value];

        $peringatanHari = (int) \App\Models\Setting::ambil('ambil_peringatan_hari', 7);

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
            'siapDiambil' => Order::where('status', OrderStatus::SIAP_DIAMBIL)->count(),
            'sedangDikerjakan' => Order::where('status', OrderStatus::DIPROSES)->count(),
            'menungguPelunasan' => Order::where('status', OrderStatus::MENUNGGU_PELUNASAN)->count(),
            /*
             * Pengajuan pembatalan yang belum diputuskan (KEP-041). Selama
             * menggantung, konveksi tetap mengerjakan kain yang mungkin tidak
             * jadi dipakai, jadi ia setara mendesaknya dengan bukti bayar.
             */
            'pembatalanMenunggu' => Order::query()
                ->with('customer')
                ->whereHas('pembatalans', fn ($q) => $q->where('status', 'DIAJUKAN'))
                ->get(),

            // Pesanan lebih mendesak daripada katalog, jadi tampil lebih dulu.
            'perluVerifikasi' => Order::query()
                ->with('customer')
                ->whereHas('payments', fn ($q) => $q
                    ->where('status', PaymentStatus::MENUNGGU->value)
                    ->whereNotNull('bukti_path'))
                ->get(),

            // Barang siap tapi tak kunjung diambil memakan rak toko dan
            // paling sering terlupakan (docs/04).
            'lamaBelumDiambil' => Order::query()
                ->with('customer')
                ->where('status', OrderStatus::SIAP_DIAMBIL)
                ->where('siap_pada', '<', now()->subDays($peringatanHari))
                ->orderBy('siap_pada')
                ->get(),
            'peringatanHari' => $peringatanHari,

            /*
             * Sudah melewati batas hangus (KEP-043). Dipisah dari daftar di
             * atasnya karena tindakannya berbeda: yang ini bukan lagi "tolong
             * ditelepon", melainkan keputusan menghanguskan uang pelanggan.
             */
            'bolehHangus' => Order::query()
                ->with('customer')
                ->where('status', OrderStatus::SIAP_DIAMBIL)
                ->whereNotNull('siap_pada')
                ->where('siap_pada', '<', now()->subDays((int) \App\Models\Setting::ambil('hangus_hari', 30)))
                ->orderBy('siap_pada')
                ->get(),
            'hangusHari' => (int) \App\Models\Setting::ambil('hangus_hari', 30),

            'emailBermasalah' => EmailLog::perluTindakLanjut()
                ->with('order.customer')
                ->latest()
                ->limit(10)
                ->get(),

            /*
             * Produk kustom tanpa harga tidak bisa dihitung sendiri oleh
             * pelanggan (KEP-010). Ini temuan paling mendesak.
             *
             * Ada DUA cara sah mengisi harganya: tier per qty (priceTiers)
             * atau matriks per kategori/bahan (prices, KEP-039) -- produk
             * kustom cuma butuh SALAH SATU. Dulu di sini hanya diperiksa
             * priceTiers; produk yang dihargai lewat matriks (mayoritas
             * seragam/jersey di katalog) ikut tertuduh "belum berharga"
             * setiap hari walau pelanggan sudah bisa menghitungnya sendiri
             * di halaman kustom.
             */
            'tanpaTier' => Product::query()
                ->where('aktif', true)
                ->whereIn('fulfillment_type', $jalurKustom)
                ->whereDoesntHave('priceTiers')
                ->whereDoesntHave('prices')
                ->get(),

            'tanpaFoto' => Product::query()
                ->where('aktif', true)
                ->whereDoesntHave('images')
                ->count(),

            'stokHabis' => ProductVariant::query()
                ->with('product')
                ->whereColumn('stok', '<=', 'stok_dipesan')
                ->whereHas('product', fn ($q) => $q->where('aktif', true)
                    ->whereIn('fulfillment_type', [
                        FulfillmentType::READY_STOCK->value, FulfillmentType::BOTH->value,
                    ]))
                ->get(),

            'stokMenipis' => ProductVariant::query()
                ->with('product')
                ->whereRaw('stok - stok_dipesan between 1 and 3')
                ->whereHas('product', fn ($q) => $q->where('aktif', true))
                ->get(),

            'produkAktif' => Product::where('aktif', true)->count(),
            'produkNonaktif' => Product::where('aktif', false)->count(),
        ]);
    }
}
