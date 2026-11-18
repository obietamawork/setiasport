<?php

namespace App\Services;

use App\Enums\FulfillmentType;
use App\Enums\MetodeAmbil;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\EmailLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Yang menunggu orang toko, dipakai dua tempat (KEP-111): dashboard
 * menampilkan daftarnya satu per satu, lonceng di bilah atas cukup
 * menyebut jumlahnya per jenis dari halaman mana pun.
 *
 * Satu sumber supaya keduanya tidak bisa berselisih: lonceng yang menyebut
 * "3 bukti transfer" sementara dashboard tidak memuat satu pun membuat staf
 * berhenti mempercayai keduanya.
 */
class DaftarTindakan
{
    public function __construct(private KesehatanSistem $kesehatan) {}

    /**
     * Isi lengkap untuk dashboard, urut dari yang paling mendesak.
     *
     * @return array{
     *     pembatalanMenunggu: Collection<int, Order>, bolehHangus: Collection<int, Order>,
     *     perluVerifikasi: Collection<int, Order>, siapDikirim: Collection<int, Order>,
     *     persetujuanLama: Collection<int, Order>, lamaBelumDiambil: Collection<int, Order>,
     *     paketPerluDitutup: Collection<int, Order>, tanpaTier: Collection<int, Product>,
     *     emailBermasalah: Collection<int, EmailLog>, stokHabis: Collection<int, ProductVariant>,
     *     stokMenipis: Collection<int, ProductVariant>, peringatanHari: int, hangusHari: int
     * }
     */
    public function daftar(): array
    {
        return [
            'pembatalanMenunggu' => $this->pembatalanMenunggu()->with('customer')->get(),
            'bolehHangus' => $this->bolehHangus()->with('customer')->orderBy('siap_pada')->get(),
            'perluVerifikasi' => $this->perluVerifikasi()->with('customer')->get(),
            'siapDikirim' => $this->siapDikirim()
                ->with(['customer', 'items.options', 'items.rosters', 'payments', 'adjustments'])
                ->orderBy('siap_pada')
                ->get(),
            'persetujuanLama' => $this->persetujuanLama()->with('customer')->oldest('updated_at')->get(),
            'lamaBelumDiambil' => $this->lamaBelumDiambil()->with('customer')->orderBy('siap_pada')->get(),
            'paketPerluDitutup' => $this->paketPerluDitutup()->with('customer')->orderBy('dikirim_pada')->get(),
            'tanpaTier' => $this->tanpaTier()->get(),
            'emailBermasalah' => $this->emailBermasalah()->take(10),
            'stokHabis' => $this->stokHabis()->with('product.images')->get(),
            'stokMenipis' => $this->stokMenipis()->with('product.images')->get(),
            'peringatanHari' => $this->peringatanHari(),
            'hangusHari' => $this->hangusHari(),
        ];
    }

