<?php

namespace App\Http\Controllers\Panel;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\AlurPesanan;
use App\Services\KebijakanPembatalan;
use App\Services\LangkahBerikut;
use App\Services\PengirimEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PesananController extends Controller
{
    public function __construct(
        private AlurPesanan $alur,
        private PengirimEmail $email,
    ) {}

    public function index(Request $request)
    {
        $status = $request->string('status')->toString();

        /*
         * Kustom dan stok toko dipisah karena alurnya memang berbeda (KEP-027):
         * yang satu melewati desain, persetujuan, printing, dan jahit; yang lain
         * cuma diambil dari rak. Mencampurnya di satu daftar membuat orang yang
         * sedang mengurus konveksi harus menyaring sendiri setiap kali.
         *
         * Pesanan campuran masuk ke tab Kustom, karena bagian kustomnya yang
         * menentukan kapan seluruh pesanan bisa diserahkan. Ia diberi penanda
         * sendiri di daftar supaya tidak ada yang kaget menemukan barang stok
         * di dalamnya.
         */
        $jalur = $request->string('jalur')->toString();

        $saring = fn ($q) => $q
            ->when($jalur === 'kustom', fn ($w) => $w->adaKustom())
            ->when($jalur === 'stok', fn ($w) => $w->hanyaStok());

        return view('panel.pesanan.index', [
            'pesanan' => Order::query()
                ->with(['customer', 'items.options', 'items.rosters', 'payments', 'adjustments'])
                ->tap($saring)
                ->when($status === 'aktif', fn ($q) => $q->aktif())
                ->when($status === 'tindakan', fn ($q) => $q->perluTindakan())
                ->when($status && ! in_array($status, ['aktif', 'tindakan'], true),
                    fn ($q) => $q->where('status', $status))
                ->when($request->filled('q'), function ($q) use ($request) {
                    $cari = '%'.$request->string('q').'%';
                    $q->where(fn ($w) => $w->where('kode', 'like', $cari)
                        ->orWhereHas('customer', fn ($c) => $c->where('nama', 'like', $cari)
                            ->orWhere('email', 'like', $cari)
                            ->orWhere('no_hp', 'like', $cari)));
                })
                ->latest('id')
                ->paginate(20)
                ->withQueryString(),
            'status' => $status,
            'jalur' => $jalur,
            // Hitungan status mengikuti tab yang sedang dibuka; kalau tidak,
            // angkanya menjanjikan hasil yang tidak akan muncul saat diklik.
            'jumlahStatus' => [
                // Ditaruh paling depan: inilah yang benar-benar menunggu orang
                // toko, sementara penyaring lain sekadar memilah keadaan.
                'tindakan' => Order::query()->tap($saring)->perluTindakan()->count(),
                'aktif' => Order::query()->tap($saring)->aktif()->count(),
                OrderStatus::MENUNGGU_PEMBAYARAN->value => Order::query()->tap($saring)->where('status', OrderStatus::MENUNGGU_PEMBAYARAN)->count(),
                OrderStatus::DIPROSES->value => Order::query()->tap($saring)->where('status', OrderStatus::DIPROSES)->count(),
                OrderStatus::MENUNGGU_PELUNASAN->value => Order::query()->tap($saring)->where('status', OrderStatus::MENUNGGU_PELUNASAN)->count(),
                OrderStatus::SIAP_DIAMBIL->value => Order::query()->tap($saring)->where('status', OrderStatus::SIAP_DIAMBIL)->count(),
                OrderStatus::DIKIRIM->value => Order::query()->tap($saring)->where('status', OrderStatus::DIKIRIM)->count(),
            ],
            'jumlahJalur' => [
                '' => Order::count(),
                'kustom' => Order::adaKustom()->count(),
                'stok' => Order::hanyaStok()->count(),
            ],
        ]);
    }

    public function show(Order $pesanan)
    {
        $pesanan->load([
            'customer', 'items.options', 'items.rosters', 'items.productionJobs',
            'adjustments.pembuat', 'payments.pencatat', 'payments.verifikator',
            'statusLogs.pelaku', 'statusLogs.orderItem', 'emailLogs',
        ]);

        $kebijakan = app(KebijakanPembatalan::class);

        return view('panel.pesanan.show', [
            'pesanan' => $pesanan,
            'pesanWa' => $this->pesanWhatsApp($pesanan),

            // Pengajuan yang belum diputuskan (KEP-041). Selama ada di sini,
            // pekerjaan tetap berjalan, jadi ia ditaruh paling atas di layar.
            'pembatalan' => $kebijakan->pengajuanTertunda($pesanan),
            'biayaSetup' => $kebijakan->biayaSetup($pesanan),
            'refundUsulan' => $kebijakan->refundUsulan($pesanan),

            // Satu kalimat yang menjawab "sekarang saya harus apa", supaya
            // halaman sepanjang ini tidak perlu dibaca seluruhnya lebih dulu.
            'langkah' => app(LangkahBerikut::class)->untuk($pesanan),
        ]);
    }

    /**
     * Barang yang tidak pernah diambil (KEP-043). Uang pelanggan hangus, jadi
     * tombolnya hanya muncul setelah batas waktunya benar-benar lewat.
     */
    public function hanguskan(Order $pesanan): RedirectResponse
    {
        abort_unless($pesanan->bolehDihanguskan(), 422,
            'Pesanan ini belum melewati batas waktu pengambilan.');

        $this->alur->hanguskan($pesanan, auth('web')->id());

        return back()->with('sukses', 'Pesanan ditandai hangus dan reservasi stoknya dilepas.');
    }

    /**
     * Penanda reseller (KEP-046). Hanya pengingat untuk admin: potongan
     * harganya tetap diberikan per pesanan lewat penyesuaian biaya.
     */
    public function tandaiReseller(Request $request, Order $pesanan): RedirectResponse
    {
        $data = $request->validate([
            'reseller' => ['required', 'boolean'],
            'catatan_reseller' => ['nullable', 'string', 'max:200'],
        ]);

        $pesanan->customer->update([
            'reseller' => (bool) $data['reseller'],
            'catatan_reseller' => $data['catatan_reseller'] ?? null,
        ]);

        ActivityLog::catat('pelanggan.reseller', $pesanan, [
            'pelanggan' => $pesanan->customer->nama,
            'reseller' => (bool) $data['reseller'],
        ]);

        return back()->with('sukses', $data['reseller']
            ? $pesanan->customer->nama.' ditandai sebagai reseller.'
            : 'Penanda reseller dilepas.');
    }

    public function majukanItem(Request $request, Order $pesanan, OrderItem $item): RedirectResponse
    {
        abort_unless($item->order_id === $pesanan->id, 404);

        if ($sudah = $this->sudahBerpindah($request, $item)) {
            return $sudah;
        }

        $baru = $this->alur->majukanItem($item, auth('web')->id());

        return back()->with('sukses', "{$item->nama_produk} sekarang: {$baru->labelStaf()}.");
    }

    /**
     * Barang dikembalikan satu tahap untuk dikerjakan ulang (KEP-098), misalnya
     * sablon luntur yang ketahuan di Finishing atau Selesai yang tertekan untuk
     * barang yang salah.
     */
    public function mundurkanItem(Request $request, Order $pesanan, OrderItem $item): RedirectResponse
    {
        abort_unless($item->order_id === $pesanan->id, 404);

        if ($sudah = $this->sudahBerpindah($request, $item)) {
            return $sudah;
        }

        $data = $request->validate([
            'alasan' => ['required', 'string', 'max:200'],
        ], [
            'alasan.required' => 'Tulis alasannya, misalnya "sablon luntur, cetak ulang". Ini jadi catatan orang produksi.',
        ], ['alasan' => 'alasan']);

        $dari = $item->status_item;
        $baru = $this->alur->mundurkanItem($item, $data['alasan'], auth('web')->id());

        ActivityLog::catat('pesanan.tahap-dimundurkan', $pesanan, [
            'item' => $item->nama_produk,
            'dari' => $dari->value,
            'ke' => $baru->value,
            'alasan' => $data['alasan'],
        ]);

        return back()->with('sukses', "{$item->nama_produk} dikembalikan ke {$baru->labelStaf()}.");
    }

    /**
     * Pembatalan langsung dari toko, misalnya pelanggan menelepon.
     *
     * Pesanan yang sudah dibayar wajib disertai keputusan pengembaliannya
     * (KEP-098), dicatat di tempat yang sama dengan pengajuan dari pelanggan
     * (KEP-041) supaya pelanggan membacanya di halaman pesanan. Dulu uangnya
     * tetap tercatat lunas di pesanan batal tanpa jejak dikembalikan atau
     * tidak, dan pengajuan pelanggan yang belum diputuskan tertinggal selamanya.
     */
    public function batalkan(Request $request, Order $pesanan, KebijakanPembatalan $kebijakan): RedirectResponse
    {
        $pesanan->load(['items.options', 'items.rosters', 'payments', 'adjustments']);

        if ($kebijakan->pengajuanTertunda($pesanan)) {
            return back()->withErrors('Pelanggan sudah mengajukan pembatalan pesanan ini. Putuskan lewat kotak '
                .'Pengajuan pembatalan di bagian atas, supaya pengembaliannya tercatat.');
        }

        $dibayar = $pesanan->sudah_dibayar;

        $data = $request->validate([
            'alasan' => ['required', 'string', 'max:200'],
            'refund_jumlah' => [$dibayar > 0 ? 'required' : 'nullable', 'integer', 'min:0', 'max:'.$dibayar],
            'refund_metode' => ['nullable', 'in:TRANSFER,TUNAI'],
        ], [
            'alasan.required' => 'Tulis alasan pembatalan: pelanggan berhak tahu, dan ini masuk riwayat pesanan.',
            'refund_jumlah.required' => 'Pelanggan sudah membayar '.rupiah($dibayar).'. Isi berapa yang dikembalikan, 0 kalau tidak ada.',
            'refund_jumlah.max' => 'Pengembalian tidak boleh melebihi '.rupiah($dibayar).' yang sudah dibayar.',
        ], ['alasan' => 'alasan pembatalan', 'refund_jumlah' => 'pengembalian']);

        if ($dibayar > 0) {
            $kebijakan->batalkanOlehToko(
                $pesanan,
                $data['alasan'],
                (int) $data['refund_jumlah'],
                $data['refund_metode'] ?? null,
                auth('web')->id(),
            );

            return back()->with('sukses', (int) $data['refund_jumlah'] > 0
                ? 'Pesanan dibatalkan. Kirim pengembalian '.rupiah((int) $data['refund_jumlah'])
                    .' ke pelanggan; nominalnya sudah tampil di halaman pesanannya.'
                : 'Pesanan dibatalkan tanpa pengembalian uang. Keputusannya tampil di halaman pesanan pelanggan.');
        }

        $this->alur->batalkan($pesanan, $data['alasan'], auth('web')->id());

        return back()->with('sukses', 'Pesanan dibatalkan dan reservasi stoknya dilepas.');
    }

    /** Pembanding tahap di tombol, sama seperti di Antrian (KEP-098). */
    private function sudahBerpindah(Request $request, OrderItem $item): ?RedirectResponse
    {
        $dari = OrderItemStatus::tryFrom($request->string('dari')->toString());

        if ($dari === null || $dari === $item->status_item) {
            return null;
        }

        return back()->with('peringatan', $item->nama_produk.' sudah berada di tahap '
            .$item->status_item->labelStaf().'. Tidak ada yang diubah: mungkin tombolnya tertekan '
            .'dua kali, atau orang lain lebih dulu menandainya.');
    }

    /**
     * Tanpa WhatsApp API (KEP-004), sistem menyiapkan teksnya dan admin yang
     * menekan kirim. Bukan otomatisasi, tapi nol biaya dan realistis.
     */
    private function pesanWhatsApp(Order $order): array
    {
        // Desain yang menunggu persetujuan dan bukti yang baru ditolak punya
        // pesannya sendiri (KEP-098); dulu yang tersalin "pembayaran sudah
        // kami terima" untuk keduanya.
        $ditolak = $order->status === OrderStatus::MENUNGGU_PEMBAYARAN
            && $this->email->alasanTolakTerakhir($order) !== null
            && ! $order->payments->contains(fn ($p) => $p->status === PaymentStatus::MENUNGGU && filled($p->bukti_path));

        $template = match (true) {
            $ditolak => 'pembayaran-ditolak',
            $order->items->contains('status_item', OrderItemStatus::MENUNGGU_PERSETUJUAN) => 'mockup-siap',
            default => null,
        } ?? match ($order->status) {
            OrderStatus::MENUNGGU_PEMBAYARAN => 'order-dibuat',
            OrderStatus::MENUNGGU_PELUNASAN => 'menunggu-pelunasan',
            OrderStatus::SIAP_DIAMBIL => 'pesanan-siap',
            OrderStatus::DIKIRIM => 'pesanan-dikirim',
            default => 'pembayaran-diterima',
        };

        return [
            'teks' => $this->email->teksWhatsApp($order, $template),
            'url' => 'https://wa.me/'.nomor_wa($order->customer->no_hp),
        ];
    }
}
