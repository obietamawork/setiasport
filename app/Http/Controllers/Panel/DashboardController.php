<?php

namespace App\Http\Controllers\Panel;

use App\Enums\FulfillmentType;
use App\Enums\MetodeAmbil;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
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

        $peringatanHari = (int) Setting::ambil('ambil_peringatan_hari', 7);

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
            'siapDiambil' => Order::where('status', OrderStatus::SIAP_DIAMBIL)
                ->where('metode_ambil', '!=', MetodeAmbil::KIRIM->value)
                ->count(),
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
            // paling sering terlupakan (docs/04). Pesanan yang dikirim punya
            // daftarnya sendiri di bawah: yang belum bergerak di sana toko.
            'lamaBelumDiambil' => Order::query()
                ->with('customer')
                ->where('status', OrderStatus::SIAP_DIAMBIL)
                ->where('metode_ambil', '!=', MetodeAmbil::KIRIM->value)
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
                ->where('metode_ambil', '!=', MetodeAmbil::KIRIM->value)
                ->whereNotNull('siap_pada')
                ->where('siap_pada', '<', now()->subDays((int) Setting::ambil('hangus_hari', 30)))
                ->orderBy('siap_pada')
                ->get(),
            'hangusHari' => (int) Setting::ambil('hangus_hari', 30),

            /*
             * Tiga hal yang dulu tidak punya tempat di sini (KEP-098):
             *
             * - Desain yang lebih dari dua hari menunggu persetujuan. Hanya
             *   pelanggan yang bisa menyetujuinya, dan docs/04 sudah menyebutnya
             *   titik yang paling sering nyangkut.
             * - Barang siap yang menunggu dikirim. Bolanya di toko, bukan di
             *   pelanggan, jadi tidak boleh tercampur dengan "belum diambil".
             * - Paket yang menurut kurir sudah sampai, atau sudah dua minggu di
             *   jalan. Pesanan dikirim baru tertutup kalau ada yang menandai.
             */
            'persetujuanLama' => Order::query()
                ->with('customer')
                ->aktif()
                ->whereHas('items', fn ($i) => $i
                    ->where('status_item', OrderItemStatus::MENUNGGU_PERSETUJUAN->value)
                    ->whereDoesntHave('designAssets', fn ($d) => $d
                        ->where('created_at', '>=', now()->subDays(2))))
                ->oldest('updated_at')
                ->get(),

            'siapDikirim' => Order::query()
                ->with(['customer', 'items.options', 'items.rosters', 'payments', 'adjustments'])
                ->where('status', OrderStatus::SIAP_DIAMBIL)
                ->where('metode_ambil', MetodeAmbil::KIRIM->value)
                ->orderBy('siap_pada')
                ->get(),

            'paketPerluDitutup' => Order::query()
                ->with('customer')
                ->where('status', OrderStatus::DIKIRIM)
                ->where(fn ($q) => $q
                    ->whereIn('lacak_status', ['delivered', 'received', 'DELIVERED', 'RECEIVED'])
                    ->orWhere('dikirim_pada', '<', now()->subDays(14)))
                ->orderBy('dikirim_pada')
                ->get(),

            // Satu baris per pesanan (KEP-098): tindak lanjutnya memang per
            // pesanan, dan lima email gagal ke alamat yang sama dulu memakan
            // lima baris.
            'emailBermasalah' => EmailLog::perluTindakLanjut()
                ->with('order.customer')
                ->latest()
                ->limit(50)
                ->get()
                ->unique(fn (EmailLog $log) => $log->order_id ?? 'log-'.$log->id)
                ->take(10),

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