    /**
     * Isi lonceng untuk satu pengguna: hanya jenis yang halamannya boleh ia
     * buka, supaya tidak ada tautan yang berujung "tidak diizinkan".
     *
     * Sengaja tidak disimpan di cache: urusan yang baru ditangani harus hilang
     * dari lonceng di halaman berikutnya, bukan setengah menit kemudian. Isinya
     * hanya hitungan, dan sebagian besar kuerinya menyaring kolom status.
     *
     * @return list<array{label: string, jumlah: int, url: string, mendesak: bool}>
     */
    public function lonceng(User $user): array
    {
        $hitungan = $this->hitungan();

        $tindakan = route('panel.pesanan.index', ['status' => 'tindakan']);
        $dashboard = route('panel.dashboard');

        $jenis = [
            ['masalahSistem', 'Masalah sistem perlu diperiksa', 'pengaturan.kelola', $dashboard, true],
            ['pembatalanMenunggu', 'Pengajuan pembatalan', 'order.lihat', $tindakan, true],
            ['bolehHangus', 'Lewat batas pengambilan', 'order.lihat', $dashboard, true],
            ['perluVerifikasi', 'Bukti transfer menunggu diperiksa', 'order.lihat', $tindakan, true],
            ['siapDikirim', 'Siap dikirim', 'order.lihat', $dashboard, true],
            ['persetujuanLama', 'Desain menunggu persetujuan lebih dari 2 hari', 'order.lihat', $dashboard, false],
            ['lamaBelumDiambil', 'Siap tapi lama belum diambil', 'order.lihat', $dashboard, false],
            ['paketPerluDitutup', 'Paket perlu dipastikan', 'order.lihat', $dashboard, false],
            ['emailBermasalah', 'Email tidak sampai', 'order.lihat', $dashboard, false],
            ['tanpaTier', 'Harga produk belum lengkap', 'katalog.kelola', route('panel.produk.index', ['saring' => 'belum-lengkap']), false],
            ['stok', 'Stok perlu diisi', 'stok.kelola', route('panel.stok.index'), false],
        ];

        $hasil = [];

        foreach ($jenis as [$kunci, $label, $izin, $url, $mendesak]) {
            $jumlah = $hitungan[$kunci] ?? 0;

            if ($jumlah > 0 && $user->punyaIzin($izin)) {
                $hasil[] = ['label' => $label, 'jumlah' => $jumlah, 'url' => $url, 'mendesak' => $mendesak];
            }
        }

        return $hasil;
    }

    /** @return array<string, int> */
    private function hitungan(): array
    {
        return [
            'masalahSistem' => count($this->kesehatan->masalah()),
            'pembatalanMenunggu' => $this->pembatalanMenunggu()->count(),
            'bolehHangus' => $this->bolehHangus()->count(),
            'perluVerifikasi' => $this->perluVerifikasi()->count(),
            'siapDikirim' => $this->siapDikirim()->count(),
            'persetujuanLama' => $this->persetujuanLama()->count(),
            'lamaBelumDiambil' => $this->lamaBelumDiambil()->count(),
            'paketPerluDitutup' => $this->paketPerluDitutup()->count(),
            'emailBermasalah' => $this->emailBermasalah()->count(),
            'tanpaTier' => $this->tanpaTier()->count(),
            'stok' => $this->stokHabis()->count() + $this->stokMenipis()->count(),
        ];
    }

    /**
     * Pengajuan pembatalan yang belum diputuskan (KEP-041). Selama
     * menggantung, konveksi tetap mengerjakan kain yang mungkin tidak jadi
     * dipakai, jadi ia setara mendesaknya dengan bukti bayar.
     *
     * @return Builder<Order>
     */
    private function pembatalanMenunggu(): Builder
    {
        return Order::query()->whereHas('pembatalans', fn ($q) => $q->where('status', 'DIAJUKAN'));
    }

    /**
     * Sudah melewati batas hangus (KEP-043). Dipisah dari "lama belum
     * diambil" karena tindakannya berbeda: bukan lagi "tolong ditelepon",
     * melainkan keputusan menghanguskan uang pelanggan.
     *
     * @return Builder<Order>
     */
    private function bolehHangus(): Builder
    {
        return Order::query()
            ->where('status', OrderStatus::SIAP_DIAMBIL)
            ->where('metode_ambil', '!=', MetodeAmbil::KIRIM->value)
            ->whereNotNull('siap_pada')
            ->where('siap_pada', '<', now()->subDays($this->hangusHari()));
    }

    /** @return Builder<Order> */
    private function perluVerifikasi(): Builder
    {
        return Order::query()->whereHas('payments', fn ($q) => $q
            ->where('status', PaymentStatus::MENUNGGU->value)
            ->whereNotNull('bukti_path'));
    }

    /**
     * Barang siap yang menunggu dikirim (KEP-098). Bolanya di toko, bukan di
     * pelanggan, jadi tidak boleh tercampur dengan "belum diambil".
     *
     * @return Builder<Order>
     */
    private function siapDikirim(): Builder
    {
        return Order::query()
            ->where('status', OrderStatus::SIAP_DIAMBIL)
            ->where('metode_ambil', MetodeAmbil::KIRIM->value);
    }

    /**
     * Desain yang lebih dari dua hari menunggu persetujuan (KEP-098). Hanya
     * pelanggan yang bisa menyetujui, dan docs/04 menyebutnya titik yang
     * paling sering nyangkut.
     *
     * @return Builder<Order>
     */
    private function persetujuanLama(): Builder
    {
        return Order::query()
            ->aktif()
            ->whereHas('items', fn ($i) => $i
                ->where('status_item', OrderItemStatus::MENUNGGU_PERSETUJUAN->value)
                ->whereDoesntHave('designAssets', fn ($d) => $d
                    ->where('created_at', '>=', now()->subDays(2))));
    }

    /**
     * Barang siap tapi tak kunjung diambil memakan rak toko dan paling sering
     * terlupakan (docs/04).
     *
     * @return Builder<Order>
     */
    private function lamaBelumDiambil(): Builder
    {
        return Order::query()
            ->where('status', OrderStatus::SIAP_DIAMBIL)
            ->where('metode_ambil', '!=', MetodeAmbil::KIRIM->value)
            ->where('siap_pada', '<', now()->subDays($this->peringatanHari()));
    }

    /**
     * Pesanan dikirim baru tertutup kalau ada yang menandai (KEP-026): yang
     * menurut kurir sudah sampai, atau sudah dua minggu di jalan.
     *
     * @return Builder<Order>
     */
    private function paketPerluDitutup(): Builder
    {
        return Order::query()
            ->where('status', OrderStatus::DIKIRIM)
            ->where(fn ($q) => $q
                ->whereIn('lacak_status', ['delivered', 'received', 'DELIVERED', 'RECEIVED'])
                ->orWhere('dikirim_pada', '<', now()->subDays(14)));
    }

    /**
     * Satu baris per pesanan (KEP-098): tindak lanjutnya memang per pesanan,
     * dan lima email gagal ke alamat yang sama dulu memakan lima baris.
     *
     * @return Collection<int, EmailLog>
     */
    private function emailBermasalah(): Collection
    {
        return EmailLog::perluTindakLanjut()
            ->with('order.customer')
            ->latest()
            ->limit(50)
            ->get()
            ->unique(fn (EmailLog $log) => $log->order_id ?? 'log-'.$log->id)
            ->values();
    }

    /**
     * Produk kustom tanpa harga tidak bisa dihitung sendiri oleh pelanggan
     * (KEP-010). Ada dua cara sah mengisi harganya, tier per qty atau matriks
     * per kategori/bahan (KEP-039), dan produk kustom cuma butuh salah satu.
     *
     * @return Builder<Product>
     */
    private function tanpaTier(): Builder
    {
        return Product::query()
            ->where('aktif', true)
            ->whereIn('fulfillment_type', [FulfillmentType::PREORDER->value, FulfillmentType::BOTH->value])
            ->whereDoesntHave('priceTiers')
            ->whereDoesntHave('prices');
    }

    /** @return Builder<ProductVariant> */
    private function stokHabis(): Builder
    {
        return ProductVariant::query()
            ->whereColumn('stok', '<=', 'stok_dipesan')
            ->whereHas('product', fn ($q) => $q->where('aktif', true)
                ->whereIn('fulfillment_type', [
                    FulfillmentType::READY_STOCK->value, FulfillmentType::BOTH->value,
                ]));
    }

    /** @return Builder<ProductVariant> */
    private function stokMenipis(): Builder
    {
        return ProductVariant::query()
            ->whereRaw('stok - stok_dipesan between 1 and 3')
            ->whereHas('product', fn ($q) => $q->where('aktif', true));
    }

    private function peringatanHari(): int
    {
        return (int) Setting::ambil('ambil_peringatan_hari', 7);
    }

    private function hangusHari(): int
    {
        return (int) Setting::ambil('hangus_hari', 30);
    }
}
